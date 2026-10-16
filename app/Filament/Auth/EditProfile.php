<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use SensitiveParameter;

/**
 * "My profile" for every admin (Filament's user menu): display name, e-mail and
 * a password change that needs the old password, the new one twice, 8+ characters.
 * The username is the sign-in name and only a super admin can change it (Admin users).
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('আমার তথ্য'))
                ->schema([
                    $this->getNameFormComponent(),
                    $this->getEmailFormComponent(),
                ]),
            Section::make(__('পাসওয়ার্ড বদলান'))
                ->description(__('বদলাতে না চাইলে নিচের ঘরগুলো খালি রাখুন।'))
                ->schema([
                    $this->getCurrentPasswordFormComponent(),
                    $this->getPasswordFormComponent(),
                    $this->getPasswordConfirmationFormComponent(),
                ]),
        ]);
    }

    protected function getNameFormComponent(): Component
    {
        return TextInput::make('name')
            ->label(__('নাম'))
            ->maxLength(255);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('ইমেইল'))
            ->email()
            ->maxLength(255)
            ->unique(ignoreRecord: true)
            ->live(debounce: 500);
    }

    /** Asked whenever a new password (or a new e-mail) is being set — and then it must be right. */
    protected function getCurrentPasswordFormComponent(): Component
    {
        return TextInput::make('currentPassword')
            ->label(__('পুরনো পাসওয়ার্ড'))
            ->validationAttribute(__('পুরনো পাসওয়ার্ড'))
            ->password()
            ->revealable()
            ->autocomplete('current-password')
            ->currentPassword(guard: Filament::getAuthGuard())
            ->required(fn (Get $get): bool => filled($get('password')) || ($get('email') ?: null) !== ($this->getUser()->getAttributeValue('email') ?: null))
            ->dehydrated(false);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('নতুন পাসওয়ার্ড'))
            ->validationAttribute(__('নতুন পাসওয়ার্ড'))
            ->password()
            ->revealable()
            ->rule(Password::min(8))
            ->helperText(__('ন্যূনতম ৮ অক্ষর।'))
            ->autocomplete('new-password')
            ->dehydrated(fn (#[SensitiveParameter] $state): bool => filled($state))
            // Hashed here (not only by the model) because the page stores this hash in the session
            // so the admin stays signed in after changing their own password.
            ->dehydrateStateUsing(fn (#[SensitiveParameter] $state): string => Hash::make($state))
            ->live(debounce: 500)
            ->same('passwordConfirmation');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label(__('নতুন পাসওয়ার্ড আবার'))
            ->validationAttribute(__('নতুন পাসওয়ার্ড আবার'))
            ->password()
            ->revealable()
            ->autocomplete('new-password')
            ->required(fn (Get $get): bool => filled($get('password')))
            ->dehydrated(false);
    }
}
