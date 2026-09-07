<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->foreignId('lead_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('disposition_id')->nullable()->constrained()->nullOnDelete();
            $table->string('priority', 10)->default('medium');
            $table->dateTime('due_at')->nullable();
            $table->string('loss_reason')->nullable();
            $table->json('custom_data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lead_source_id');
            $table->dropConstrainedForeignId('disposition_id');
            $table->dropColumn(['priority', 'due_at', 'loss_reason', 'custom_data']);
        });
    }
};
