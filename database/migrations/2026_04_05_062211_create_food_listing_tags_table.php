<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('food_listing_tags', function (Blueprint $table) {
            $table->id();
            $table->uuid('food_listing_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();

            $table->foreign('food_listing_id')->references('id')->on('food_listings')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
            $table->unique(['food_listing_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_listing_tags');
    }
};
