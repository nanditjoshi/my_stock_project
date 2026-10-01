<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_journals', function (Blueprint $table) {
            $table->decimal('current_price', 12, 4)->nullable();
            $table->decimal('buying_average', 12, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trading_journals', function (Blueprint $table) {
            $table->dropColumn(['current_price', 'buying_average']);
        });
    }
};
