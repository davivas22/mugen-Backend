<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('social_bets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            // first_to_fail | first_to_quit | most_days | complete_all | custom
            $table->string('bet_type', 30);
            // Optional free-text description (required for custom type)
            $table->string('description', 200)->nullable();
            // Optional stake ("El perdedor invita un café")
            $table->string('stake', 150)->nullable();
            // open | resolved
            $table->string('status', 20)->default('open');
            // true = prediction was correct, false = wrong (null while open)
            $table->boolean('outcome')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_bets');
    }
};
