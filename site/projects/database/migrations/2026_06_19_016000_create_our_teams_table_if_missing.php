<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('our_teams')) {
            return;
        }

        Schema::create('our_teams', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('profile')->nullable();
            $table->text('message')->nullable();
            $table->string('image')->nullable();
            $table->string('bannerImage')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('our_teams');
    }
};
