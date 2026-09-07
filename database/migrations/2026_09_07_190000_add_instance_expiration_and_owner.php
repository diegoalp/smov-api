<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
    public function up(): void {
        Schema::table('instances', function (Blueprint $table) {
            $table->date('expiration_date')->nullable();
            $table->foreignId('owner_user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
        });
        // Preserve existing instances; the master can set their expiration explicitly.
    }
    public function down(): void {
        Schema::table('instances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn('expiration_date');
        });
    }
};
