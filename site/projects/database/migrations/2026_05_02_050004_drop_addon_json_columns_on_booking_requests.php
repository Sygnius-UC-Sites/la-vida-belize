<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = array_values(array_filter(
            ['rv_hookup_json', 'visitor_hookup_json', 'hottub_json', 'firewood_json'],
            fn (string $column) => Schema::hasColumn('booking_requests', $column)
        ));
        if ($columns) {
            Schema::table('booking_requests', function (Blueprint $t) use ($columns) {
                $t->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('booking_requests', function (Blueprint $t) {
            $t->longText('rv_hookup_json')->nullable();
            $t->longText('visitor_hookup_json')->nullable();
            $t->longText('hottub_json')->nullable();
            $t->longText('firewood_json')->nullable();
        });
    }
};
