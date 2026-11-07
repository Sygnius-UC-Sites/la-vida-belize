<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Files admins upload to be appended to specific lifecycle emails (welcome
 * package PDFs, house manuals, check-in maps, etc.). Owned by `email_type`
 * — at send time the helper loads every attachment for the matching type
 * and passes their absolute paths to MailHelper / Mailables.
 */
class EmailAttachment extends Model
{
    protected $table = 'email_attachments';

    protected $fillable = [
        'email_type',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
        'sort_order',
    ];

    /**
     * Absolute filesystem path the mailer can attach. Returns null if the
     * file went missing on disk after the row was created — the send
     * pipeline must skip those rather than crash the whole send.
     */
    public function absolutePath(): ?string
    {
        $abs = public_path($this->file_path);
        return is_file($abs) ? $abs : null;
    }

    /** Human-friendly size, e.g. "2.4 MB". */
    public function humanSize(): string
    {
        $bytes = (int) ($this->file_size ?? 0);
        if ($bytes <= 0) return '—';
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
        return number_format($bytes, $i ? 1 : 0) . ' ' . $units[$i];
    }

    /**
     * Loaded once per send so the mailer can attach. Type-keyed.
     *
     * @return array<int, array{path:string, name:string}>
     */
    public static function pathsFor(string $type): array
    {
        $out = [];
        foreach (self::where('email_type', $type)->orderBy('sort_order')->orderBy('id')->get() as $a) {
            $abs = $a->absolutePath();
            if ($abs) $out[] = ['path' => $abs, 'name' => $a->original_name];
        }
        return $out;
    }
}
