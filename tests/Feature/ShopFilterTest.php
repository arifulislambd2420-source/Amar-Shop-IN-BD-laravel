<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ShopFilters;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/** Shop / offers filters: several categories, brands, colours, sizes, a price range and "in stock". */
class ShopFilterTest extends TestCase
{
    use CreatesShopData;

    private function variant(Product $p, array $attrs): ProductVariant
    {
        return ProductVariant::create($attrs + ['product_id' => $p->id, 'price' => (float) $p->price, 'stock' => 5]);
    }

    private function sees(string $url, array $present, array $absent = []): void
    {
        $r = $this->get($url)->assertOk();

        foreach ($present as $name) {
            $r->assertSee($name);
        }
        foreach ($absent as $name) {
            $r->assertDontSee($name);
        }
    }

    public function test_several_categories_at_once_and_the_old_single_link_still_works(): void
    {
        $a = Category::create(['name' => 'ক-ক্যাট', 'slug' => 'cat-a']);
        $b = Category::create(['name' => 'খ-ক্যাট', 'slug' => 'cat-b']);
        $c = Category::create(['name' => 'গ-ক্যাট', 'slug' => 'cat-c']);
        $this->product(['name' => 'পণ্য-এ', 'category_id' => $a->id]);
        $this->product(['name' => 'পণ্য-বি', 'category_id' => $b->id]);
        $this->product(['name' => 'পণ্য-সি', 'category_id' => $c->id]);

        $this->sees('/shop?category[]=cat-a&category[]=cat-b', ['পণ্য-এ', 'পণ্য-বি'], ['পণ্য-সি']);
        $this->sees('/shop?category=cat-c', ['পণ্য-সি'], ['পণ্য-এ', 'পণ্য-বি']);
    }

    public function test_several_brands_at_once(): void
    {
        $x = Brand::create(['name' => 'ব্র্যান্ড-এক্স']);
        $y = Brand::create(['name' => 'ব্র্যান্ড-ওয়াই']);
        $this->product(['name' => 'জিনিস-এক্স', 'brand_id' => $x->id]);
        $this->product(['name' => 'জিনিস-ওয়াই', 'brand_id' => $y->id]);
        $this->product(['name' => 'জিনিস-ছাড়া']);

        $this->sees("/shop?brand[]={$x->id}&brand[]={$y->id}", ['জিনিস-এক্স', 'জিনিস-ওয়াই'], ['জিনিস-ছাড়া']);
        $this->sees("/shop?brand={$x->id}", ['জিনিস-এক্স'], ['জিনিস-ওয়াই']);
    }

    public function test_colour_and_size_must_match_the_same_variant(): void
    {
        $both = $this->product(['name' => 'লাল-এম-আছে']);
        $this->variant($both, ['color' => 'Red', 'size' => 'M']);
        $this->variant($both, ['color' => 'Blue', 'size' => 'L']);

        $split = $this->product(['name' => 'আলাদা-লাল-এল']);
        $this->variant($split, ['color' => 'Red', 'size' => 'L']);
        $this->variant($split, ['color' => 'Blue', 'size' => 'M']);

        $this->sees('/shop?color[]=Red&size[]=M', ['লাল-এম-আছে'], ['আলাদা-লাল-এল']);
        $this->sees('/shop?color[]=Red', ['লাল-এম-আছে', 'আলাদা-লাল-এল']);
        $this->sees('/shop?color[]=Red&color[]=Blue&size[]=L', ['লাল-এম-আছে', 'আলাদা-লাল-এল']);
    }

