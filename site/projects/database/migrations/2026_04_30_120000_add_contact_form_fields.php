<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('contactus_requests', function (Blueprint $t) {
            foreach (['property','property_slug','intent','check_in','check_out'] as $c) {
                if (!Schema::hasColumn('contactus_requests', $c)) {
                    $t->string($c)->nullable();
                }
            }
        });

        Schema::table('cms', function (Blueprint $t) {
            if (!Schema::hasColumn('cms', 'getting_here_steps'))           $t->longText('getting_here_steps')->nullable();
            if (!Schema::hasColumn('cms', 'getting_here_map_iframe_src'))  $t->longText('getting_here_map_iframe_src')->nullable();
            if (!Schema::hasColumn('cms', 'getting_here_directions_url'))  $t->text('getting_here_directions_url')->nullable();
        });
    }

    public function down(): void {
        Schema::table('contactus_requests', function (Blueprint $t) {
            foreach (['property','property_slug','intent','check_in','check_out'] as $c) {
                if (Schema::hasColumn('contactus_requests', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
        Schema::table('cms', function (Blueprint $t) {
            foreach (['getting_here_steps','getting_here_map_iframe_src','getting_here_directions_url'] as $c) {
                if (Schema::hasColumn('cms', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
