<?php

namespace App\Filament\Resources\AdminUsers;

use App\Filament\Concerns\HasAdminArea;
use App\Filament\Resources\AdminUsers\Pages\ManageAdminUsers;
use App\Models\AdminUser;
use App\Support\AdminAccess;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Admin users and their roles (super admin only). The last super admin can
 * never be demoted or deleted, and nobody can delete their own account.
 */
class AdminUserResource extends Resource
{
    use HasAdminArea;

    protected static string $adminArea = AdminAccess::AREA_ADMINS;

    protected static ?string $model = AdminUser::class;

    protected static ?string $pluralModelLabel = 'অ্যাডমিন ব্যবহারকারী';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationLabel = 'অ্যাডমিন ব্যবহারকারী';

    protected static ?string $modelLabel = 'অ্যাডমিন ব্যবহারকারী';

    protected static ?string $recordTitleAttribute = 'username';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('username')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('এডিটের সময় খালি রাখলে পাসওয়ার্ড বদলাবে না।'),
                Select::make('role')
                    ->label('রোল')
                    ->options(AdminUser::ROLE_OPTIONS)
                    ->default(AdminUser::ROLE_ORDER_STAFF)
                    ->required()
                    ->native(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label('রোল')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => AdminUser::ROLE_OPTIONS[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        AdminUser::ROLE_SUPER_ADMIN => 'danger',
                        AdminUser::ROLE_MANAGER => 'warning',
                        default => 'info',
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (Model $record, array $data): Model {
                        if (($data['role'] ?? $record->role) !== AdminUser::ROLE_SUPER_ADMIN && self::isLastSuperAdmin($record)) {
                            Notification::make()->title('শেষ সুপার অ্যাডমিনের রোল বদলানো যাবে না।')->danger()->send();

                            return $record;
                        }

                        $record->update($data);

                        return $record;
                    }),
                DeleteAction::make()
                    ->hidden(fn (AdminUser $record): bool => $record->is(auth('admin')->user()) || self::isLastSuperAdmin($record)),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }

    public static function isLastSuperAdmin(AdminUser $user): bool
    {
        return $user->isSuperAdmin()
            && AdminUser::where('role', AdminUser::ROLE_SUPER_ADMIN)->where('id', '!=', $user->id)->doesntExist();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAdminUsers::route('/'),
        ];
    }
}
