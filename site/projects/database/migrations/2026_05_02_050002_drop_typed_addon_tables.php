<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('property_hottubs');
        Schema::dropIfExists('property_firewoods');
        Schema::dropIfExists('property_rvhookups');
        Schema::dropIfExists('property_no_of_visitors');
    }

    public function down(): void
    {
        Schema::create('property_hottubs', function (Blueprint $t) {
            $t->id();
            $t->string('property_id');
            $t->string('hottub_name', 1025);
            $t->text('hottub_helping_text')->nullable();
            $t->string('hottub_status', 50)->nullable();
            $t->string('hottub_rate', 50);
            $t->string('hottub_type', 50)->default('per person');
            $t->timestamps();
        });
        Schema::create('property_firewoods', function (Blueprint $t) {
            $t->id();
            $t->string('property_id');
            $t->string('firewood_name', 1025);
            $t->text('firewood_helping_text')->nullable();
            $t->string('firewood_status', 50)->nullable();
            $t->string('firewood_rate', 50);
            $t->string('firewood_type', 50)->default('per person');
            $t->timestamps();
        });
        Schema::create('property_rvhookups', function (Blueprint $t) {
            $t->id();
            $t->string('property_id');
            $t->string('rvhookup_name', 1025);
            $t->text('rvhookup_helping_text')->nullable();
            $t->string('rvhookup_status', 50)->nullable();
            $t->string('rvhookup_rate', 50);
            $t->string('rvhookup_type', 50)->default('per person');
            $t->timestamps();
        });
        Schema::create('property_no_of_visitors', function (Blueprint $t) {
            $t->id();
            $t->string('property_id');
            $t->string('visitor_name', 1025);
            $t->text('visitor_helping_text')->nullable();
            $t->string('visitor_status', 50)->nullable();
            $t->string('visitor_rate', 50);
            $t->string('visitor_type', 50)->default('per person');
            $t->timestamps();
        });
    }
};
