{{-- Signed Rental Agreement — legal-defensible PDF rendering.
     Phase 5 of the rental-agreement rebuild
     (plan: docs/superpowers/plans/2026-05-08-rental-agreement-rebuild.md).

     Sections:
       • Header — brand, document ID, booking ref, dates
       • Parties block — guest + brand identity (who signed what)
       • Trip details — what was agreed for
       • Agreement body — owner's template with merge tokens substituted
       • House rules — appended if set
       • Signature block — typed name + drawn signature image + date
       • Electronic-signature audit — IP, user-agent, hashes, ESIGN disclosure
       • Page footer — doc ID + page numbers + truncated hash on every page

     Audit data persists for legal reference; the SHA-256 fingerprint over
     the agreement body + signing timestamp + consent text means any post-
     signing alteration is detectable. --}}
@php
    $brand = $ctx->brandContact();
    $docId = $ctx->documentId();
    $hash  = $ctx->documentHash();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Rental Agreement — {{ $docId }}</title>
    <style>
        /* Page setup — A4 with footer space. DomPDF reads @page rules at the
           start of the document; the .footer block is rendered absolutely on
           every page via the script-tag at the bottom. */
        @page { margin: 60px 50px 90px 50px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5pt;
            color: #1f2937;
            line-height: 1.55;
        }

        /* ─── Header ─── */
        .doc-header {
            border-bottom: 2px solid #d9a24a;
            padding-bottom: 14px;
            margin-bottom: 24px;
        }
        .doc-header .brand-row {
            display: block;
            margin-bottom: 6px;
            color: #7a5818;
            font-size: 9.5pt;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .doc-header h1 {
            margin: 0;
            color: #0b2820;
            font-size: 22pt;
            font-weight: 700;
            letter-spacing: -0.01em;
        }
        .doc-header .doc-id {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 9pt;
            color: #6b7280;
            margin-top: 4px;
        }

        /* ─── Parties block ─── */
        .parties-block {
            background: #faf3e6;
            border: 1px solid #e8c896;
            border-radius: 6px;
            padding: 14px 18px;
            margin: 0 0 22px;
        }
        .parties-block .party { display: block; margin-bottom: 8px; }
        .parties-block .party:last-child { margin-bottom: 0; }
        .parties-block .party-label {
            display: block;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #7a5818;
            font-weight: 700;
        }
        .parties-block .party-value {
            display: block;
            color: #0b2820;
            font-weight: 600;
            margin-top: 2px;
        }
        .parties-block .party-meta {
            font-size: 9pt;
            color: #6b7280;
            margin-top: 2px;
        }

        /* ─── Trip summary ─── */
        .trip-summary {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 22px;
            font-size: 10pt;
        }
        .trip-summary table { width: 100%; border-collapse: collapse; }
        .trip-summary td {
            padding: 4px 8px;
            vertical-align: top;
        }
        .trip-summary td.label {
            color: #6b7280;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            width: 32%;
        }

        /* ─── Agreement body ─── */
        .agreement-body { margin: 24px 0; }
        .agreement-body h2,
        .agreement-body h3 { color: #0b2820; }
        .agreement-body p { margin: 8px 0; }

        /* ─── House rules ─── */
        .house-rules {
            margin-top: 24px;
            padding: 16px 18px;
            background: #fffbeb;
            border-left: 4px solid #d9a24a;
            border-radius: 0 6px 6px 0;
        }
        .house-rules h2 {
            margin: 0 0 8px 0;
            color: #7a5818;
            font-size: 13pt;
        }

        /* ─── Signature block ─── */
        .signature-block {
            margin-top: 36px;
            padding-top: 20px;
            border-top: 1px solid #cbd5e1;
            page-break-inside: avoid;
        }
        .signature-block h2 {
            margin: 0 0 12px 0;
            color: #0b2820;
            font-size: 13pt;
        }
        .signature-block .signed-by {
            margin: 8px 0 12px;
            font-size: 11pt;
        }
        .signature-img {
            border-bottom: 1px solid #94a3b8;
            padding-bottom: 4px;
            max-width: 320px;
            max-height: 110px;
        }
        .signature-meta {
            margin-top: 10px;
            font-size: 9.5pt;
            color: #6b7280;
        }

        /* ─── Electronic-signature audit ─── */
        .audit-block {
            margin-top: 28px;
            padding: 14px 16px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 9pt;
            color: #475569;
            page-break-inside: avoid;
        }
        .audit-block h3 {
            margin: 0 0 10px 0;
            font-size: 10pt;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .audit-block table { width: 100%; border-collapse: collapse; }
        .audit-block td {
            padding: 3px 6px;
            vertical-align: top;
            border-bottom: 1px solid #e2e8f0;
        }
        .audit-block td.k {
            color: #64748b;
            font-weight: 600;
            width: 32%;
            white-space: nowrap;
        }
        .audit-block .hash {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 8pt;
            word-break: break-all;
            color: #0f172a;
        }
        .audit-block blockquote {
            margin: 6px 0 0;
            padding: 8px 10px;
            background: #fff;
            border-left: 3px solid #d9a24a;
            font-style: italic;
            font-size: 9pt;
            color: #1f2937;
        }
        .esign-disclosure {
            margin-top: 18px;
            padding: 12px 14px;
            background: #fff;
            border: 1px dashed #94a3b8;
            border-radius: 4px;
            font-size: 8.5pt;
            color: #475569;
            line-height: 1.5;
        }

        /* ─── Page footer (every page, fixed-positioned) ─── */
        .footer {
            position: fixed;
            bottom: -60px;
            left: 0;
            right: 0;
            height: 50px;
            padding: 6px 0;
            border-top: 1px solid #cbd5e1;
            font-size: 8pt;
            color: #6b7280;
            font-family: DejaVu Sans, sans-serif;
        }
        .footer table { width: 100%; }
        .footer td.left  { text-align: left; }
        .footer td.right { text-align: right; }
        .footer .doc-meta-line { font-family: DejaVu Sans Mono, monospace; }
    </style>
</head>
<body>

{{-- ─── HEADER ─── --}}
<div class="doc-header">
    <span class="brand-row">{{ $brand['brand'] }}</span>
    <h1>Rental Agreement</h1>
    <div class="doc-id">Document ID: <strong>{{ $docId }}</strong>
        &nbsp;·&nbsp; Booking #{{ $ctx->booking_id }}
        &nbsp;·&nbsp; Generated {{ $ctx->generatedAtDisplay() }}
    </div>
</div>

{{-- ─── PARTIES ─── --}}
<div class="parties-block">
    <div class="party">
        <span class="party-label">Property owner / operator</span>
        <span class="party-value">{{ $brand['brand'] }}</span>
        @if(!empty($ctx->owner_name) && $ctx->owner_name !== $brand['brand'])
            <span class="party-meta">Operator: {{ $ctx->owner_name }}</span>
        @endif
        @if(!empty($brand['address']))
            <span class="party-meta">{{ $brand['address'] }}</span>
        @endif
        @if(!empty($brand['email']) || !empty($brand['phone']))
            <span class="party-meta">{{ $brand['email'] }}@if($brand['email'] && $brand['phone']) · @endif{{ $brand['phone'] }}</span>
        @endif
    </div>
    <div class="party">
        <span class="party-label">Guest / signer</span>
        <span class="party-value">{{ $ctx->guest_name }}</span>
        <span class="party-meta">{{ $ctx->guest_email }}@if($ctx->guest_phone) · {{ $ctx->guest_phone }}@endif</span>
    </div>
</div>

{{-- ─── TRIP SUMMARY ─── --}}
<div class="trip-summary">
    <table>
        <tr>
            <td class="label">Property</td><td>{{ $ctx->property_name }}</td>
        </tr>
        @if($ctx->property_address)
        <tr>
            <td class="label">Address</td><td>{{ $ctx->property_address }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">Check-in</td><td>{{ \Carbon\Carbon::parse($ctx->check_in)->format('D, M j, Y') }}</td>
        </tr>
        <tr>
            <td class="label">Check-out</td><td>{{ \Carbon\Carbon::parse($ctx->check_out)->format('D, M j, Y') }}</td>
        </tr>
        <tr>
            <td class="label">Length of stay</td><td>{{ $ctx->nights }} night{{ $ctx->nights === 1 ? '' : 's' }}</td>
        </tr>
        <tr>
            <td class="label">Guests</td>
            <td>
                {{ $ctx->adults }} adult{{ $ctx->adults === 1 ? '' : 's' }}
                @if($ctx->children > 0), {{ $ctx->children }} {{ \Illuminate\Support\Str::plural('child', $ctx->children) }} @endif
                @if($ctx->pets > 0), {{ $ctx->pets }} pet{{ $ctx->pets === 1 ? '' : 's' }} @endif
            </td>
        </tr>
        @if($ctx->total_amount > 0)
        <tr>
            <td class="label">Total</td><td><strong>{{ $ctx->currency }} {{ number_format($ctx->total_amount, 2) }}</strong></td>
        </tr>
        @endif
    </table>
</div>

{{-- ─── AGREEMENT BODY ─── --}}
<div class="agreement-body">
    {!! $ctx->applyMergeFields($ctx->template_html) !!}
</div>

@if(!empty($ctx->house_rules_html))
<div class="house-rules">
    <h2>House Rules</h2>
    {!! $ctx->applyMergeFields($ctx->house_rules_html) !!}
</div>
@endif

{{-- ─── SIGNATURE ─── --}}
<div class="signature-block">
    <h2>Signed by Guest</h2>
    <p class="signed-by"><strong>{{ $ctx->guest_name }}</strong> &lt;{{ $ctx->guest_email }}&gt;</p>
    <img class="signature-img" src="{{ $ctx->signature_data_url }}" alt="Guest signature">
    <p class="signature-meta">Signed on {{ $ctx->signedAtDisplay() }}</p>
</div>

{{-- ─── AUDIT (LEGAL HEFT) ─── --}}
<div class="audit-block">
    <h3>Electronic Signature Audit</h3>
    <table>
        <tr>
            <td class="k">Document ID</td><td class="hash">{{ $docId }}</td>
        </tr>
        <tr>
            <td class="k">Document fingerprint (SHA-256)</td><td class="hash">{{ $hash }}</td>
        </tr>
        <tr>
            <td class="k">Booking reference</td><td>#{{ $ctx->booking_id }}</td>
        </tr>
        <tr>
            <td class="k">Signer name</td><td>{{ $ctx->guest_name }}</td>
        </tr>
        <tr>
            <td class="k">Signer email</td><td>{{ $ctx->guest_email }}</td>
        </tr>
        @if($ctx->guest_phone)
        <tr>
            <td class="k">Signer phone</td><td>{{ $ctx->guest_phone }}</td>
        </tr>
        @endif
        <tr>
            <td class="k">Signed at (UTC)</td><td>{{ $ctx->signed_at_iso }}</td>
        </tr>
        <tr>
            <td class="k">Signing IP address</td><td>{{ $ctx->signed_ip }}</td>
        </tr>
        <tr>
            <td class="k">User agent (browser)</td><td style="word-break:break-all;">{{ $ctx->signed_user_agent }}</td>
        </tr>
        <tr>
            <td class="k">Document generated</td><td>{{ $ctx->generatedAtDisplay() }}</td>
        </tr>
    </table>

    <p style="margin-top: 10px;"><strong>Consent statement (verbatim, as displayed at signing):</strong></p>
    <blockquote>{{ $ctx->signed_consent_text }}</blockquote>

    <div class="esign-disclosure">
        <strong>Electronic Signature Disclosure.</strong>
        By drawing a signature and clicking the consent confirmation, the signer
        agreed under the U.S. ESIGN Act, Uniform Electronic Transactions Act
        (UETA), and equivalent international electronic-records statutes, that
        an electronic signature has the same legal force as a hand-written
        signature on this document. The audit data above (IP address, user
        agent, timestamp, and consent text) was captured at the moment of
        signing and is preserved with this document. The SHA-256 fingerprint
        cryptographically attests to the integrity of the agreement body — any
        modification to the signed text after the timestamp shown produces a
        different fingerprint when re-computed.
    </div>
</div>

{{-- ─── PAGE FOOTER (renders on every page) ─── --}}
<div class="footer">
    <table>
        <tr>
            <td class="left">
                <span class="doc-meta-line">{{ $docId }}</span><br>
                {{ $brand['brand'] }} · Booking #{{ $ctx->booking_id }}
            </td>
            <td class="right">
                Hash: <span class="doc-meta-line">{{ substr($hash, 0, 16) }}…</span><br>
                Page <script type="text/php">
                    if (isset($pdf)) {
                        $font = $fontMetrics->getFont('DejaVu Sans');
                        $pdf->page_text(490, 798, "{PAGE_NUM} of {PAGE_COUNT}", $font, 8, [0.42, 0.45, 0.5]);
                    }
                </script>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
