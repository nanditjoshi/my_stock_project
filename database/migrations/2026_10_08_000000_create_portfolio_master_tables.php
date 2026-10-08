<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_master', function (Blueprint $table) {
            $table->id();
            // Each account belongs to a user, allowing separate portfolios per user.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('acc_name');
            $table->string('acc_number')->nullable();
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('investment_type_master', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });

        Schema::create('portfolio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_master_id')->constrained('account_master')->cascadeOnDelete();
            $table->foreignId('investment_type_master_id')->constrained('investment_type_master')->restrictOnDelete();
            $table->string('symbol')->nullable();
            $table->decimal('qty', 18, 6)->default(0);
            $table->decimal('investement_price', 18, 4)->default(0);
            $table->decimal('current_price', 18, 4)->default(0);
            $table->decimal('total_investment', 18, 2)->default(0);
            $table->decimal('total_PL', 18, 2)->default(0);
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
            $table->index(['account_master_id', 'investment_type_master_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio');
        Schema::dropIfExists('investment_type_master');
        Schema::dropIfExists('account_master');
    }
};