    public function test_older_size_only_products_are_found_by_size(): void
    {
        $old = $this->product(['name' => 'পুরনো-শার্ট']);
        $this->variant($old, ['label' => 'M']);
        $this->variant($old, ['label' => 'L']);
        $other = $this->product(['name' => 'অন্য-শার্ট']);
        $this->variant($other, ['label' => 'XL']);

        $this->sees('/shop?size[]=M', ['পুরনো-শার্ট'], ['অন্য-শার্ট']);
        // …and the sizes are offered in the panel, in a sensible order.
        $html = $this->get('/shop')->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'value="L"'), strpos($html, 'value="M"'));
        $this->assertLessThan(strpos($html, 'value="XL"'), strpos($html, 'value="L"'));
    }

    public function test_price_range_uses_the_selling_price_and_a_reversed_range_is_fixed(): void
    {
        $this->product(['name' => 'সস্তা-জিনিস', 'price' => 200]);
        $this->product(['name' => 'ছাড়ে-মাঝারি', 'price' => 900, 'sale_price' => 450]);
        $this->product(['name' => 'দামি-জিনিস', 'price' => 2000]);

        $this->sees('/shop?price_min=300&price_max=1000', ['ছাড়ে-মাঝারি'], ['সস্তা-জিনিস', 'দামি-জিনিস']);
        $this->sees('/shop?price_min=1000', ['দামি-জিনিস'], ['সস্তা-জিনিস', 'ছাড়ে-মাঝারি']);
        $this->sees('/shop?price_max=300', ['সস্তা-জিনিস'], ['দামি-জিনিস']);
        $this->sees('/shop?price_min=1000&price_max=300', ['ছাড়ে-মাঝারি'], ['সস্তা-জিনিস', 'দামি-জিনিস']); // read as 300–1000
    }

    public function test_in_stock_hides_sold_out_products_and_sold_out_colours(): void
    {
        $this->product(['name' => 'স্টকে-আছে-সাধারণ', 'stock' => 4]);
        $this->product(['name' => 'স্টক-নেই-সাধারণ', 'stock' => 0]);

        $withStock = $this->product(['name' => 'ভ্যারিয়েন্ট-স্টকে', 'stock' => 0]);
        $this->variant($withStock, ['color' => 'Red', 'stock' => 3]);
        $gone = $this->product(['name' => 'ভ্যারিয়েন্ট-শেষ', 'stock' => 0]);
        $this->variant($gone, ['color' => 'Red', 'stock' => 0]);

        $this->sees('/shop?in_stock=1', ['স্টকে-আছে-সাধারণ', 'ভ্যারিয়েন্ট-স্টকে'], ['স্টক-নেই-সাধারণ', 'ভ্যারিয়েন্ট-শেষ']);
        // With a colour ticked, that colour itself has to be in stock.
        $this->sees('/shop?in_stock=1&color[]=Red', ['ভ্যারিয়েন্ট-স্টকে'], ['ভ্যারিয়েন্ট-শেষ']);
        $this->sees('/shop?color[]=Red', ['ভ্যারিয়েন্ট-স্টকে', 'ভ্যারিয়েন্ট-শেষ']);
    }

    public function test_offers_page_has_the_same_filters_and_offers_only_choices_from_its_own_products(): void
    {
        $sale = $this->product(['name' => 'অফারের-জিনিস', 'price' => 1000, 'sale_price' => 700]);
        $this->variant($sale, ['color' => 'Gold']);
        $full = $this->product(['name' => 'পুরো-দামের', 'price' => 500]);
        $this->variant($full, ['color' => 'Hidden-Colour']);

        $this->sees('/offers', ['অফারের-জিনিস'], ['পুরো-দামের']);
        $this->get('/offers')->assertSee('Gold')->assertDontSee('Hidden-Colour');
        $this->sees('/offers?color[]=Gold', ['অফারের-জিনিস']);
        $this->sees('/offers?color[]=Hidden-Colour', [], ['অফারের-জিনিস', 'পুরো-দামের']);
    }

    public function test_the_panel_lists_each_filter_and_marks_what_is_ticked(): void
    {
        $cat = Category::create(['name' => 'প্যানেল-ক্যাট', 'slug' => 'panel-cat']);
        $p = $this->product(['name' => 'প্যানেল-পণ্য', 'category_id' => $cat->id, 'price' => 800]);
        $this->variant($p, ['color' => 'Teal', 'color_hex' => '#008080', 'size' => 'M']);

        $html = $this->get('/shop?category[]=panel-cat&color[]=Teal&size[]=M&in_stock=1&price_min=100')->assertOk()->getContent();

        foreach (['name="category[]"', 'name="color[]"', 'name="size[]"', 'name="price_min"', 'name="price_max"', 'name="in_stock"', 'background: #008080'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }

        $this->assertMatchesRegularExpression('/name="category\[\]" value="panel-cat"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/name="color\[\]" value="Teal"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/name="in_stock" value="1"[^>]*checked/', $html);
    }

    public function test_ticked_filters_show_as_pills_that_remove_just_one_value(): void
    {
        $a = Category::create(['name' => 'পিল-এ', 'slug' => 'pill-a']);
        $b = Category::create(['name' => 'পিল-বি', 'slug' => 'pill-b']);
        $this->product(['category_id' => $a->id]);

        $html = $this->get('/shop?category[]=pill-a&category[]=pill-b&in_stock=1')->assertOk()->getContent();
        $pills = substr($html, strpos($html, 'aria-label="চালু ফিল্টার"'));
        $pills = substr($pills, 0, strpos($pills, '</div>'));

        $this->assertStringContainsString('পিল-এ', $pills);
        $this->assertStringContainsString('পিল-বি', $pills);
        // Removing "পিল-এ" keeps পিল-বি and the stock filter.
        $this->assertMatchesRegularExpression('/href="[^"]*category%5B0%5D=pill-b[^"]*in_stock=1[^"]*"[^>]*aria-label="পিল-এ/', $pills);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*pill-a[^"]*"[^>]*aria-label="পিল-এ/', $pills);
    }

    public function test_changing_the_sort_keeps_the_filters_and_the_search(): void
    {
        $html = $this->get('/shop?category[]=x&color[]=Red&price_min=100&in_stock=1&q=শার্ট')->assertOk()->getContent();

        foreach (['name="category[]" value="x"', 'name="color[]" value="Red"', 'name="price_min" value="100"', 'name="in_stock" value="1"', 'name="q" value="শার্ট"'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
    }

    public function test_pagination_keeps_the_filters(): void
    {
        $cat = Category::create(['name' => 'পেজ-ক্যাট', 'slug' => 'page-cat']);
        foreach (range(1, 14) as $i) {
            $this->product(['name' => "পেজ-পণ্য-{$i}", 'category_id' => $cat->id, 'price' => 100 + $i]);
        }

        $html = $this->get('/shop?category[]=page-cat&price_min=100')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/href="[^"]*page=2[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/href="[^"]*page=2[^"]*category%5B0%5D=page-cat|href="[^"]*category%5B0%5D=page-cat[^"]*page=2/', $html);
        $this->get('/shop?category[]=page-cat&price_min=100&page=2')->assertOk()->assertSee('পেজ-পণ্য-');
    }

    public function test_garbage_in_the_url_never_breaks_the_page(): void
    {
        $this->product();

        foreach ([
            '/shop?category[]=&category[]=%00',
            '/shop?price_min=abc&price_max=-5',
            '/shop?color[a][b]=c&size=',
            '/shop?brand[]=x&brand[]=999999999999',
            '/shop?in_stock=banana&sort[]=1',
            '/offers?category[x]=y',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_the_request_parser_cleans_its_input(): void
    {
        $f = ShopFilters::fromRequest(request()->duplicate(['category' => ['a', 'a', ' ', 'b'], 'brand' => ['3', 'x'], 'price_min' => '-4', 'price_max' => '90', 'in_stock' => '1']));

        $this->assertSame(['a', 'b'], $f['category']);
        $this->assertSame(['3'], $f['brand']);
        $this->assertNull($f['price_min']);
        $this->assertSame(90, $f['price_max']);
        $this->assertTrue($f['in_stock']);
        $this->assertSame(5, ShopFilters::count($f)); // 2 categories + 1 brand + the price range + in stock
    }
}
