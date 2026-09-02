<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEma10WeekToWhatchListTable extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('whatch_list', '10wema')) {
            return;
        }

        Schema::table('whatch_list', function (Blueprint $table) {
            $table->decimal('10wema', 15, 2)->nullable()->after('21ema');
        });
    }

    public function down()
    {
        if (!Schema::hasColumn('whatch_list', '10wema')) {
            return;
        }

        Schema::table('whatch_list', function (Blueprint $table) {
            $table->dropColumn('10wema');
        });
    }
}
