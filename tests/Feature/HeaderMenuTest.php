<?php

namespace Tests\Feature;

use App\Models\Category;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/** The storefront menu is in English, and the phone drawer lists no categories. */
class HeaderMenuTest extends TestCase
{
    use CreatesShopData;

    public function test_the_header_menu_is_in_english(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['Home', 'Offers', 'Shop', 'Brands', 'Contact', 'Track order'] as $label) {
            $this->assertStringContainsString('>'.$label.'</a>', $html, $label);
        }

        $this->assertStringNotContainsString('>হোম</a>', $html);
        $this->assertStringNotContainsString('>অফার</a>', $html);
        $this->assertStringNotContainsString('>যোগাযোগ</a>', $html);
    }

    public function test_the_phone_drawer_has_no_category_list(): void
    {
        $category = Category::create(['name' => 'ড্রয়ারে-থাকবে-না', 'slug' => 'drawer-cat']);
        $this->product(['category_id' => $category->id]);

        $html = $this->get('/')->assertOk()->getContent();

        // The drawer is the part of the page from #mobile-drawer on.
        $drawer = substr($html, strpos($html, 'id="mobile-drawer"'));
        $drawer = substr($drawer, 0, strpos($drawer, '</nav>') ?: null);

        $this->assertStringContainsString('Track order', $drawer);
        $this->assertStringNotContainsString('ড্রয়ারে-থাকবে-না', $drawer);
        $this->assertStringNotContainsString('category=drawer-cat', $drawer);
    }
}
