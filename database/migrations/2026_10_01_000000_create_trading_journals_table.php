<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_journals', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 32);
            $table->date('entry_date');
            $table->date('exit_date')->nullable();
            $table->text('reason_of_entry')->nullable();
            $table->text('reason_of_exit')->nullable();
            $table->string('setup')->nullable();
            $table->decimal('entry_price', 12, 4);
            $table->decimal('exit_price', 12, 4)->nullable();
            $table->decimal('profit_loss', 14, 2)->nullable();
            $table->decimal('profit_loss_percentage', 8, 4)->nullable();
            $table->unsignedInteger('days_held')->nullable();
            $table->string('entry_image')->nullable();
            $table->string('exit_image')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_journals');
    }
};
