<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms')) {
            return;
        }

        Schema::table('cms', function (Blueprint $table) {
            foreach ([
                'ui_labels',
                'fw_section_sub',
            ] as $column) {
                if (!Schema::hasColumn('cms', $column)) {
                    $table->longText($column)->nullable();
                }
            }

            foreach ([
                'fw_banner_title',
                'fw_section_eyebrow',
                'fw_section_title',
            ] as $column) {
                if (!Schema::hasColumn('cms', $column)) {
                    $table->text($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        // No-op: these are baseline editable CMS fields used by clone templates.
    }
};
