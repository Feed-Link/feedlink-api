<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_acceptances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('terms_version'); // e.g., "2026-04-05"
            $table->enum('terms_type', ['donor', 'recipient', 'mutual'])->default('mutual');
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
            $table->index('terms_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_acceptances');
    }
};
