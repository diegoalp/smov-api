<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->foreignId('instance_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        DB::table('clients')
            ->whereNull('instance_id')
            ->orderBy('id')
            ->eachById(function (object $client): void {
                $instanceId = DB::table('businesses')
                    ->where('client_id', $client->id)
                    ->orderBy('id')
                    ->value('instance_id');

                if ($instanceId !== null) {
                    DB::table('clients')->where('id', $client->id)->update(['instance_id' => $instanceId]);
                }
            });

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropUnique('clients_registration_unique');
            $table->unique(['instance_id', 'registration']);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropUnique(['instance_id', 'registration']);
            $table->unique('registration');
            $table->dropConstrainedForeignId('instance_id');
        });
    }
};
