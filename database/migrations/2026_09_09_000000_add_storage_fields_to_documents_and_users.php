<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('disk')->default('local')->after('file');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('profile_photo_path')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('profile_photo_path');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('disk');
        });
    }
};
