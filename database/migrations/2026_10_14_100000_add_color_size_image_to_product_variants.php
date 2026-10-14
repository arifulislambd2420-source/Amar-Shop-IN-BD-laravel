<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Colour + size variants. All additive and nullable, so existing variants
| (which only have a label such as "M") keep working untouched:
|  - color / color_hex: the colour name and its swatch
|  - size: the size, kept apart from the colour
|  - image: the picture shown when this colour is chosen
| `label` stays the one human-readable name ("Red / M") that carts, orders
| and SMS already use; it is composed from colour + size when those are set.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('color')->nullable()->after('label');
            $table->string('color_hex', 9)->nullable()->after('color');
            $table->string('size')->nullable()->after('color_hex');
            $table->string('image')->nullable()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['color', 'color_hex', 'size', 'image']);
        });
    }
};
