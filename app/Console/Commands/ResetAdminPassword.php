<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Forgot an admin password and cannot sign in? Run this on the server.
 * It also switches the account back on, so a locked-out admin is fully restored.
 */
class ResetAdminPassword extends Command
{
    protected $signature = 'admin:reset-password
        {user? : Username or e-mail of the admin (asked for when left out)}
        {--password= : The new password (otherwise it is asked for, hidden — safer than typing it here)}';

    protected $description = 'Set a new password for an admin user (and make sure the account is active)';

    public function handle(): int
    {
        $user = $this->findUser((string) ($this->argument('user') ?? ''));

        if (! $user) {
            return self::FAILURE;
        }

        $password = $this->option('password');

        if ($password === null) {
            $password = (string) $this->secret('New password (at least 8 characters)');

            if ($password !== (string) $this->secret('New password again')) {
                $this->error('The two passwords are not the same. Nothing was changed.');

                return self::FAILURE;
            }
        }

        if (mb_strlen((string) $password) < 8) {
            $this->error('The password must be at least 8 characters. Nothing was changed.');

            return self::FAILURE;
        }

        $user->forceFill(['is_active' => true, 'remember_token' => Str::random(60)]);
        $user->password = (string) $password;
        $user->save();

        $this->info("Password changed for \"{$user->username}\" ({$user->role}); the account is active. Sign in with the new password.");

        return self::SUCCESS;
    }

    private function findUser(string $login): ?AdminUser
    {
        if (AdminUser::count() === 0) {
            $this->error('There are no admin users yet. Run: php artisan db:seed (creates the first admin).');

            return null;
        }

        if ($login === '') {
            $names = AdminUser::orderBy('id')->pluck('username')->all();
            $login = count($names) === 1 ? $names[0] : (string) $this->choice('Which admin?', $names);
        }

        $user = AdminUser::where('username', $login)->orWhere('email', $login)->first();

        if (! $user) {
            $this->error("No admin with the username or e-mail \"{$login}\". Admins: ".AdminUser::pluck('username')->implode(', '));
        }

        return $user;
    }
}
