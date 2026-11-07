<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('direct_booking_benefits', function (Blueprint $table) {
            $table->id();
            $table->string('icon')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['true', 'false'])->default('true');
            $table->timestamps();
        });
    }
    public function down() {
        Schema::dropIfExists('direct_booking_benefits');
    }
};
