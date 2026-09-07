<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('activity_type_id')->constrained()->restrictOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->dateTime('scheduled_at');
            $table->string('status', 20)->default('pending');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['instance_id', 'scheduled_at', 'status']);
            $table->index(['instance_id', 'user_id', 'scheduled_at']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('activities');
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('supervisor_id'));
    }
};
