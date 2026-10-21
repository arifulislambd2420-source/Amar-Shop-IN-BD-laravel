<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Widgets\CustomerStats;
use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\LowStockProducts;
use App\Filament\Widgets\DashboardDetails;
use App\Filament\Widgets\OrderStatusSummary;
use App\Filament\Widgets\PaymentDeliveryReport;
use App\Filament\Widgets\SalesChart;
use App\Filament\Widgets\SalesProfitReport;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TopProducts;
use App\Jobs\SendServerPurchase;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\DashboardReport;
use App\Support\ReportRange;
use App\Support\TrackingSettings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/**
 * The dashboard: one date filter (incl. custom dates), orders by status, REAL (confirmed) sales kept apart from
 * everything ordered and from cancelled, delivered sales, profit, best / slowest products, customers, payments,
 * and quick Confirm / Cancel / Delivered and stock buttons.
 */
class DashboardReportTest extends TestCase
{
    use CreatesShopData;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // 12:00 on 21 Oct 2026 in Dhaka (06:00 UTC) — mid-day, so "today" is unambiguous.
        Carbon::setTestNow(Carbon::parse('2026-10-21 06:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** An order of one product, set to a status / time / payment, without the observers (no SMS, no tracking). */
    private function order(string $status, float $price = 1000, array $at = ['2026-10-21 03:00:00'], array $extra = [], ?Product $product = null, int $qty = 1): Order
    {
        $product ??= $this->product(['price' => $price, 'cost_price' => 600, 'stock' => 100]);
        $this->n++;

        $order = $this->placeOrder($product, ['phone' => '01'.str_pad((string) (700000000 + $this->n), 9, '0'), 'customer_name' => "Customer {$this->n}"], $qty);
        $order->forceFill($extra + ['status' => $status, 'created_at' => $at[0], 'total' => $price * $qty + 70, 'shipping_fee' => 70])->saveQuietly();
        OrderItem::where('order_id', $order->id)->update(['unit_price' => $price, 'line_total' => $price * $qty]);

        return $order->fresh();
    }

    // ── the date filter ──────────────────────────────────────────────────

    public function test_every_range_covers_the_right_shop_days(): void
    {
        $utc = fn (ReportRange $r) => [$r->start()->format('Y-m-d H:i'), $r->end()->format('Y-m-d H:i')];

        // The shop's days are Dhaka days: 21 Oct starts at 20 Oct 18:00 UTC.
        $this->assertSame(['2026-10-20 18:00', '2026-10-21 18:00'], $utc(ReportRange::fromFilters(['range' => 'today'])));
        $this->assertSame(['2026-10-19 18:00', '2026-10-20 18:00'], $utc(ReportRange::fromFilters(['range' => 'yesterday'])));
        $this->assertSame(['2026-10-14 18:00', '2026-10-21 18:00'], $utc(ReportRange::fromFilters(['range' => 'last_7'])));
        $this->assertSame(['2026-09-21 18:00', '2026-10-21 18:00'], $utc(ReportRange::fromFilters(['range' => 'last_30'])));
        $this->assertSame(['2026-09-30 18:00', '2026-10-21 18:00'], $utc(ReportRange::fromFilters(['range' => 'this_month'])));

        $this->assertSame(1, ReportRange::fromFilters(['range' => 'today'])->days());
        $this->assertSame(7, ReportRange::fromFilters(['range' => 'last_7'])->days());
        $this->assertSame(30, ReportRange::fromFilters(['range' => 'last_30'])->days());
        $this->assertSame(21, ReportRange::fromFilters(['range' => 'this_month'])->days());
    }

    public function test_a_custom_range_takes_from_and_until_and_is_forgiving(): void
    {
        $custom = ReportRange::fromFilters(['range' => 'custom', 'from' => '2026-10-05', 'until' => '2026-10-09']);
        $this->assertSame(['2026-10-04 18:00', '2026-10-09 18:00'], [$custom->start()->format('Y-m-d H:i'), $custom->end()->format('Y-m-d H:i')]);
        $this->assertSame(5, $custom->days());
        $this->assertSame(['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09'], $custom->dates());
        $this->assertSame('05 Oct 2026 – 09 Oct 2026', $custom->label());

        // Swapped dates are put right; an empty "until" means one day; nothing at all means today.
        $this->assertSame(5, ReportRange::fromFilters(['range' => 'custom', 'from' => '2026-10-09', 'until' => '2026-10-05'])->days());
        $this->assertSame(1, ReportRange::fromFilters(['range' => 'custom', 'from' => '2026-10-05'])->days());
        $this->assertSame('21 Oct 2026', ReportRange::fromFilters(['range' => 'custom'])->label());
        $this->assertSame('21 Oct 2026', ReportRange::fromFilters(['range' => 'custom', 'from' => 'nonsense', 'until' => '###'])->label());

        // A typo cannot make the dashboard scan years.
        $this->assertSame(ReportRange::MAX_DAYS, ReportRange::fromFilters(['range' => 'custom', 'from' => '2015-01-01', 'until' => '2026-10-01'])->days());

        // Unknown or missing range = the default (today).
        $this->assertSame('today', ReportRange::fromFilters(['range' => 'banana'])->key);
        $this->assertSame('today', ReportRange::fromFilters(null)->key);
    }

    // ── real sales vs everything ordered ─────────────────────────────────

    public function test_real_sales_are_confirmed_orders_and_cancelled_ones_never_count(): void
    {
        $this->order('pending', 1000);
        $this->order('on_hold', 1000);
        $this->order('processing', 2000);
        $this->order('shipped', 3000);
        $this->order('delivered', 4000);
        $this->order('completed', 5000);
        $this->order('cancelled', 9000);

        $report = DashboardReport::make(['range' => 'today']);
        $counts = $report->statusCounts();

        $this->assertSame(1, $counts['pending']);
        $this->assertSame(1, $counts['on_hold']);
        $this->assertSame(1, $counts['processing']);
        $this->assertSame(1, $counts['shipped']);
        $this->assertSame(1, $counts['delivered']);
        $this->assertSame(1, $counts['completed']);
        $this->assertSame(1, $counts['cancelled']);
        $this->assertSame(0, $counts['out_for_delivery'], 'every status is listed, zero when none');

        $placed = $report->group(null);
        $this->assertSame(7, $placed['orders']);
        $this->assertEquals(25000 + 7 * 70, $placed['total']);

        // Real sales: confirmed and later — not pending, on hold or cancelled.
        $real = $report->group(DashboardReport::CONFIRMED);
        $this->assertSame(4, $real['orders']);
        $this->assertEquals(14000 + 4 * 70, $real['total']);

        // Delivered / completed on their own.
        $delivered = $report->group(DashboardReport::DELIVERED);
        $this->assertSame(2, $delivered['orders']);
        $this->assertEquals(9000 + 2 * 70, $delivered['total']);

        // Cancelled kept apart.
        $cancelled = $report->group(DashboardReport::CANCELLED);
        $this->assertSame(1, $cancelled['orders']);
        $this->assertEquals(9070, $cancelled['total']);
    }

    public function test_orders_outside_the_range_are_left_out_and_a_custom_range_finds_them(): void
    {
        $this->order('processing', 1000, ['2026-10-21 03:00:00']);      // today
        $this->order('processing', 2000, ['2026-10-15 03:00:00']);      // 6 days ago
        $this->order('processing', 4000, ['2026-09-01 03:00:00']);      // long ago

        $this->assertSame(1, DashboardReport::make(['range' => 'today'])->group(null)['orders']);
        $this->assertSame(2, DashboardReport::make(['range' => 'last_7'])->group(null)['orders']);
        $this->assertSame(2, DashboardReport::make(['range' => 'this_month'])->group(null)['orders']);
        $this->assertSame(3, DashboardReport::make(['range' => 'custom', 'from' => '2026-08-25', 'until' => '2026-10-21'])->group(null)['orders']);
        $this->assertSame(1, DashboardReport::make(['range' => 'custom', 'from' => '2026-09-01', 'until' => '2026-09-01'])->group(null)['orders']);
        $this->assertSame(0, DashboardReport::make(['range' => 'yesterday'])->group(null)['orders']);
    }

    // ── profit ───────────────────────────────────────────────────────────

    public function test_profit_is_sales_minus_product_cost_minus_the_shops_delivery_cost(): void
    {
        $this->setting('delivery_cost_per_order', '50');

        $a = $this->order('processing', 1000, qty: 2); // total 2070, product cost 2 × 600
        $this->order('pending', 1000);                  // not real sales: no profit counted
        $this->order('cancelled', 1000);

        $real = DashboardReport::make(['range' => 'today'])->group(DashboardReport::CONFIRMED);

        $this->assertSame(1, $real['orders']);
        $this->assertEquals(2070, $real['total']);
        $this->assertEquals(1200, $real['cost']);
        $this->assertEquals(50, $real['delivery_cost']);
        $this->assertEquals(2070 - 1200 - 50, $real['profit']);
        $this->assertSame(2, $real['qty']);
    }

    public function test_the_cost_is_kept_on_the_order_so_later_price_changes_do_not_rewrite_history(): void
    {
        $product = $this->product(['price' => 1000, 'cost_price' => 600, 'stock' => 50]);
        $order = $this->placeOrder($product);

        $this->assertEquals(600, $order->items()->first()->unit_cost, 'the cost is stored with the order line');

        $product->update(['cost_price' => 900]);
        $order->forceFill(['status' => 'processing'])->saveQuietly();
        Order::whereKey($order->id)->update(['created_at' => '2026-10-21 03:00:00']);

        $this->assertEquals(600, DashboardReport::make(['range' => 'today'])->group(DashboardReport::CONFIRMED)['cost']);
    }

    public function test_old_lines_without_a_stored_cost_use_the_products_cost_and_missing_costs_are_flagged(): void
    {
        $withCost = $this->product(['price' => 1000, 'cost_price' => 400, 'stock' => 50]);
        $noCost = $this->product(['price' => 1000, 'cost_price' => null, 'stock' => 50]);

        $a = $this->order('processing', 1000, product: $withCost);
        $b = $this->order('processing', 1000, product: $noCost);
        OrderItem::whereIn('order_id', [$a->id, $b->id])->update(['unit_cost' => null]); // lines from before the cost was kept

        $report = DashboardReport::make(['range' => 'today']);

        $this->assertEquals(400, $report->group(DashboardReport::CONFIRMED)['cost'], 'falls back to the current product cost');
        $this->assertSame(1, $report->itemsWithoutCost(), 'the product with no cost price at all is flagged');
    }

    // ── products, customers, payments, chart ─────────────────────────────

    public function test_best_and_slowest_sellers_count_confirmed_sales_only(): void
    {
        $honey = $this->product(['name' => 'Honey', 'price' => 500, 'stock' => 100]);
        $ghee = $this->product(['name' => 'Ghee', 'price' => 800, 'stock' => 100]);
        $dates = $this->product(['name' => 'Dates', 'price' => 300, 'stock' => 100]);
        $never = $this->product(['name' => 'Never sold', 'price' => 100, 'stock' => 100]);

        $this->order('processing', 500, product: $honey, qty: 3);
        $this->order('delivered', 500, product: $honey, qty: 2);
        $this->order('processing', 800, product: $ghee, qty: 1);
        $this->order('cancelled', 300, product: $dates, qty: 50);  // never counts
        $this->order('pending', 300, product: $dates, qty: 50);    // not confirmed yet

        $report = DashboardReport::make(['range' => 'today']);

        $best = $report->products(5, true);
        $this->assertSame(['Honey', 'Ghee'], array_column($best, 'name'));
        $this->assertSame([5, 1], array_column($best, 'qty'));
        $this->assertEquals(2500, $best[0]['revenue']);

        $slow = $report->products(5, false);
        $this->assertContains('Never sold', array_column($slow, 'name'), 'products that sold nothing are listed as slow');
        $this->assertContains('Dates', array_column($slow, 'name'));
        $this->assertSame('Honey', array_column($slow, 'name')[count($slow) - 1] ?? null, 'the best seller is last');
    }

    public function test_customers_are_told_apart_by_phone_and_new_and_repeat_are_counted(): void
    {
        $product = $this->product(['price' => 100, 'stock' => 500]);

        // Karim: ordered before today (so a repeat today). Rahim: first order today (new). Salma: two orders today (new and repeat).
        $this->order('delivered', 100, ['2026-10-01 03:00:00'], ['phone' => '01711111111'], $product);
        $this->order('processing', 100, ['2026-10-21 03:00:00'], ['phone' => '01711111111'], $product);
        $this->order('processing', 100, ['2026-10-21 04:00:00'], ['phone' => '01722222222'], $product);
        $this->order('processing', 100, ['2026-10-21 02:00:00'], ['phone' => '01733333333'], $product);
        $this->order('processing', 100, ['2026-10-21 03:30:00'], ['phone' => '01733333333'], $product);
        $this->order('cancelled', 100, ['2026-10-21 03:00:00'], ['phone' => '01744444444'], $product); // never counts

        $c = DashboardReport::make(['range' => 'today'])->customers();

        $this->assertSame(3, $c['total'], 'people who ordered — the one with only a cancelled order is not counted');
        $this->assertSame(3, $c['in_range']);
        $this->assertSame(2, $c['new']);     // Rahim, Salma
        $this->assertSame(2, $c['repeat']);  // Karim (earlier order), Salma (twice today)
    }

    public function test_payments_split_confirmed_sales_by_method_with_delivery_charges_and_cancelled(): void
    {
        $this->setting('delivery_cost_per_order', '60');

        $this->order('processing', 1000, extra: ['payment_method' => 'cod']);
        $this->order('shipped', 2000, extra: ['payment_method' => 'cod']);
        $this->order('processing', 3000, extra: ['payment_method' => 'bkash', 'payment_status' => 'paid']);
        $this->order('pending', 5000, extra: ['payment_method' => 'cod']);       // not confirmed
        $this->order('cancelled', 7000, extra: ['payment_method' => 'bkash']);

        $p = DashboardReport::make(['range' => 'today'])->payments();
        $by = collect($p['methods'])->keyBy('method');

        $this->assertSame(2, $by['cod']['orders']);
        $this->assertEquals(3000 + 140, $by['cod']['total']);
        $this->assertSame(1, $by['bkash']['orders']);
        $this->assertEquals(3070, $by['bkash']['total']);
        $this->assertEquals(3 * 70, $p['delivery_charge']);
        $this->assertEquals(3 * 60, $p['delivery_cost']);
        $this->assertSame(1, $p['cancelled_orders']);
        $this->assertEquals(7070, $p['cancelled_total']);
    }

    public function test_the_daily_series_separates_everything_ordered_from_real_sales_by_dhaka_day(): void
    {
        $this->order('processing', 1000, ['2026-10-21 03:00:00']);   // 21 Oct, confirmed
        $this->order('pending', 500, ['2026-10-21 05:00:00']);       // 21 Oct, waiting
        $this->order('cancelled', 800, ['2026-10-20 20:00:00']);     // 21 Oct 02:00 in Dhaka, cancelled
        $this->order('delivered', 2000, ['2026-10-20 10:00:00']);    // 20 Oct 16:00 in Dhaka

        $series = DashboardReport::make(['range' => 'last_7'])->series();

        $this->assertCount(7, $series);
        $this->assertEquals(['placed' => 1070 + 570 + 870, 'real' => 1070], $series['2026-10-21']);
        $this->assertEquals(['placed' => 2070, 'real' => 2070], $series['2026-10-20']);
        $this->assertEquals(['placed' => 0, 'real' => 0], $series['2026-10-19']);
    }

    // ── the widgets ──────────────────────────────────────────────────────

    private function admin(string $role = 'super_admin'): AdminUser
    {
        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        $this->setting('admin_locale', 'en');
        app()->setLocale('en');

        return tap(AdminUser::create(['username' => $role, 'password' => 'secret-pass-1', 'role' => $role]), fn ($a) => $this->actingAs($a, 'admin'));
    }

    private function seedMixed(): void
    {
        $this->order('pending', 1000);
        $this->order('processing', 2000, extra: ['payment_method' => 'cod']);
        $this->order('delivered', 3000, extra: ['payment_method' => 'bkash', 'payment_status' => 'paid']);
        $this->order('cancelled', 5000);
    }

    public function test_the_status_cards_show_each_status_and_follow_the_dates(): void
    {
        $this->admin();
        $this->seedMixed();
        $this->order('shipped', 700, ['2026-10-10 03:00:00']); // not today

        Livewire::test(OrderStatusSummary::class, ['pageFilters' => ['range' => 'today']])
            ->assertSee('Pending')->assertSee('Confirmed')->assertSee('Shipped')->assertSee('Delivered')->assertSee('Cancelled')
            ->assertSee('Out for delivery')->assertSee('21 Oct 2026')->assertDontSee('No orders in this period.');

        Livewire::test(OrderStatusSummary::class, ['pageFilters' => ['range' => 'custom', 'from' => '2026-01-01', 'until' => '2026-01-02']])
            ->assertSee('No orders in this period.');

        $today = DashboardReport::make(['range' => 'today'])->statusCounts();
        $month = DashboardReport::make(['range' => 'this_month'])->statusCounts();
        $this->assertSame(0, $today['shipped']);
        $this->assertSame(1, $month['shipped']);
    }

    public function test_the_kpi_cards_keep_real_sales_apart_from_orders_placed_and_cancelled(): void
    {
        $this->admin();
        $this->seedMixed();

        Livewire::test(StatsOverview::class, ['pageFilters' => ['range' => 'today']])
            ->assertSee("Today's orders")
            ->assertSee('Confirmed sales')
            ->assertSee('৳5,140')  // 2070 + 3070 (cancelled and waiting orders are not in it)
            ->assertDontSee('৳11,280')
            ->assertSee('Awaiting confirmation')
            ->assertSee('Estimated profit')
            ->assertSee('before');
    }

    public function test_the_report_table_widget_lists_every_group(): void
    {
        $this->admin();
        $this->seedMixed();

        Livewire::test(SalesProfitReport::class, ['pageFilters' => ['range' => 'today']])
            ->assertSee('Sales & profit report')
            ->assertSee('Confirmed sales (real sales)')
            ->assertSee('Delivered / completed sales')
            ->assertSee('Cancelled (not in sales)')
            ->assertSee('Waiting to be confirmed')
            ->assertSee('৳5,140');
    }

    public function test_the_other_report_widgets_render_with_data_and_with_none(): void
    {
        $this->admin();

        foreach ([TopProducts::class, CustomerStats::class, PaymentDeliveryReport::class, SalesChart::class, DashboardDetails::class, StatsOverview::class, OrderStatusSummary::class, SalesProfitReport::class] as $widget) {
            Livewire::test($widget, ['pageFilters' => ['range' => 'last_30']])->assertOk(); // no orders at all
        }

        $this->seedMixed();

        Livewire::test(TopProducts::class, ['pageFilters' => ['range' => 'today']])->assertSee('Best sellers')->assertSee('Slowest sellers');
        Livewire::test(CustomerStats::class, ['pageFilters' => ['range' => 'today']])->assertSee('Total customers')->assertSee('New customers')->assertSee('Repeat customers');
        Livewire::test(PaymentDeliveryReport::class, ['pageFilters' => ['range' => 'today']])->assertSee('Cash on delivery')->assertSee('bKash')->assertSee('Delivery charge paid by customers');
    }

    public function test_the_dashboard_page_has_the_date_filter_with_custom_dates(): void
    {
        $this->admin();

        $this->get('/admin')->assertOk()->assertSee('7 days')->assertSee('Custom');

        $page = Livewire::test(Dashboard::class)
            ->assertFormFieldExists('range', 'filtersForm')
            ->assertFormFieldIsHidden('from', 'filtersForm')
            ->assertFormFieldIsHidden('until', 'filtersForm');

        foreach (['today', 'yesterday', 'last_7', 'last_30', 'this_month', 'custom'] as $range) {
            $page->set('filters.range', $range)->assertHasNoErrors();
        }

        $page->set('filters.range', 'custom')
            ->assertFormFieldIsVisible('from', 'filtersForm')
            ->assertFormFieldIsVisible('until', 'filtersForm')
            ->set('filters.from', '2026-10-01')
            ->set('filters.until', '2026-10-15')
            ->assertSet('filters.from', '2026-10-01');
    }

    public function test_the_details_section_is_folded_and_opens_tab_by_tab(): void
    {
        $this->admin();
        $this->seedMixed();

        Livewire::test(DashboardDetails::class, ['pageFilters' => ['range' => 'today']])
            ->assertSee('Detailed reports')
            ->assertDontSee('Sales & profit report')
            ->call('toggle')
            ->assertSee('Sales & profit report')
            ->call('setTab', 'customers')
            ->assertSee('Total customers')
            ->call('setTab', 'nonsense')
            ->assertSet('tab', 'customers');
    }

    public function test_change_compares_with_the_period_before(): void
    {
        $this->assertSame(['pct' => 50, 'dir' => 'up'], DashboardReport::change(150, 100));
        $this->assertSame(['pct' => 25, 'dir' => 'down'], DashboardReport::change(75, 100));
        $this->assertSame(['pct' => null, 'dir' => 'up'], DashboardReport::change(5, 0));
        $this->assertSame(['pct' => null, 'dir' => 'flat'], DashboardReport::change(0, 0));
    }

    // ── quick actions ────────────────────────────────────────────────────

    public function test_confirm_cancel_and_delivered_work_from_the_dashboard_and_the_order_list(): void
    {
        $this->admin();
        $product = $this->product(['price' => 1000, 'stock' => 10]);
        $order = $this->placeOrder($product, quantity: 2);
        $this->assertSame(8, $product->fresh()->stock);

        // The right buttons for each status.
        Livewire::test(LatestOrders::class)
            ->assertActionVisible(TestAction::make('quickConfirm')->table($order))
            ->assertActionHidden(TestAction::make('quickDelivered')->table($order))
            ->assertActionVisible(TestAction::make('quickCancel')->table($order))
            ->callAction(TestAction::make('quickConfirm')->table($order))
            ->assertNotified();
        $this->assertSame('processing', $order->fresh()->status);

        Livewire::test(LatestOrders::class)
            ->assertActionHidden(TestAction::make('quickConfirm')->table($order->fresh()))
            ->assertActionVisible(TestAction::make('quickDelivered')->table($order->fresh()))
            ->callAction(TestAction::make('quickDelivered')->table($order->fresh()));
        $this->assertSame('delivered', $order->fresh()->status);

        // A delivered order can no longer be cancelled from the shortcut.
        Livewire::test(LatestOrders::class)->assertActionHidden(TestAction::make('quickCancel')->table($order->fresh()));

        // Cancelling (from the orders list this time) gives the stock back and takes the order out of real sales.
        $other = $this->placeOrder($product, ['phone' => '01899999999'], 3);
        $this->assertSame(5, $product->fresh()->stock);
        Livewire::test(ListOrders::class)->callAction(TestAction::make('quickCancel')->table($other));
        $this->assertSame('cancelled', $other->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_confirming_from_the_dashboard_sends_the_server_side_purchase_once(): void
    {
        $this->admin();
        $this->setting('meta_pixel_id', '123456789012345');
        TrackingSettings::putSecret(TrackingSettings::CAPI_TOKEN, 'EAAB-token-1234');
        Queue::fake();

        $order = $this->placeOrder();
        Queue::assertNothingPushed();

        Livewire::test(LatestOrders::class)->callAction(TestAction::make('quickConfirm')->table($order));

        Queue::assertPushed(SendServerPurchase::class, 1);
    }

    public function test_low_stock_products_can_be_restocked_from_the_dashboard(): void
    {
        $this->admin();
        $plain = $this->product(['name' => 'Plain', 'stock' => 2]);
        $sized = $this->product(['name' => 'Sized', 'stock' => 0]);
        ProductVariant::create(['product_id' => $sized->id, 'label' => 'M', 'price' => 100, 'stock' => 0]);

        Livewire::test(LowStockProducts::class)
            ->assertCanSeeTableRecords([$plain, $sized])
            ->assertActionVisible(TestAction::make('quickStock')->table($plain))
            ->assertActionHidden(TestAction::make('quickStock')->table($sized)); // sizes keep their stock in the variants

        Livewire::test(LowStockProducts::class)->callAction(TestAction::make('quickStock')->table($plain), ['stock' => -4])->assertHasFormErrors(['stock']);
        $this->assertSame(2, $plain->fresh()->stock);

        Livewire::test(LowStockProducts::class)->callAction(TestAction::make('quickStock')->table($plain), ['stock' => 40])->assertHasNoFormErrors();
        $this->assertSame(40, $plain->fresh()->stock);

        Livewire::test(LowStockProducts::class)->assertCanNotSeeTableRecords([$plain]);
    }

    // ── who sees what ────────────────────────────────────────────────────

    public function test_order_staff_see_the_status_cards_but_not_money_or_profit(): void
    {
        $this->admin('order_staff');

        $this->assertTrue(OrderStatusSummary::canView());
        $this->assertTrue(LatestOrders::canView());
        foreach ([StatsOverview::class, SalesProfitReport::class, TopProducts::class, CustomerStats::class, PaymentDeliveryReport::class, SalesChart::class, DashboardDetails::class] as $widget) {
            $this->assertFalse($widget::canView(), $widget);
        }

        $this->flushSession();
        $this->admin('manager');
        $this->assertTrue(SalesProfitReport::canView());
    }

    public function test_the_new_delivery_cost_setting_defaults_to_zero(): void
    {
        $this->assertSame(0.0, DashboardReport::deliveryCostPerOrder());
        $this->setting('delivery_cost_per_order', '65');
        $this->assertSame(65.0, DashboardReport::deliveryCostPerOrder());
        $this->setting('delivery_cost_per_order', 'abc');
        $this->assertSame(0.0, DashboardReport::deliveryCostPerOrder());
    }
}
