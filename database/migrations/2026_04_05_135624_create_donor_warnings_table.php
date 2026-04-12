<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donor_warnings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('donor_id')->unique();
            $table->integer('claim_count')->default(0);
            $table->boolean('warning_active')->default(false);
            $table->timestamp('last_claim_at')->nullable();
            $table->timestamps();

            $table->foreign('donor_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donor_warnings');
    }
};
