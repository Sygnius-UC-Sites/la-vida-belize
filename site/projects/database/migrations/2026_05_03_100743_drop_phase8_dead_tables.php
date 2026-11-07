<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 8 — drop tables behind features deleted wholesale.
     * Backed up to projects/storage/backups/pre_phase8_*.sql.
     */
    public function up(): void
    {
        Schema::dropIfExists('direct_booking_benefits');
        Schema::dropIfExists('our_clients');
        Schema::dropIfExists('services');
        Schema::dropIfExists('check_in_configs');
        Schema::dropIfExists('newsletters');
        Schema::dropIfExists('welcome_packages');
        Schema::dropIfExists('blogs');
        Schema::dropIfExists('blog_categories');
        Schema::dropIfExists('landing_cms');
        Schema::dropIfExists('seo_pages');
    }

    public function down(): void
    {
        throw new \RuntimeException(
            'Phase 8 destructive cleanup; restore from projects/storage/backups/pre_phase8_*.sql instead of rolling back.'
        );
    }
};
