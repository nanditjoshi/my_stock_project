<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_center', function (Blueprint $table) {
            $table->text('images')->nullable()->after('message3');
            $table->string('PMS_id')->nullable()->after('images');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_center', function (Blueprint $table) {
            $table->dropColumn(['images', 'PMS_id']);
        });
    }
};
