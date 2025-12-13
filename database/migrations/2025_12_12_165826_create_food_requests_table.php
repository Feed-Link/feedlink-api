<?php

use App\Modules\FoodShare\Enums\FoodRequestEnums;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('food_requests', function (Blueprint $table) {
            $table->uuid('id')->primary()->unique();
            $table->foreignUuid('foodlist_id')
                ->index()
                ->references('id')
                ->on('food_lists');
            $table->foreignUuid('user_id')
                ->index()
                ->references('id')
                ->on('users');
            $table->enum('status', FoodRequestEnums::getAllValues())->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_requests');
    }
};
