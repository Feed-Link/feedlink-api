<?php

use App\Modules\FoodShare\Enums\FoodListTypeEnums;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('food_lists', function (Blueprint $table) {
            $table->uuid('id')->primary()->unique();
            $table->foreignUuid('user_id')
                  ->index()
                  ->references('id')
                  ->on('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', FoodListTypeEnums::getAllValues());
            $table->integer('quantity')->nullable();
            $table->float('weight')->nullable();
            $table->dateTime('pickup_within');
            $table->text('instructions')->nullable();
            $table->magellanPoint('location', 4326);
            $table->string('address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_lists');
    }
};
