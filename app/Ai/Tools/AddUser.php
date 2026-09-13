<?php

namespace App\Ai\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AddUser implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'This tool may be used to add a new user to the system.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create($validated);

        return json_encode([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The full name of the user.')
                ->required(),
            'email' => $schema->string()
                ->format('email')
                ->description('The email address of the user. Must not already be in use.')
                ->required(),
            'password' => $schema->string()
                ->description('The password of the user. Must be at least 8 characters.')
                ->required(),
        ];
    }

    /**
     * Creating a user is irreversible from the chat, so always ask first.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        return Approval::required(sprintf(
            'This will create a new user account for %s.',
            $request['email'] ?? 'an unknown email address',
        ));
    }
}
