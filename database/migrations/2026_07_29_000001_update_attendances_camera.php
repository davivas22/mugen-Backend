<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('status', ['confirmed', 'pending'])->default('confirmed')->after('attended_date');
            $table->string('photo_path')->nullable()->after('status');
            $table->unsignedBigInteger('confirmed_by')->nullable()->after('photo_path');
            $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by']);
            $table->dropColumn(['status', 'photo_path', 'confirmed_by']);
        });
    }
};
