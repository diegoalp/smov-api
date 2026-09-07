<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('position');
            $table->integer('duration')->nullable();
            $table->string('duration_unit', 10)->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('is_final')->default(false)->index();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['funnel_id', 'name']);
            $table->unique(['funnel_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stages');
    }
};
