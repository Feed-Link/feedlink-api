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
        Schema::create('food_request_tags', function (Blueprint $table) {
            $table->id();
            $table->uuid('food_request_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();

            $table->foreign('food_request_id')->references('id')->on('food_requests')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
            $table->unique(['food_request_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_request_tags');
    }
};
