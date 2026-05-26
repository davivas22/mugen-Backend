<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('cover_image')->nullable();
            $table->unsignedInteger('duration_days');
            $table->date('start_date');
            $table->json('gym_days_per_week');
            $table->string('challenge_mode')->default('tracking');
            $table->boolean('use_location')->default(false);
            $table->string('meeting_point')->nullable();
            $table->boolean('use_camera')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
