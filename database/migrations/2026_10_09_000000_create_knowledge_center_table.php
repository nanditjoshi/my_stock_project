<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_center', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->longText('message1')->nullable();
            $table->longText('message2')->nullable();
            $table->longText('message3')->nullable();
            $table->timestamp('created_date')->nullable();
            $table->timestamp('updated_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_center');
    }
};
