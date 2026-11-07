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
        Schema::create('property_amenity_groups', function (Blueprint $table) {
            $table->id();
            $table->string("property_id");
            $table->string("status",10)->default("true");
            $table->string("name");
            $table->string("image")->nullable();
            $table->bigInteger("sorting")->default(0);
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
        Schema::dropIfExists('property_amenity_groups');
    }
};
