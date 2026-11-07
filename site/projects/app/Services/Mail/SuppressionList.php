<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\DB;

class SuppressionList
{
    public function isSuppressed(string $email): bool
    {
        return DB::table('mail_suppressions')->where('email', strtolower(trim($email)))->exists();
    }

    public function add(string $email, string $reason, ?string $bounceSubtype = null, ?string $diagnostic = null, string $source = 'webhook'): void
    {
        $email = strtolower(trim($email));
        if ($email === '') return;

        DB::table('mail_suppressions')->updateOrInsert(
            ['email' => $email],
            [
                'reason'         => $reason,
                'bounce_subtype' => $bounceSubtype,
                'diagnostic'     => $diagnostic,
                'source'         => $source,
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );
    }

    public function remove(string $email): void
    {
        DB::table('mail_suppressions')->where('email', strtolower(trim($email)))->delete();
    }
}
