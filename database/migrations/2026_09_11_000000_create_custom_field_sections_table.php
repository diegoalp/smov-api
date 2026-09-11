<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('section');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['instance_id', 'section', 'name']);
        });

        Schema::table('custom_fields', function (Blueprint $table): void {
            $table->foreignId('custom_field_section_id')->nullable()->after('section')->constrained('custom_field_sections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('custom_fields', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('custom_field_section_id');
        });

        Schema::dropIfExists('custom_field_sections');
    }
};
