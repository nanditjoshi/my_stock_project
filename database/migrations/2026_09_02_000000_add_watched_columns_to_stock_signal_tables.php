<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWatchedColumnsToStockSignalTables extends Migration
{
    private $tables = ['20_cross_50', '30w_ema_cross'];

    public function up()
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $addWatched = !Schema::hasColumn($table, 'is_whatched');
            $addUpdatedAt = !Schema::hasColumn($table, 'updated_at');

            if (!$addWatched && !$addUpdatedAt) {
                continue;
            }

            Schema::table($table, function (Blueprint $tableBlueprint) use ($addWatched, $addUpdatedAt) {
                if ($addWatched) {
                    $tableBlueprint->boolean('is_whatched')->default(false);
                }

                if ($addUpdatedAt) {
                    $tableBlueprint->timestamp('updated_at')->nullable();
                }
            });
        }
    }

    public function down()
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $columns = array_filter(['is_whatched', 'updated_at'], function ($column) use ($table) {
                return Schema::hasColumn($table, $column);
            });

            if ($columns !== []) {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($columns) {
                    $tableBlueprint->dropColumn($columns);
                });
            }
        }
    }
}
