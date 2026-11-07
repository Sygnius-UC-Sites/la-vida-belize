<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Testimonial;
use Illuminate\Http\Request;

class ReviewSubmissionController extends Controller
{
    public function show($token)
    {
        $testimonial = Testimonial::where('submission_token', $token)->first();
        abort_unless($testimonial, 404);

        if ($testimonial->status === 'true') {
            return view('front.static.review-thanks', ['msg' => 'Thanks — your review has already been received.']);
        }
        if ($testimonial->status === 'rejected') {
            return view('front.static.review-thanks', ['msg' => 'This review link is no longer active.']);
        }

        $property = Property::find($testimonial->property_id);
        return view('front.static.review-submit', compact('testimonial', 'property'));
    }

    public function submit(Request $request, $token)
    {
        $testimonial = Testimonial::where('submission_token', $token)->first();
        abort_unless($testimonial, 404);

        if (in_array($testimonial->status, ['true', 'rejected'])) {
            return redirect('/review/' . $token);
        }

        $spam = app(\App\Services\Spam\SpamProtection::class)->check($request, 'review');
        if (!$spam->passed) {
            if ($spam->silent) {
                return view('front.static.review-thanks', ['msg' => 'Thank you — your review has been submitted and will appear after moderation.']);
            }
            return redirect('/review/' . $token)->with('danger', $spam->userMessage)->withInput();
        }

        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'message' => 'required|string|min:20|max:5000',
        ]);

        $testimonial->update([
            'rating'        => $validated['rating'],
            'score'         => (string) $validated['rating'],
            'message'       => $validated['message'],
            'status'        => 'false',
            'submitted_at'  => now(),
            'submitted_ip'  => $request->ip(),
        ]);

        return view('front.static.review-thanks', ['msg' => 'Thank you — your review has been submitted and will appear after moderation.']);
    }
}
