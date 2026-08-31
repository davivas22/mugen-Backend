<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sala_battles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenger_id')->constrained('challenges')->cascadeOnDelete();
            $table->foreignId('opponent_id')->constrained('challenges')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('invite_code', 10)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['pending', 'active', 'finished'])->default('pending');
            $table->foreignId('winner_id')->nullable()->constrained('challenges')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sala_battles');
    }
};
