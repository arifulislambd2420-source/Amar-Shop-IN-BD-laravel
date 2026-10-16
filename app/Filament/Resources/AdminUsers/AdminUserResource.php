<?php

namespace App\Filament\Resources\AdminUsers;

use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\AdminUsers\Pages\ManageAdminUsers;
use App\Models\AdminUser;
use App\Support\AdminAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

/**
 * Admin users and their roles (super admin only): add, edit, switch off, reset a
 * password, delete. The last active super admin can never be deleted, deactivated
 * or demoted (also enforced in the AdminUser model), and nobody can delete or
 * deactivate their own account.
 */
class AdminUserResource extends Resource
{
    use HasAdminArea;

    protected static string $adminArea = AdminAccess::AREA_ADMINS;

    protected static ?string $model = AdminUser::class;

    public static function getPluralModelLabel(): string
    {

        return __('অ্যাডমিন ব্যবহারকারী');

    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    public static function getNavigationGroup(): string|UnitEnum|null
    {

        return __('সেটিংস');

    }

    protected static ?int $navigationSort = 11;

    public static function getNavigationLabel(): string
    {

        return __('অ্যাডমিন ব্যবহারকারী');

    }

    public static function getModelLabel(): string
    {

        return Str::singular(__('অ্যাডমিন ব্যবহারকারী'));

    }

    protected static ?string $recordTitleAttribute = 'username';

    /** Fields that would lock the shop out (role, active) are frozen for yourself and for the last super admin. */
    private static function isProtected(?AdminUser $record): bool
    {
        return $record !== null && ($record->is(auth('admin')->user()) || $record->isLastActiveSuperAdmin());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('username')
                    ->label(__('ইউজারনেম (লগইনের নাম)'))
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label(__('নাম'))
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('ইমেইল'))
                    ->email()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText(__('ঐচ্ছিক। দিলে ইউজারনেমের বদলে ইমেইল দিয়েও লগইন করা যাবে।')),
                TextInput::make('password')
                    ->label(__('পাসওয়ার্ড'))
                    ->password()
                    ->revealable()
                    ->rule(Password::min(8))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): string => $operation === 'create'
                        ? __('ন্যূনতম ৮ অক্ষর।')
                        : __('এডিটের সময় খালি রাখলে পাসওয়ার্ড বদলাবে না। (আলাদা "পাসওয়ার্ড রিসেট" বাটনও আছে।)')),
                Select::make('role')
                    ->label(__('রোল'))
                    ->options(array_map('__', AdminUser::ROLE_OPTIONS))
                    ->default(AdminUser::ROLE_ORDER_STAFF)
                    ->required()
                    ->native(false)
                    ->disabled(fn (?AdminUser $record): bool => self::isProtected($record))
                    ->helperText(fn (?AdminUser $record): ?string => self::isProtected($record) ? __('নিজের বা শেষ সুপার অ্যাডমিনের রোল বদলানো যায় না।') : null),
                Toggle::make('is_active')
                    ->label(__('সক্রিয় (লগইন করতে পারবে)'))
                    ->default(true)
                    ->disabled(fn (?AdminUser $record): bool => self::isProtected($record))
                    ->helperText(fn (?AdminUser $record): ?string => self::isProtected($record) ? __('নিজেকে বা শেষ সুপার অ্যাডমিনকে নিষ্ক্রিয় করা যায় না।') : null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')
                    ->label(__('ইউজারনেম (লগইনের নাম)'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('নাম'))
                    ->searchable()
                    ->placeholder('—')
                    ->visibleFrom('md'),
                TextColumn::make('email')
                    ->label(__('ইমেইল'))
                    ->searchable()
                    ->placeholder('—')
                    ->visibleFrom('lg'),
                TextColumn::make('role')
                    ->label(__('রোল'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => __(AdminUser::ROLE_OPTIONS[$state] ?? (string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        AdminUser::ROLE_SUPER_ADMIN => 'danger',
                        AdminUser::ROLE_MANAGER => 'warning',
                        default => 'info',
                    }),
                IconColumn::make('is_active')
                    ->label(__('সক্রিয়'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                Action::make('resetPassword')
                    ->label(__('পাসওয়ার্ড রিসেট'))
                    ->icon(Heroicon::OutlinedKey)
                    ->iconButton()
                    ->color('warning')
                    ->modalHeading(fn (AdminUser $record): string => __('":user"-এর নতুন পাসওয়ার্ড', ['user' => $record->username]))
                    ->modalDescription(__('নতুন পাসওয়ার্ড সেট হলে তিনি অন্য সব জায়গা থেকে লগআউট হবেন। পাসওয়ার্ডটি তাকে নিরাপদে জানিয়ে দিন।'))
                    ->modalSubmitActionLabel(__('পাসওয়ার্ড বদলান'))
                    ->schema([
                        TextInput::make('password')
                            ->label(__('নতুন পাসওয়ার্ড'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::min(8))
                            ->same('passwordConfirmation')
                            ->helperText(__('ন্যূনতম ৮ অক্ষর।')),
                        TextInput::make('passwordConfirmation')
                            ->label(__('নতুন পাসওয়ার্ড আবার'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->dehydrated(false),
                    ])
                    ->action(function (AdminUser $record, array $data): void {
                        $record->update(['password' => $data['password'], 'remember_token' => Str::random(60)]);

                        Notification::make()->title(__('":user"-এর পাসওয়ার্ড বদলেছে।', ['user' => $record->username]))->success()->send();
                    }),
                Action::make('toggleActive')
                    ->label(fn (AdminUser $record): string => $record->is_active ? __('নিষ্ক্রিয় করুন') : __('সক্রিয় করুন'))
                    ->icon(fn (AdminUser $record) => $record->is_active ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
                    ->iconButton()
                    ->color(fn (AdminUser $record): string => $record->is_active ? 'gray' : 'success')
                    ->visible(fn (AdminUser $record): bool => ! self::isProtected($record) || ! $record->is_active)
                    ->requiresConfirmation()
                    ->modalDescription(fn (AdminUser $record): string => $record->is_active
                        ? __('নিষ্ক্রিয় করলে তিনি আর লগইন করতে পারবেন না, লগইন থাকলেও পরের ক্লিকেই বের হয়ে যাবেন। তার তথ্য থেকে যাবে।')
                        : __('সক্রিয় করলে তিনি আবার লগইন করতে পারবেন।'))
                    ->action(function (AdminUser $record): void {
                        try {
                            $record->update(['is_active' => ! $record->is_active]);
                        } catch (\DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title($record->is_active ? __('সক্রিয় করা হয়েছে।') : __('নিষ্ক্রিয় করা হয়েছে।'))->success()->send();
                    }),
                DeleteAction::make()
                    ->iconButton()
                    ->hidden(fn (AdminUser $record): bool => self::isProtected($record)),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }

    /** Only the last *active* super admin counts: if it were gone, nobody could manage the shop. */
    public static function isLastSuperAdmin(AdminUser $user): bool
    {
        return $user->isLastActiveSuperAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof AdminUser && ! self::isProtected($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAdminUsers::route('/'),
        ];
    }
}
