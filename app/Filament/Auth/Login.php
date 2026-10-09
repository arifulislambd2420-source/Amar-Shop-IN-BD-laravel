<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Custom Filament login page for the admin panel.
 *
 * Admins authenticate with a `username` (the admin_users table has no email
 * column), so we swap Filament's default e-mail field for a username field and
 * build the guard credentials from it.
 */
class Login extends BaseLogin
{
    /**
     * Replace the default e-mail field with a username field.
     */
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('ইউজারনেম')
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    /**
     * Build the credentials passed to Auth::guard('admin')->attempt().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'username' => $data['username'],
            'password' => $data['password'],
        ];
    }

    /**
     * Point the "failed login" validation error at the username field.
     */
    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
