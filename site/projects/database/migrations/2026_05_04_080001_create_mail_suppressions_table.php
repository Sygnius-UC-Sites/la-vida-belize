<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mail_suppressions', function (Blueprint $t) {
            $t->id();
            $t->string('email', 255)->unique();
            $t->string('reason', 32);                       // bounce_permanent | bounce_transient | complaint | manual
            $t->string('bounce_subtype', 64)->nullable();   // SES diagnostic, e.g. "General", "MailboxFull"
            $t->text('diagnostic')->nullable();
            $t->string('source', 32)->default('webhook');   // webhook | admin | seed
            $t->timestamps();

            $t->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_suppressions');
    }
};
