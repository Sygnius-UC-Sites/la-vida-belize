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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string("name");

            $table->string("seo_url");
            $table->string("heading")->nullable();
            $table->string("price")->default(0);
            $table->text("address")->nullable();
            $table->string("mobile",50)->nullable();
            $table->string("email",50)->nullable();
            $table->string("website",191)->nullable();
            $table->longText("short_description")->nullable();
            $table->longText("long_description")->nullable();
            $table->longText("description")->nullable();

            $table->longText("cancellation_policy")->nullable();
            $table->longText("booking_policy")->nullable();
            $table->longText("notes")->nullable();

            $table->bigInteger("bedroom")->default(0);
            $table->bigInteger("bathroom")->nullable();
            $table->bigInteger("beds")->nullable();
            $table->bigInteger("sleeps")->nullable();
            $table->string("area")->nullable();
            $table->bigInteger("full_bath")->nullable();
            $table->bigInteger("half_bath")->nullable();
            $table->string("spaces")->nullable();
            $table->string("feature_image")->nullable();
            $table->decimal("cleaning_fee")->nullable();
            $table->decimal("heating_swimming_pool_fee")->nullable();
            $table->decimal("refundable_damage_fee")->nullable();
            $table->decimal("pet_fee")->nullable();
            $table->decimal("tax")->nullable();
            $table->decimal("propane_gas")->nullable();


            $table->string("status",10)->default("true");
            $table->longText("meta_title")->nullable();
            $table->longText("meta_keywords")->nullable();
            $table->longText("meta_description")->nullable();
            $table->longText("header_section")->nullable();
            $table->longText("footer_section")->nullable();
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
        Schema::dropIfExists('properties');
    }
};
