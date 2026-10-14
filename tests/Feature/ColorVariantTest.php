<?php

namespace Tests\Feature;

use App\Livewire\Product\AddToCart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Livewire\Livewire;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/** Colour + size variants: own price, stock and picture each; old size-only products are untouched. */
class ColorVariantTest extends TestCase
{
    use CreatesShopData;

    private function tee(): Product
    {
        $p = $this->product(['name' => 'রঙিন টি', 'slug' => 'color-tee', 'price' => 500, 'stock' => 0]);
        $rows = [
            ['Red', '#ff0000', 'M', 500, 3, '/storage/media/red.jpg'],
            ['Red', '#ff0000', 'L', 550, 0, '/storage/media/red.jpg'],
            ['Blue', '#0000ff', 'M', 520, 4, '/storage/media/blue.jpg'],
            ['Blue', '#0000ff', 'L', 560, 2, '/storage/media/blue.jpg'],
        ];
        foreach ($rows as [$c, $hex, $s, $price, $stock, $img]) {
            ProductVariant::create(['product_id' => $p->id, 'color' => $c, 'color_hex' => $hex, 'size' => $s, 'price' => $price, 'stock' => $stock, 'image' => $img]);
        }

        return $p->fresh('variants');
    }

    public function test_label_is_composed_from_colour_and_size_and_stock_is_summed(): void
    {
        $p = $this->tee();

        $this->assertSame('Red / M', $p->variants[0]->label);
        $this->assertSame(9, $p->stock);
    }

    public function test_product_page_shows_colours_then_sizes_and_the_colour_pictures(): void
    {
        $this->tee();

        $this->get('/product/color-tee')->assertOk()
            ->assertSee('রং বাছাই করুন')->assertSee('Red')->assertSee('Blue')
            ->assertSee('/storage/media/blue.jpg', false);
    }

    public function test_choosing_a_colour_switches_picture_price_and_size(): void
    {
        $p = $this->tee();

        Livewire::test(AddToCart::class, ['productId' => $p->id, 'mode' => 'detail'])
            ->assertSet('variantId', $p->variants[0]->id) // Red / M
            ->call('selectColor', 'Blue')
            ->assertSet('variantId', $p->variants[2]->id) // keeps size M
            ->assertDispatched('variant-image', url: '/storage/media/blue.jpg')
            ->assertSee('560') // Blue / L chip price shown
            ->call('selectVariant', $p->variants[3]->id)
            ->assertSet('variantId', $p->variants[3]->id);
    }

    public function test_a_sold_out_size_of_a_colour_cannot_be_ordered_but_the_cart_keeps_the_exact_variant(): void
    {
        $p = $this->tee();
        $blueL = $p->variants[3];

        Livewire::test(AddToCart::class, ['productId' => $p->id, 'mode' => 'detail'])
            ->call('selectVariant', $blueL->id)
            ->set('quantity', 2)
            ->call('add');

        $line = app(CartService::class)->lines()[0];
        $this->assertSame($blueL->id, $line['variant']->id);
        $this->assertSame('/storage/media/blue.jpg', $line['image']);
        $this->assertStringContainsString('Blue / L', $line['name']);
    }

    public function test_size_only_products_still_work_without_colours(): void
    {
        $p = $this->product(['name' => 'পুরনো শার্ট', 'slug' => 'old-shirt', 'stock' => 0]);
        ProductVariant::create(['product_id' => $p->id, 'label' => 'M', 'price' => 500, 'stock' => 2]);
        ProductVariant::create(['product_id' => $p->id, 'label' => 'L', 'price' => 500, 'stock' => 2]);

        $this->get('/product/old-shirt')->assertOk()->assertSee('সাইজ বাছাই করুন')->assertDontSee('রং বাছাই করুন');
        $this->assertSame('M', $p->fresh('variants')->variants[0]->label);
    }
}
