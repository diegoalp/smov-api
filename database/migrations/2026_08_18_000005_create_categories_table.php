<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unique(['instance_id', 'name']);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('category_funnel', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['category_id', 'funnel_id'])->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_funnel');
        Schema::dropIfExists('categories');
    }
};
