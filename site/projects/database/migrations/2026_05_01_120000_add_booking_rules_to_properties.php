<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Booking rules
            $table->boolean('instant_booking_enabled')->default(false);
            $table->boolean('rental_agreement_required')->default(false);
            $table->enum('cancellation_policy_type', [
                'flexible', 'moderate', 'strict', 'firm', 'super_strict', 'custom'
            ])->default('moderate');

            // Stay constraints
            $table->integer('max_stay')->nullable();

            // Visitor-info toggles (D16)
            $table->boolean('accepts_pets')->default(false);
            $table->boolean('smoking_allowed')->default(false);
            $table->boolean('parties_allowed')->default(false);
            $table->boolean('wheelchair_accessible')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'instant_booking_enabled',
                'rental_agreement_required',
                'cancellation_policy_type',
                'max_stay',
                'accepts_pets',
                'smoking_allowed',
                'parties_allowed',
                'wheelchair_accessible',
            ]);
        });
    }
};
