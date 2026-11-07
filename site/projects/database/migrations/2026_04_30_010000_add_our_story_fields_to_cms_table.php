<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('cms', function (Blueprint $table) {
            $existing = array_column(Schema::getColumns('cms'), 'name');
            $columns = [
                ['story_block_label', 'text'],
                ['story_block_heading', 'text'],
                ['story_block_body', 'longText'],
                ['story_block_image', 'text'],
                ['story_block_author_name', 'text'],
                ['story_block_author_role', 'text'],
                ['quote_text', 'text'],
                ['milestones_label', 'text'],
                ['milestones_heading', 'text'],
                ['milestones_list', 'longText'],
                ['stats_list', 'longText'],
            ];
            foreach ($columns as [$name, $type]) {
                if (!in_array($name, $existing)) {
                    $table->{$type}($name)->nullable();
                }
            }
        });
    }

    public function down()
    {
        Schema::table('cms', function (Blueprint $table) {
            foreach (['story_block_label','story_block_heading','story_block_body','story_block_image','story_block_author_name','story_block_author_role','quote_text','milestones_label','milestones_heading','milestones_list','stats_list'] as $c) {
                if (Schema::hasColumn('cms', $c)) $table->dropColumn($c);
            }
        });
    }
};
