<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->date('week_start'); // always Monday
            $table->json('committed_days'); // [0,2,4] = Lun,Mié,Vie
            $table->timestamps();
            $table->unique(['user_id', 'challenge_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_commitments');
    }
};
