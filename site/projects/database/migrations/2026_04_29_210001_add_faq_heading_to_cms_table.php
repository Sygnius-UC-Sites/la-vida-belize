<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('cms', function (Blueprint $table) {
            $cols = array_column(Schema::getColumns('cms'), 'name');
            if (!in_array('faq_heading', $cols)) {
                $table->string('faq_heading')->nullable()->after('faq_title');
            }
        });
    }

    public function down()
    {
        Schema::table('cms', function (Blueprint $table) {
            if (Schema::hasColumn('cms', 'faq_heading')) {
                $table->dropColumn('faq_heading');
            }
        });
    }
};
