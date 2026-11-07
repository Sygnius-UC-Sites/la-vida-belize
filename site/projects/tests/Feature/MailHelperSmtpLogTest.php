<?php

namespace Tests\Feature;

use App\Helper\MailHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailHelperSmtpLogTest extends TestCase
{
    public function test_smtp_fallback_writes_mail_send_log(): void
    {
        Mail::fake();
        DB::table('basic_settings')->updateOrInsert(['name' => 'mail_enabled'], ['value' => '0']);
        DB::table('basic_settings')->updateOrInsert(['name' => 'mail_from'], ['value' => 'noreply@example.test']);
        DB::table('basic_settings')->updateOrInsert(['name' => 'mail_from_name'], ['value' => 'Example']);

        $sent = app(MailHelper::class)->emailSenderByController(
            '<p>Operations smoke email.</p>',
            'ops@example.test',
            'Operations smoke email'
        );

        $this->assertTrue($sent);
        $this->assertDatabaseHas('mail_send_log', [
            'purpose' => 'transactional',
            'to_email' => 'ops@example.test',
            'subject' => 'Operations smoke email',
            'status' => 'sent',
        ]);
    }
}
