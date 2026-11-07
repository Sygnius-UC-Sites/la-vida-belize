<?php

namespace App\Http\Controllers\Admin;

use App\Helper\Upload;
use App\Http\Controllers\Controller;
use App\Services\Booking\WordDocConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RentalAgreementSettingsController extends Controller
{
    /** Phase 2 of the rental-agreement rebuild — accepted upload extensions
     *  for the Word→HTML converter. Mirrors WordDocConverter::ALLOWED_EXTS. */
    private const ACCEPTED_DOC_EXTS = ['docx', 'doc', 'rtf', 'odt'];

    /** 5 MB cap (per the plan's open-question answer). Bigger contracts than
     *  this almost always indicate a corrupted upload, not a legitimate doc. */
    private const MAX_DOC_BYTES = 5 * 1024 * 1024;

    public function edit()
    {
        $rows = DB::table('basic_settings')
            ->whereIn('name', [
                'rental_agreement_template_html',
                'rental_agreement_house_rules_html',
                'rental_agreement_default_enabled',
                'rental_agreement_source_doc_path',
                'rental_agreement_signing_mode_global',
                // Phase 5 — editable email-template subject/body pairs.
                'rental_agreement_request_default_subject',
                'rental_agreement_request_default_body_html',
                'rental_agreement_signed_default_subject',
                'rental_agreement_signed_default_body_html',
                'admin_alert_rental_agreement_subject',
                'admin_alert_rental_agreement_body_html',
            ])
            ->pluck('value', 'name')
            ->toArray();

        // Email-attachment groups for the inline helper. Keyed by email type
        // so the partial can render the right attachments under each section.
        $attachments = \App\Models\EmailAttachment::orderBy('email_type')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('email_type');

        return view('admin.settings.rental-agreement', [
            'template_html'                  => $rows['rental_agreement_template_html'] ?? '',
            'house_rules_html'               => $rows['rental_agreement_house_rules_html'] ?? '',
            'default_enabled'                => $rows['rental_agreement_default_enabled'] ?? '0',
            'source_doc_path'                => $rows['rental_agreement_source_doc_path'] ?? '',
            'signing_mode_global'            => $rows['rental_agreement_signing_mode_global'] ?? 'pre_payment',
            'request_subject'                => $rows['rental_agreement_request_default_subject'] ?? '',
            'request_body_html'              => $rows['rental_agreement_request_default_body_html'] ?? '',
            'signed_subject'                 => $rows['rental_agreement_signed_default_subject'] ?? '',
            'signed_body_html'               => $rows['rental_agreement_signed_default_body_html'] ?? '',
            'admin_alert_signed_subject'     => $rows['admin_alert_rental_agreement_subject'] ?? '',
            'admin_alert_signed_body_html'   => $rows['admin_alert_rental_agreement_body_html'] ?? '',
            'attachments'                    => $attachments,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'template_html'                  => 'required|string',
            'house_rules_html'               => 'nullable|string',
            'default_enabled'                => 'nullable|in:0,1',
            'signing_mode_global'            => 'nullable|in:pre_payment,post_confirmation',
            'request_subject'                => 'nullable|string',
            'request_body_html'              => 'nullable|string',
            'signed_subject'                 => 'nullable|string',
            'signed_body_html'               => 'nullable|string',
            'admin_alert_signed_subject'     => 'nullable|string',
            'admin_alert_signed_body_html'   => 'nullable|string',
        ]);

        foreach ([
            'rental_agreement_template_html'           => $validated['template_html'],
            'rental_agreement_house_rules_html'        => $validated['house_rules_html'] ?? '',
            'rental_agreement_default_enabled'         => $validated['default_enabled'] ?? '0',
            'rental_agreement_signing_mode_global'     => $validated['signing_mode_global'] ?? 'pre_payment',
            'rental_agreement_request_default_subject' => $validated['request_subject'] ?? '',
            'rental_agreement_request_default_body_html' => $validated['request_body_html'] ?? '',
            'rental_agreement_signed_default_subject'  => $validated['signed_subject'] ?? '',
            'rental_agreement_signed_default_body_html' => $validated['signed_body_html'] ?? '',
            'admin_alert_rental_agreement_subject'     => $validated['admin_alert_signed_subject'] ?? '',
            'admin_alert_rental_agreement_body_html'   => $validated['admin_alert_signed_body_html'] ?? '',
        ] as $name => $value) {
            DB::table('basic_settings')->updateOrInsert(
                ['name' => $name],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        return redirect()->back()->with('success', 'Rental agreement settings updated.');
    }

    /**
     * Phase 2 — POST /admin/settings/rental-agreement/convert-doc
     *
     * Accepts a Word/RTF/ODT upload, converts it to clean HTML via
     * WordDocConverter, optionally persists the source path (so the admin
     * can re-download the original), and returns the HTML so the caller
     * can pre-fill CKEditor without a page reload.
     *
     * Used from BOTH the global rental-agreement settings page AND the
     * per-property edit form (the `?context=property&property_id=X` query
     * tells us which `*_source_doc_path` field to update).
     */
    public function convertDoc(Request $request)
    {
        $request->validate([
            'file'        => 'required|file|max:' . (self::MAX_DOC_BYTES / 1024),
            'context'     => 'nullable|in:global,property',
            'property_id' => 'nullable|integer|exists:properties,id',
        ]);
        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension() ?: '');
        if (!in_array($ext, self::ACCEPTED_DOC_EXTS, true)) {
            return response()->json([
                'ok'    => false,
                'error' => 'Unsupported file type. Allowed: ' . implode(', ', self::ACCEPTED_DOC_EXTS),
            ], 422);
        }

        // Capture original-name + size BEFORE Upload::fileUpload() moves the
        // temp file. After move, the source path is gone and any later call
        // to $file->getSize() / $file->getRealPath() throws "stat failed".
        $origName = $file->getClientOriginalName();
        $origSize = $file->getSize();

        // Persist into uploads/rental-agreements/ via the same convention every
        // other upload uses, so the file is reachable from public_path() and
        // the admin can later re-download via a regular asset URL.
        $relPath = Upload::fileUpload($file, 'rental-agreements');
        $absPath = base_path('../' . $relPath);

        $report = app(WordDocConverter::class)->convertToHtmlWithReport($absPath);
        if ($report === null) {
            // Cleanup — the file we just uploaded is unusable.
            @unlink($absPath);
            return response()->json([
                'ok'    => false,
                'error' => "Couldn't read that document. Try saving as .docx and re-uploading.",
            ], 422);
        }
        $html = $report['html'];

        // Persist the source path so the admin sees the original filename and
        // can replace it later. Two scopes: 'global' (BasicSetting row) or
        // 'property' (per-property column).
        $context    = (string) $request->input('context', 'global');
        $propertyId = (int) $request->input('property_id', 0);
        if ($context === 'property' && $propertyId) {
            DB::table('properties')->where('id', $propertyId)->update([
                'rental_agreement_source_doc_path' => $relPath,
                'rental_agreement_template_html'   => $html,
                'updated_at'                        => now(),
            ]);
        } else {
            DB::table('basic_settings')->updateOrInsert(
                ['name' => 'rental_agreement_source_doc_path'],
                ['value' => $relPath, 'updated_at' => now()]
            );
            // Note: we do NOT auto-save template_html here — let the admin
            // review the converted HTML in CKEditor and click Save themselves.
            // That way they can tweak before committing.
        }

        return response()->json([
            'ok'                  => true,
            'html'                => $html,
            'source_doc_path'     => $relPath,
            'source_doc_filename' => $origName,
            'source_doc_size'     => $origSize,
            // Phase 2 smart-tokenize report. Tells the admin how many of the
            // recognised placeholder patterns ([Guest Name], "Name: ___", etc.)
            // were converted to {{guest_name}}-style merge tokens.
            'tokens_replaced'     => $report['tokens_replaced'],
            'tokens_breakdown'    => $report['tokens'],
        ]);
    }
}
