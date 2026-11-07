<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('property_rates', function (Blueprint $table) {
            $table->id();

            $table->string("property_id");
            $table->date("single_date");
            $table->bigInteger("single_date_timestamp");
            $table->decimal("price");
            $table->string("is_available")->default(1);

            $table->string("platform_type")->default("airbnb");
            $table->string("currency")->default("USD");
            $table->decimal("base_price")->nullable();
            $table->text("notes")->nullable();
            $table->string("min_stay")->nullable();
            $table->string("base_min_stay")->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('property_rates');
    }
};
