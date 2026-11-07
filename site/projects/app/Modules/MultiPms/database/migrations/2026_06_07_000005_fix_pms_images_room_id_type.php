<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_images', function (Blueprint $table) {
            $table->string('pms_room_id')->change();
            if (!Schema::hasColumn('pms_images', 'pms_property_id'))
                $table->string('pms_property_id')->nullable()->after('pms_room_id');
            if (!Schema::hasColumn('pms_images', 'text'))
                $table->string('text')->nullable()->after('pms_property_id');
        });
    }

    public function down(): void {}
};
