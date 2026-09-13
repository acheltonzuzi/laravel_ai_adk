<?php

namespace App\Ai\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeleteUser implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Permanently delete a user. Find the user ID with the search_user tool first. The signed in user cannot be deleted.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $user = User::find($validated['id']);

        if ($user === null) {
            return json_encode(['error' => 'No user found with the given ID.']);
        }

        if (Auth::user()?->is($user)) {
            return json_encode(['error' => 'You cannot delete the account you are signed in with.']);
        }

        $deleted = ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];

        $user->delete();

        return json_encode(['deleted' => $deleted]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The ID of the user to delete.')
                ->required(),
        ];
    }

    /**
     * Deleting a user is irreversible, so always ask first.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $user = User::find($request['id']);

        return Approval::required(sprintf(
            'This will permanently delete %s.',
            $user === null ? 'user #'.($request['id'] ?? 'unknown') : "{$user->name} ({$user->email})",
        ));
    }
}
