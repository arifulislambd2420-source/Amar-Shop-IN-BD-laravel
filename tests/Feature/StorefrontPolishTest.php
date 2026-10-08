<?php

namespace Tests\Feature;

use App\Livewire\Product\AddToCart;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

class StorefrontPolishTest extends TestCase
{
    use CreatesShopData;

    /** A product with sizes S/M/L/XL and the given stock per size. */
    private function sized(array $stock = [3, 5, 0, 2], array $overrides = []): Product
    {
        $product = $this->product(['stock' => array_sum($stock)] + $overrides);
        foreach (['S', 'M', 'L', 'XL'] as $i => $size) {
            ProductVariant::create(['product_id' => $product->id, 'label' => $size, 'price' => 500, 'stock' => $stock[$i]]);
        }

        return $product->load('variants');
    }

    public function test_a_card_never_adds_a_size_the_customer_did_not_pick(): void
    {
        $product = $this->sized();

        Livewire::test(AddToCart::class, ['productId' => $product->id, 'mode' => 'card'])
            ->assertSee(route('product.show', $product->slug))
            ->assertDontSee('wire:click="buyNow"', false)
            ->call('add')
            ->call('buyNow')
            ->assertNoRedirect();

        $this->assertSame(0, app(CartService::class)->count());
    }

    public function test_a_card_for_a_product_without_sizes_orders_in_one_tap(): void
    {
        $product = $this->product();

        Livewire::test(AddToCart::class, ['productId' => $product->id, 'mode' => 'card'])
            ->call('buyNow')
            ->assertRedirect(route('checkout'));

        $this->assertSame(1, app(CartService::class)->count());
    }

    public function test_the_first_size_in_stock_is_preselected(): void
    {
        $product = $this->sized([0, 0, 4, 1]);
        $l = $product->variants->firstWhere('label', 'L');

        Livewire::test(AddToCart::class, ['productId' => $product->id, 'mode' => 'detail'])
            ->assertSet('variantId', $l->id);
    }

    public function test_quantity_is_capped_at_the_chosen_sizes_stock(): void
    {
        $product = $this->sized([3, 5, 0, 2]);
        $xl = $product->variants->firstWhere('label', 'XL');

        Livewire::test(AddToCart::class, ['productId' => $product->id, 'mode' => 'detail'])
            ->call('selectVariant', $xl->id)
            ->call('increment')->call('increment')->call('increment')
            ->assertSet('quantity', 2)
            ->assertSee('মাত্র 2টি বাকি')
            ->call('add');

        $line = app(CartService::class)->lines()[0];
        $this->assertSame($xl->id, $line['variant']->id);
        $this->assertSame(2, $line['quantity']);
    }

    public function test_an_out_of_stock_size_cannot_be_bought(): void
    {
        $product = $this->sized([3, 5, 0, 2]);
        $l = $product->variants->firstWhere('label', 'L');

        Livewire::test(AddToCart::class, ['productId' => $product->id, 'mode' => 'detail'])
            ->set('variantId', $l->id)
            ->assertSee('এই সাইজ স্টকে নেই')
            ->call('add')
            ->call('buyNow')
            ->assertNoRedirect();

        $this->assertSame(0, app(CartService::class)->count());
    }

    public function test_a_product_is_sold_out_only_when_every_size_is(): void
    {
        $some = $this->sized([0, 2, 0, 0]);
        $none = $this->sized([0, 0, 0, 0], ['name' => 'Gone']);

        $this->assertFalse($some->isSoldOut());
        $this->assertTrue($none->isSoldOut());

        $this->get('/product/'.$none->slug)->assertOk()
            ->assertSee('স্টক শেষ')
            ->assertSee('https://schema.org/OutOfStock', false);
        $this->get('/product/'.$some->slug)->assertOk()
            ->assertSee('https://schema.org/InStock', false);
    }

    public function test_product_page_has_a_swipeable_gallery_size_chips_and_a_sticky_buy_bar(): void
    {
        $product = $this->sized();

        $this->get('/product/'.$product->slug)->assertOk()
            ->assertSee('snap-x snap-mandatory', false)
            ->assertSee('role="radio"', false)
            ->assertSee('সাইজ বাছাই করুন')
            ->assertSee('pdp-cta', false);
    }

    public function test_shop_heading_names_the_category_and_the_search(): void
    {
        $cat = Category::create(['name' => 'জুতা', 'slug' => 'shoes']);
        $this->product(['name' => 'চামড়ার জুতা', 'category_id' => $cat->id]);

        $this->get('/shop?category=shoes')->assertOk()->assertSee('<h1', false)->assertSee('জুতা')->assertSee('1 টি পণ্য');
        $this->get('/shop?q=nothing-here')->assertOk()
            ->assertSee('“nothing-here” এর ফলাফল')
            ->assertSee('কোনো পণ্য পাওয়া যায়নি')
            ->assertSee('noindex, follow', false);
    }

    public function test_shop_pagination_is_in_bangla(): void
    {
        foreach (range(1, 13) as $i) {
            $this->product();
        }

        $this->get('/shop')->assertOk()->assertSee('পরের ›')->assertSee('পাতা 1 / 2')->assertDontSee('Next &raquo;', false);
    }

    public function test_review_errors_are_in_bangla(): void
    {
        $product = $this->product();

        $this->from('/product/'.$product->slug)->post('/product/'.$product->slug.'/reviews', [])
            ->assertSessionHasErrors(['customer_name' => 'আপনার নাম লিখুন।', 'rating' => '১ থেকে ৫ এর মধ্যে রেটিং দিন।']);
    }

    public function test_home_offer_section_links_to_the_offers_page(): void
    {
        $this->product(['price' => 500, 'sale_price' => 400]);

        $this->get('/')->assertOk()->assertSee('href="'.route('offers').'"', false);
    }
}
