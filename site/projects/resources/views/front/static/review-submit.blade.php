@extends("front.layouts.master")

@section("title", "Review your stay")

@section("container")
<section class="review-submit-section" style="padding:60px 0;">
    <div class="container" style="max-width:680px;">
        <h1>Review your stay at {{ $property->name ?? 'our property' }}</h1>
        <p class="text-muted">Stayed: {{ $testimonial->stay_date }}</p>

        @if(session('danger'))
            <div class="alert alert-danger">{{ session('danger') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ url('/review/' . $testimonial->submission_token) }}">
            @csrf

            <div class="form-group" style="margin: 24px 0;">
                <label><strong>Your rating (required)</strong></label>
                <div class="star-rating" style="font-size:32px; cursor:pointer; color:#ddd;">
                    @for($i=1; $i<=5; $i++)
                        <span class="star" data-value="{{ $i }}" style="margin-right:4px;">★</span>
                    @endfor
                </div>
                <input type="hidden" name="rating" id="rating-input" value="{{ old('rating', $testimonial->rating) }}" required>
            </div>

            <div class="form-group">
                <label for="review-name">Your name</label>
                <input type="text" id="review-name" class="form-control" value="{{ $testimonial->name }}" disabled>
            </div>

            <div class="form-group">
                <label for="review-message"><strong>Your review (required, min 20 characters)</strong></label>
                <textarea id="review-message" name="message" rows="6" class="form-control" minlength="20" maxlength="5000" required>{{ old('message', $testimonial->message) }}</textarea>
            </div>

            <x-spam-protection form="review" />

            <button type="submit" class="btn btn-primary main-btn" style="margin-top:18px;">Submit review</button>
        </form>
    </div>
</section>

@push('scripts')
<script>
$(function() {
    var $stars = $('.star-rating .star');
    var $input = $('#rating-input');

    function paint(value) {
        $stars.each(function() {
            var v = parseInt($(this).data('value'), 10);
            $(this).css('color', v <= value ? '#f5c518' : '#ddd');
        });
    }

    if ($input.val()) paint(parseInt($input.val(), 10));

    $stars.on('click', function() {
        var v = parseInt($(this).data('value'), 10);
        $input.val(v);
        paint(v);
    });
    $stars.on('mouseenter', function() {
        paint(parseInt($(this).data('value'), 10));
    });
    $('.star-rating').on('mouseleave', function() {
        paint(parseInt($input.val() || 0, 10));
    });
});
</script>
@endpush
@endsection
