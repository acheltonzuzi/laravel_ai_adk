<?php

namespace App\Ai\Tools;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rules\Password;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class EditUser implements Approvable, Tool
{
    use InteractsWithApprovals, ProfileValidationRules;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Update the name, email or password of an existing user. Find the user ID with the search_user tool first. Only the fields you provide are changed.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $user = User::find($request['id']);

        if ($user === null) {
            return json_encode(['error' => 'No user found with the given ID.']);
        }

        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['sometimes', ...$this->nameRules()],
            'email' => ['sometimes', ...$this->emailRules($user->id)],
            'password' => ['sometimes', 'string', Password::default()],
        ]);

        $user->fill(array_intersect_key($validated, array_flip(['name', 'email'])));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if (isset($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

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
            'id' => $schema->integer()
                ->description('The ID of the user to update.')
                ->required(),
            'name' => $schema->string()
                ->description('The new full name. Omit to keep the current one.'),
            'email' => $schema->string()
                ->format('email')
                ->description('The new email address. Omit to keep the current one. Changing it clears the email verification.'),
            'password' => $schema->string()
                ->description('The new password. Omit to keep the current one.'),
        ];
    }

    /**
     * Changing someone else's credentials should never happen silently.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $changes = array_values(array_filter(
            ['name', 'email', 'password'],
            fn (string $field): bool => ($request[$field] ?? null) !== null,
        ));

        return Approval::required(sprintf(
            'This will update %s of user #%s.',
            $changes === [] ? 'nothing' : implode(', ', $changes),
            $request['id'] ?? 'unknown',
        ));
    }
}
