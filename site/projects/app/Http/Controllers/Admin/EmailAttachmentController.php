<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmailAttachmentController extends Controller
{
    /** Allowed email-type slugs — must match the templatePreview routes. */
    private const TYPES = [
        'welcome', 'checkin', 'checkout', 'review', 'cancellation',
        'awaiting-payment', 'confirmed', 'enquiry',
        'installment2', 'installment3', 'receipt',
    ];

    /** Hard caps to keep Gmail/Outlook delivery stable (most providers reject >25MB). */
    private const MAX_FILE_BYTES   = 10 * 1024 * 1024;  // 10 MB per file
    private const MAX_TOTAL_BYTES  = 20 * 1024 * 1024;  // 20 MB total per type

    /** File extensions safe to attach to a customer email. */
    private const ALLOWED_EXTS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'rtf', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'heic',
        'zip', 'mp3', 'mp4', 'mov', 'wav', 'ics',
    ];

    public function store(Request $request, string $type)
    {
        if (!in_array($type, self::TYPES, true)) {
            return response()->json(['ok' => false, 'error' => 'Unknown email type.'], 404);
        }
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:' . (self::MAX_FILE_BYTES / 1024), // Laravel max is in KB
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'error' => $validator->errors()->first()], 422);
        }

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension() ?: 'bin');
        if (!in_array($ext, self::ALLOWED_EXTS, true)) {
            return response()->json(['ok' => false, 'error' => 'File type .' . $ext . ' is not allowed.'], 422);
        }

        $existingTotal = (int) EmailAttachment::where('email_type', $type)->sum('file_size');
        if ($existingTotal + $file->getSize() > self::MAX_TOTAL_BYTES) {
            return response()->json([
                'ok' => false,
                'error' => 'Adding this file would exceed the 20 MB total limit for this email. Remove an existing attachment first.',
            ], 422);
        }

        $relPath = \App\Helper\Upload::fileUpload($file, 'email-attachments');

        $row = EmailAttachment::create([
            'email_type'    => $type,
            'file_path'     => $relPath,
            'original_name' => $file->getClientOriginalName(),
            'file_size'     => $file->getSize(),
            'mime_type'     => $file->getClientMimeType(),
            'sort_order'    => (int) (EmailAttachment::where('email_type', $type)->max('sort_order') ?? 0) + 1,
        ]);

        return response()->json([
            'ok' => true,
            'attachment' => [
                'id'            => $row->id,
                'original_name' => $row->original_name,
                'human_size'    => $row->humanSize(),
                'mime_type'     => $row->mime_type,
            ],
        ]);
    }

    public function destroy(int $id)
    {
        $row = EmailAttachment::find($id);
        if (!$row) {
            return response()->json(['ok' => false, 'error' => 'Attachment not found.'], 404);
        }
        // Best-effort delete on disk; the row goes regardless so admins
        // never get stuck with a phantom attachment they can't remove.
        $abs = public_path($row->file_path);
        if (is_file($abs)) {
            @unlink($abs);
        }
        $row->delete();
        return response()->json(['ok' => true]);
    }
}
