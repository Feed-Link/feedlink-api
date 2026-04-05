<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('donor_id')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('quantity');
            $table->string('food_type');
            $table->json('photos')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('pickup_before');
            $table->text('pickup_instructions')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('address');
            $table->uuid('listing_claim_id')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users');
            $table->foreignUuid('claimed_by')->nullable()->constrained('users');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index('donor_id');
        });

        // Add PostGIS geography Point column
        DB::statement('ALTER TABLE food_listings ADD COLUMN location geography(Point, 4326)');
    }

    public function down(): void
    {
        Schema::dropIfExists('food_listings');
    }
};
