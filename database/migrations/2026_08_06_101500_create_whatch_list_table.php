<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatchListTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('whatch_list', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->index();
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('current_price', 15, 2)->nullable();
            $table->decimal('21ema', 15, 2)->nullable();
            $table->decimal('30wema', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('whatch_list');
    }
}
