<?php

namespace Tests\Feature;

use App\Filament\Resources\LandingPages\Pages\ListLandingPages;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\AdminUser;
use App\Models\LandingPage;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/** Admin pages use the whole screen, and table rows keep their extra buttons in one "⋯" menu. */
class AdminTablesTest extends TestCase
{
    use CreatesShopData;

    private function admin(): AdminUser
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        $this->setting('admin_locale', 'en');
        app()->setLocale('en');

        return tap(AdminUser::create(['username' => 'tables', 'name' => 'Tables Tester', 'password' => 'secret-pass-1', 'role' => AdminUser::ROLE_SUPER_ADMIN]), fn ($a) => $this->actingAs($a, 'admin'));
    }

    public function test_admin_pages_use_the_full_width(): void
    {
        $this->admin();

        $html = $this->get('/admin/orders')->assertOk()->getContent();

        $this->assertStringContainsString('fi-width-full', $html);
        $this->assertStringNotContainsString('fi-width-7xl', $html);
    }

    public function test_order_row_actions_live_in_a_menu_and_still_work(): void
    {
        $this->admin();
        $order = $this->placeOrder($this->product(['price' => 500, 'stock' => 5]));

        Livewire::test(ListOrders::class)
            ->assertTableActionExists('view')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('quickConfirm', record: $order)
            ->assertTableActionExists('quickCancel', record: $order)
            ->callTableAction('quickConfirm', $order)
            ->assertNotified();

        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_landing_page_preview_copy_and_delete_are_in_the_menu(): void
    {
        $this->admin();
        $page = LandingPage::create(['title' => 'Test page', 'slug' => 'test-page', 'headline' => 'Headline', 'template' => 'cream', 'is_active' => true, 'blocks' => []]);

        Livewire::test(ListLandingPages::class)
            ->assertTableActionExists('edit')
            ->assertTableActionExists('preview')
            ->assertTableActionExists('duplicate')
            ->assertTableActionExists('delete')
            ->callTableAction('delete', $page);

        $this->assertDatabaseMissing('landing_pages', ['slug' => 'test-page']);
    }

    public function test_less_important_columns_can_be_switched_off(): void
    {
        $this->admin();

        Livewire::test(ListOrders::class)
            ->assertTableColumnExists('phone')
            ->assertTableColumnExists('payment_status')
            ->assertTableColumnExists('created_at');

        $toggleable = collect(Livewire::test(ListOrders::class)->instance()->getTable()->getColumns())
            ->filter(fn ($c) => $c->isToggleable())->keys()->all();

        $this->assertContains('phone', $toggleable);
        $this->assertContains('payment_status', $toggleable);
        $this->assertContains('created_at', $toggleable);
    }
}
