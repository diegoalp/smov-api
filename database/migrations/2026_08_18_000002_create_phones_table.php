<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20);
            $table->boolean('whatsapp')->default(false);
            $table->timestamps();

            $table->unique(['client_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phones');
    }
};
