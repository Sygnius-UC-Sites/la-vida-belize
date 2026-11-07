<?php

namespace App\Http\Controllers;

use App\Models\BookingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RentalAgreementDownloadController extends Controller
{
    public function guestDownload(Request $request, $id)
    {
        $booking = BookingRequest::findOrFail($id);
        abort_unless($booking->rental_agreement_pdf_path, 404);

        $expected = hash_hmac(
            'sha256',
            $id . '|' . (string) $booking->rental_agreement_signed_at,
            config('app.key')
        );
        abort_unless(hash_equals($expected, (string) $request->query('sig')), 403);

        return Storage::disk('local')->download(
            $booking->rental_agreement_pdf_path,
            sprintf('agreement-booking-%d.pdf', $id),
            ['Content-Type' => 'application/pdf']
        );
    }
}
