<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminArea;
use App\Support\AdminAccess;
use App\Support\AdminPath;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Change the address of the admin panel (default /admin). Super admins only.
 * Saving asks for confirmation that shows the exact new link, because anyone
 * who forgets it is locked out of the panel (the server command
 * `php artisan admin:reset-path` is the way back).
 */
class AdminAddress extends Page implements HasSchemas
{
    use HasAdminArea;
    use InteractsWithSchemas;

    protected static string $adminArea = AdminAccess::AREA_ADMINS;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'admin-address';

    protected string $view = 'filament.pages.admin-address';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('সেটিংস');
    }

    public static function getNavigationLabel(): string
    {
        return __('অ্যাডমিন লিংক');
    }

    public function getTitle(): string|Htmlable
    {
        return __('অ্যাডমিন লিংক');
    }

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['path' => AdminPath::current()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('অ্যাডমিন প্যানেলের লিংক'))
                    ->description(__('এখন: :url', ['url' => AdminPath::url()]))
                    ->schema([
                        TextInput::make('path')
                            ->label(__('নতুন পথ'))
                            ->prefix(url('/').'/')
                            ->required()
                            ->live(debounce: 400)
                            ->rules([fn () => function (string $attribute, $value, \Closure $fail): void {
                                if ($error = AdminPath::validate(trim((string) $value))) {
                                    $fail($error);
                                }
                            }])
                            ->helperText(__('শুধু ছোট হাতের ইংরেজি অক্ষর, সংখ্যা ও হাইফেন (যেমন my-panel-2026)। "admin" দিলে আগের /admin-এ ফিরবে।')),
                        Text::make(fn (): string => __('সেভ করলে লগইন লিংক হবে: :url', ['url' => AdminPath::url(trim((string) ($this->data['path'] ?? ''))).'/login']))
                            ->weight('bold'),
                    ]),
            ]);
    }

    public function saveAction(): Action
    {
        return Action::make('save')
            ->label(__('সেভ করুন'))
            ->size('lg')
            ->requiresConfirmation()
            ->modalHeading(__('অ্যাডমিন লিংক বদলাবেন?'))
            ->modalDescription(fn (): string => __('নতুন লগইন লিংক: :url — লিংকটি এখনই কোথাও লিখে রাখুন। এটি ভুলে গেলে প্যানেলে ঢুকতে পারবেন না। পুরনো লিংকে (:old) আর কিছু পাওয়া যাবে না (404)। ভুলে গেলে সার্ভারে "php artisan admin:reset-path" চালালে আবার /admin ফিরে আসবে।', [
                'url' => AdminPath::url(trim((string) ($this->data['path'] ?? ''))).'/login',
                'old' => AdminPath::url(),
            ]))
            ->modalSubmitActionLabel(__('হ্যাঁ, লিংক বদলান'))
            ->action(fn () => $this->save());
    }

    public function save(): void
    {
        $this->data['path'] = trim((string) ($this->data['path'] ?? ''));
        $path = $this->form->getState()['path'];

        if ($path === AdminPath::current()) {
            Notification::make()->title(__('লিংক আগের মতোই আছে।'))->send();

            return;
        }

        $cleared = AdminPath::set($path);

        if (! $cleared) {
            Notification::make()->title(__('রুট ক্যাশ মোছা যায়নি — সার্ভারে "php artisan route:clear" চালান।'))->warning()->persistent()->send();
        }

        Notification::make()->title(__('অ্যাডমিন লিংক বদলেছে: :url', ['url' => AdminPath::url($path)]))->success()->persistent()->send();

        $this->redirect(AdminPath::url($path), navigate: false);
    }
}
