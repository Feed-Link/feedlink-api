<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('contact');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->geography('location')->nullable()->after('longitude');
            $table->boolean('is_verified')->default(false)->after('is_active')
                ->comment('Whether the user is verified (NGO/organisation badge)');
            $table->string('profile_photo')->nullable()->after('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'location', 'is_verified', 'profile_photo']);
        });
    }
};
