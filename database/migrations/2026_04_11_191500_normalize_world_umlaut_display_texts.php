<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('worlds')
            ->where('slug', 'kriminalfaelle')
            ->whereIn('name', ['Kriminalfaelle', 'Kriminalfalle'])
            ->update([
                'name' => 'Kriminalfälle',
                'updated_at' => $now,
            ]);

        DB::table('worlds')
            ->where('slug', 'chroniken-der-asche')
            ->where('tagline', 'Duestere Fantasy in den Aschelanden.')
            ->update([
                'tagline' => 'Düstere Fantasy in den Aschelanden.',
                'updated_at' => $now,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $now = now();

        DB::table('worlds')
            ->where('slug', 'kriminalfaelle')
            ->where('name', 'Kriminalfälle')
            ->update([
                'name' => 'Kriminalfaelle',
                'updated_at' => $now,
            ]);

        DB::table('worlds')
            ->where('slug', 'chroniken-der-asche')
            ->where('tagline', 'Düstere Fantasy in den Aschelanden.')
            ->update([
                'tagline' => 'Duestere Fantasy in den Aschelanden.',
                'updated_at' => $now,
            ]);
    }
};
