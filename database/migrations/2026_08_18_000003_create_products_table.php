<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('prefix', 30);
            $table->string('color', 7);
            $table->unique(['instance_id', 'prefix']);
            $table->text('description')->nullable();
            $table->json('fields')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('funnel_product', function (Blueprint $table): void {
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['funnel_id', 'product_id'])->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_product');
        Schema::dropIfExists('products');
    }
};
