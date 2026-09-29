<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['instance_id', 'title']);
        });

        Schema::create('checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checklist_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->boolean('required')->default(false);
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['checklist_id', 'position']);
        });

        Schema::create('checklist_funnel', function (Blueprint $table): void {
            $table->foreignId('checklist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['checklist_id', 'funnel_id']);
        });

        Schema::create('checklist_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checklist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('min_stage_id')->nullable()->constrained('stages')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('checklist_condition_product', function (Blueprint $table): void {
            $table->foreignId('condition_id')->constrained('checklist_conditions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['condition_id', 'product_id']);
            $table->unique('product_id');
        });

        Schema::create('checklist_item_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checklist_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->boolean('done')->default(false);
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['checklist_item_id', 'business_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_item_completions');
        Schema::dropIfExists('checklist_condition_product');
        Schema::dropIfExists('checklist_conditions');
        Schema::dropIfExists('checklist_funnel');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('checklists');
    }
};
