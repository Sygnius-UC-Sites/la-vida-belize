@extends("front.layouts.master")
@section("title","404 - Page not found")
@section("keywords","404 - Page not found")
@section("description","404 - Page not found")
@section("container")

    @php
        $name='404 - Page Not Found';
        $bannerImage=asset('front/images/breadcrumb.webp');
        $payment_currency = $setting_data['payment_currency'] ?? '$';
    @endphp
    @include("front.layouts.banner")
  
    <!-- end banner sec -->
      <section class="error">
         <div class="container">
            <div class="row m-0">
                    <h2>404</h2>
                    <h3>Oops.. Page Not Found</h3>
                    <p>You can search for the page you want here or return to the homepage.</p>
                    <a href="{{ url('/') }}" class="main-btn">Go Home</a>

              
            </div>
         </div>
      </section>


@stop
