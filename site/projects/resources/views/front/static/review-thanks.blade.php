@extends("front.layouts.master")

@section("title", "Thank you")

@section("container")
<section style="padding:80px 0; text-align:center;">
    <div class="container" style="max-width:600px;">
        <h1>Thanks!</h1>
        <p style="font-size:18px; margin-top:20px;">{{ $msg ?? 'Your review has been received.' }}</p>
        <a href="{{ url('/') }}" class="btn btn-primary main-btn" style="margin-top:30px;">Back to home</a>
    </div>
</section>
@endsection
