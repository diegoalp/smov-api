<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add the persisted resources required by the frontend integration endpoints. */
    public function up(): void
    {
        Schema::table('funnels', function (Blueprint $table): void {
            $table->string('owner_team')->nullable()->after('description');
            $table->boolean('active')->default(true)->after('color')->index();
        });

        Schema::table('lead_sources', function (Blueprint $table): void {
            $table->string('type', 20)->default('manual')->after('name');
            $table->boolean('active')->default(true)->after('description')->index();
        });

        Schema::create('automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger');
            $table->text('condition')->nullable();
            $table->string('action');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['instance_id', 'active']);
        });

        Schema::create('business_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('title');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['instance_id', 'business_id']);
        });

        Schema::create('business_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sender', 20);
            $table->text('text');
            $table->timestamps();
            $table->index(['instance_id', 'business_id']);
        });

        Schema::create('installed_operation_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('template_key');
            $table->timestamp('installed_at');
            $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installed_operation_templates');
        Schema::dropIfExists('business_messages');
        Schema::dropIfExists('business_events');
        Schema::dropIfExists('automation_rules');

        Schema::table('lead_sources', function (Blueprint $table): void {
            $table->dropIndex(['active']);
            $table->dropColumn(['type', 'active']);
        });

        Schema::table('funnels', function (Blueprint $table): void {
            $table->dropIndex(['active']);
            $table->dropColumn(['owner_team', 'active']);
        });
    }
};
