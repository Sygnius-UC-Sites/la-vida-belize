<?php

use Database\Seeders\LaVidaAttractionsSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new LaVidaAttractionsSeeder())->run();
    }

    public function down(): void
    {
        // Keep seeded guest content; deleting would empty the live attractions grid.
    }
};
