<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Ensure10wemaOnWhatchListTable extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('whatch_list', '10wema')) {
            return;
        }

        Schema::table('whatch_list', function (Blueprint $table) {
            $table->decimal('10wema', 15, 2)->nullable()->after('21ema');
        });

        // Preserve values for installations that previously used this interim name.
        if (Schema::hasColumn('whatch_list', 'ema_10_week')) {
            DB::table('whatch_list')->update([
                '10wema' => DB::raw('`ema_10_week`'),
            ]);
        }
    }

    public function down()
    {
        // The column may have existed before this migration, so do not remove it.
    }
}
