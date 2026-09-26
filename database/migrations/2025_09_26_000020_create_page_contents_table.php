<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_contents', function (Blueprint $table) {
            $table->id();
            $table->string('page_key', 100);
            $table->string('section_key', 100);
            $table->string('element_key', 100);
            $table->string('content_type', 50)->default('text');
            $table->text('content_value')->nullable();
            $table->json('settings_json')->nullable();
            $table->boolean('is_published')->default(false);
            $table->integer('version')->default(1);
            $table->string('updated_by')->nullable();
            // Old schema has only updated_at (ON UPDATE CURRENT_TIMESTAMP), no created_at.
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['page_key', 'section_key', 'element_key'], 'idx_page_section_element');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_contents');
    }
};
