<?php

namespace App\Services\Booking;

use App\Models\BookingRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use ModelHelper;

final class RentalAgreementGenerator
{
    public function generate(BookingRequest $booking, array $signingData): string
    {
        $property = app(RentalAgreementPropertyResolver::class)->resolve($booking);
        if (!$property) {
            throw new \RuntimeException('Property not found for rental agreement.');
        }

        $templateHtml = $property->rental_agreement_template_html
            ?: ModelHelper::getDataFromSetting('rental_agreement_template_html');

        $houseRulesHtml = $property->rental_agreement_house_rules_html
            ?: ModelHelper::getDataFromSetting('rental_agreement_house_rules_html');

        if (empty($templateHtml)) {
            throw new \RuntimeException('No rental agreement template configured (per-property or global).');
        }

        $signing = array_merge($signingData, [
            'template_html'    => $templateHtml,
            'house_rules_html' => $houseRulesHtml ?: '',
        ]);

        $context = RentalAgreementContext::fromBooking($booking, $property, $signing);

        $renderedHtml = view('pdf.rental-agreement', ['ctx' => $context])->render();

        $pdf = Pdf::loadHTML($renderedHtml)->setPaper('a4');

        $relativePath = sprintf(
            'agreements/booking_%d_signed_%s.pdf',
            $booking->id,
            now()->format('YmdHis')
        );

        Storage::disk('local')->put($relativePath, $pdf->output());

        return $relativePath;
    }
}
