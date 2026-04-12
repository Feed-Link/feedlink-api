<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('illness_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reporter_id');
            $table->uuid('donor_id');
            $table->uuid('food_listing_id')->nullable();
            $table->text('description');
            $table->timestamp('reported_at'); // when illness occurred
            $table->enum('status', ['pending', 'reviewed', 'archived'])->default('pending');
            $table->timestamps();

            $table->foreign('reporter_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('donor_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('food_listing_id')->references('id')->on('food_listings')->onDelete('set null');

            $table->index('donor_id');
            $table->index('reporter_id');
            $table->index('reported_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('illness_claims');
    }
};
