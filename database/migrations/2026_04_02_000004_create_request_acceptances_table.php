<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_acceptances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('food_request_id')->constrained('food_requests')->cascadeOnDelete();
            $table->foreignUuid('donor_id')->constrained('users');
            $table->string('status')->default('pending');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['food_request_id', 'donor_id']);
            $table->unique(['food_request_id', 'donor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_acceptances');
    }
};
