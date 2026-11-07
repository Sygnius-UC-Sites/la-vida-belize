<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = array_values(array_filter(
            ['one_pet_fee', 'two_pet_fee', 'three_pet_fee', 'four_pet_fee', 'five_pet_fee'],
            fn (string $column) => Schema::hasColumn('properties', $column)
        ));
        if ($columns) {
            Schema::table('properties', function (Blueprint $t) use ($columns) {
                $t->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $t) {
            $t->double('one_pet_fee')->nullable();
            $t->double('two_pet_fee')->nullable();
            $t->double('three_pet_fee')->nullable();
            $t->double('four_pet_fee')->nullable();
            $t->double('five_pet_fee')->nullable();
        });
    }
};
