<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('food_listing_id');
            $table->foreignUuid('recipient_id')->constrained('users');
            $table->foreignUuid('claim_user_id')->nullable()->constrained('users');
            $table->string('status')->default('pending');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['food_listing_id', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_claims');
    }
};
