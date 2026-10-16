<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\ContactSocialSettings;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Brands\Pages\ManageBrands;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Resources\LandingPages\Pages\CreateLandingPage;
use App\Filament\Resources\LandingPages\Pages\EditLandingPage;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\AdminUser;
use App\Models\Brand;
use App\Models\Category;
use App\Models\LandingPage;
use App\Models\ProductVariant;
use App\Models\SiteSetting;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ManageRecords;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/**
 * A sweep over the whole admin (English, the default): every list searches and
 * filters without error, records can be created, edited, saved and deleted, and
 * the variant (colour / size / picture) manager works. Needs intl, like Filament.
 */
class AdminSweepTest extends TestCase
{
    use CreatesShopData;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament needs the intl PHP extension.');
        }

        $this->actingAs(AdminUser::create(['username' => 'boss', 'password' => 'secret-pass-1', 'role' => 'super_admin']), 'admin');
        app()->setLocale('en');
    }

    public function test_every_list_searches_and_every_filter_runs(): void
    {
        $product = $this->product(['name' => 'Sweep product']);
        $this->placeOrder($product);
        $checked = 0;

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            $index = $resource::getPages()['index']->getPage();

            if (! is_subclass_of($index, ListRecords::class) && ! is_subclass_of($index, ManageRecords::class)) {
                continue;
            }

            $component = Livewire::test($index)->assertOk();
            $component->searchTable('zzz-no-such-thing')->assertOk()->searchTable('');

            foreach ($component->instance()->getTable()->getFilters() as $name => $filter) {
                if ($filter instanceof SelectFilter) {
                    $value = array_key_first($filter->getOptions());
                    if ($value !== null) {
                        $component->filterTable($name, $value)->assertOk();
                    }
                } elseif ($filter instanceof TernaryFilter) {
                    $component->filterTable($name, true)->assertOk()->filterTable($name, false)->assertOk();
                }

                $component->resetTableFilters()->assertOk();
                $checked++;
            }
        }

        $this->assertGreaterThan(8, $checked, 'filters were exercised');
    }

    public function test_category_and_brand_create_edit_delete(): void
    {
        Livewire::test(ManageCategories::class)
            ->callAction('create', ['name' => 'Shoes', 'slug' => 'shoes'])->assertHasNoFormErrors();
        $category = Category::where('slug', 'shoes')->firstOrFail();

        Livewire::test(ManageCategories::class)
            ->callAction(TestAction::make('edit')->table($category), ['name' => 'Footwear'])->assertHasNoFormErrors();
        $this->assertSame('Footwear', $category->fresh()->name);

        Livewire::test(ManageCategories::class)->callAction(TestAction::make('delete')->table($category));
        $this->assertNull(Category::find($category->id));

        Livewire::test(ManageBrands::class)->callAction('create', ['name' => 'Acme'])->assertHasNoFormErrors();
        $brand = Brand::where('name', 'Acme')->firstOrFail();
        Livewire::test(ManageBrands::class)->callAction(TestAction::make('delete')->table($brand));
        $this->assertNull(Brand::find($brand->id));
    }

    public function test_variants_with_colour_size_and_picture_can_be_added_edited_and_deleted_and_the_name_follows(): void
    {
        $product = $this->product(['name' => 'Variant host', 'stock' => 0]);
        $manager = fn () => Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class]);

        $manager()->callAction(TestAction::make('create')->table(), [
            'color' => 'Red', 'color_hex' => '#ff0000', 'size' => 'M', 'price' => 450, 'stock' => 7, 'image' => ['/storage/media/red.jpg'],
        ])->assertHasNoFormErrors();

        $variant = ProductVariant::where('product_id', $product->id)->firstOrFail();
        $this->assertSame('Red / M', $variant->label);
        $this->assertSame('/storage/media/red.jpg', $variant->image);
        $this->assertSame(7, $product->fresh()->stock);

        // An old-style variant (only a name) is still fine.
        $manager()->callAction(TestAction::make('create')->table(), ['label' => 'XL', 'price' => 500, 'stock' => 2])->assertHasNoFormErrors();
        $this->assertSame(9, $product->fresh()->stock);

        // Neither a name nor a colour/size is refused.
        $manager()->callAction(TestAction::make('create')->table(), ['price' => 500, 'stock' => 2])->assertHasFormErrors(['label']);

        $manager()->callAction(TestAction::make('edit')->table($variant), ['size' => 'L', 'stock' => 3])->assertHasNoFormErrors();
        $this->assertSame('Red / L', $variant->fresh()->label);
        $this->assertSame(5, $product->fresh()->stock);

        $manager()->callAction(TestAction::make('delete')->table($variant));
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_a_landing_page_with_the_new_blocks_can_be_created_edited_and_deleted(): void
    {
        $product = $this->product(['name' => 'LP host']);

        Livewire::test(CreateLandingPage::class)
            ->fillForm([
                'title' => 'Sweep page', 'slug' => 'sweep-page', 'template' => 'purple', 'product_id' => $product->id,
                'is_active' => true, 'headline' => 'Sweep', 'button_text' => 'Order now',
                'blocks' => [
                    ['type' => 'hero', 'data' => ['headline' => 'Big hero', 'hidden' => false]],
                    ['type' => 'price', 'data' => ['label' => 'Offer', 'hidden' => false]],
                    ['type' => 'models', 'data' => ['heading' => 'Pick one', 'hidden' => false]],
                    ['type' => 'trust', 'data' => ['heading' => 'Why us', 'items' => [['icon' => '🛡️', 'title' => 'Guarantee']], 'hidden' => false]],
                    ['type' => 'order_form', 'data' => ['heading' => 'Order below', 'hidden' => false]],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = LandingPage::where('slug', 'sweep-page')->firstOrFail();
        $this->assertSame(['hero', 'price', 'models', 'trust', 'order_form'], array_column($page->blocks, 'type'));
        $this->get('/lp/sweep-page')->assertOk()->assertSee('Why us')->assertSee('Order below');

        Livewire::test(EditLandingPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['title' => 'Sweep page 2', 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Sweep page 2', $page->fresh()->title);
        $this->get('/lp/sweep-page')->assertNotFound();

        Livewire::test(EditLandingPage::class, ['record' => $page->getRouteKey()])
            ->callAction('delete');
        $this->assertNull(LandingPage::find($page->id));
    }

    public function test_site_setting_saves_and_the_storefront_follows(): void
    {
        Livewire::test(SiteSettings::class)->set('data.site_name', 'Sweep Shop')->call('save');
        Livewire::test(ContactSocialSettings::class)->set('data.contact_phone', '01712345678')->call('save');
        $this->assertSame('01712345678', SiteSetting::where('setting_key', 'contact_phone')->value('setting_value'));

        $this->assertSame('Sweep Shop', SiteSetting::where('setting_key', 'site_name')->value('setting_value'));
        $this->get('/')->assertOk()->assertSee('Sweep Shop');
    }
}
