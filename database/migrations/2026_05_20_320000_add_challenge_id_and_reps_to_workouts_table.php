<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->foreignId('challenge_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('reps')->default(0)->after('calories');
        });
    }

    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('challenge_id');
            $table->dropColumn('reps');
        });
    }
};
