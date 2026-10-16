<?php

namespace App\Console\Commands;

use App\Support\AdminPath;
use Illuminate\Console\Command;

/**
 * Forgot the custom admin address? Run this on the server to get /admin back.
 */
class ResetAdminPath extends Command
{
    protected $signature = 'admin:reset-path';

    protected $description = 'Put the admin panel back at /admin (use when the custom admin address was forgotten)';

    public function handle(): int
    {
        $was = AdminPath::isCustom() ? '/'.AdminPath::current() : null;

        if (! AdminPath::reset()) {
            $this->warn('Could not delete the route cache — run "php artisan route:clear" too.');
        }

        $this->info($was ? "Admin address reset ({$was} → /admin)." : 'The admin address was already /admin.');
        $this->line('Login: '.AdminPath::url(AdminPath::DEFAULT).'/login');

        return self::SUCCESS;
    }
}
