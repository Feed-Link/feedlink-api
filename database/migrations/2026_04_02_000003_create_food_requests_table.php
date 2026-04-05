<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recipient_id')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('quantity_needed');
            $table->string('food_type');
            $table->timestamp('needed_by');
            $table->string('status')->default('open');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('address');
            $table->foreignUuid('accepted_by')->nullable()->constrained('users');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'needed_by']);
            $table->index('recipient_id');
        });

        // Add PostGIS geography Point column
        DB::statement('ALTER TABLE food_requests ADD COLUMN location geography(Point, 4326)');
    }

    public function down(): void
    {
        Schema::dropIfExists('food_requests');
    }
};
