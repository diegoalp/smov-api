<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_type_funnel', function (Blueprint $table) {
            $table->foreignId('activity_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['activity_type_id', 'funnel_id']);
        });

        DB::table('activity_types')->whereNotNull('funnel_id')->orderBy('id')->chunkById(500, function ($types) {
            DB::table('activity_type_funnel')->insert($types->map(fn ($type) => [
                'activity_type_id' => $type->id,
                'funnel_id' => $type->funnel_id,
                'created_at' => $type->created_at,
                'updated_at' => $type->updated_at,
            ])->all());
        });

        // Keep the legacy nullable column for a reversible migration.
        // Application reads and writes use only the pivot relation.
    }

    public function down(): void
    {
        DB::table('activity_types')->update(['funnel_id' => null]);

        // The previous schema can retain only one funnel per activity type.
        DB::table('activity_type_funnel')->select('activity_type_id')
            ->selectRaw('MIN(funnel_id) as funnel_id')->groupBy('activity_type_id')
            ->orderBy('activity_type_id')->get()->each(function ($link) {
                DB::table('activity_types')->where('id', $link->activity_type_id)
                    ->update(['funnel_id' => $link->funnel_id]);
            });

        Schema::dropIfExists('activity_type_funnel');
    }
};
