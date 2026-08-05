<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->decimal('gym_lat', 10, 7)->nullable()->after('use_location');
            $table->decimal('gym_lng', 10, 7)->nullable()->after('gym_lat');
            $table->unsignedInteger('gym_radius_meters')->default(200)->after('gym_lng');
        });
    }

    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn(['gym_lat', 'gym_lng', 'gym_radius_meters']);
        });
    }
};
