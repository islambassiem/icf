<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Deletes an account.
 *
 * Your foreign keys have no cascade, and payments and exam history should be kept for accounting,
 * so an account that has any of them is ANONYMISED instead of removed: every personal detail goes,
 * the records stay. An account with no subscriptions or exams is deleted outright.
 */
class DeleteAccount
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $hasRecords = $user->subscriptions()->exists() || $user->examAttempts()->exists();

            // @phpstan-ignore-next-line
            if (method_exists($user, 'passkeys')) {
                $user->passkeys()->delete();
            }

            if (! $hasRecords) {
                $user->delete();

                return;
            }

            $user->forceFill([
                'name' => 'Deleted user',
                'email' => "deleted-{$user->id}@deleted.invalid",
                'password' => Str::random(64),
                'remember_token' => null,
                'email_verified_at' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();
        });
    }
}
