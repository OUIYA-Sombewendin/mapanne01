<?php

use App\Support\TradeModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_types', function (Blueprint $table) {
            $table->jsonb('modules')->default('[]')->after('profile_kind');
        });

        // Chaque métier reçoit ses modules ; seul un métier avec le catalogue vend des pièces.
        foreach (DB::table('provider_types')->get() as $type) {
            $modules = TradeModules::defaults($type->profile_kind);
            DB::table('provider_types')->where('id', $type->id)->update([
                'modules' => json_encode($modules),
                'can_sell_parts' => in_array('catalog', $modules, true),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('provider_types', fn (Blueprint $table) => $table->dropColumn('modules'));
    }
};
