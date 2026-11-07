@extends('front.layouts.brad')
@section("title",$data->meta_title)
@section("keywords",$data->meta_keywords)
@section("description",$data->meta_description)
@section('body-class', 'page booking-page')

@section('head')
@include('front.partials.booking-funnel-head')
@endsection

@section('content')

    @php
        $name=$data->name;
        $bannerImage=asset('front/images/internal-banner.webp');
          $payment_currency= $setting_data['payment_currency'];
    @endphp
    <!-- start banner sec -->
@include("front.layouts.banner")


<section class="booking-reciept">
<div class="container">
    <div class="row">
        <div class="top-area">
            <div class="icon">
                <i class="fa-solid fa-check"></i>
            </div>
                 <h3>Congratulations</h3>
            </div>
        <div class="jumbotron">
            
            <div class="quote-pro">
                        <div class="pro-img">
                            <img src="{{ asset($property->feature_image)}}" class="img-fluid" alt="{{$property->name}}" title="{{$property->name}}">
                        </div>
                        <div class="pro-cont">
                            <p class="home-type">{{$property->heading}}</p>
                            <h4 class="pro-name">{{$property->name}}</h4>
                             <div class="ammenity-home">
                              @if($property->sleeps)
                                    <span>{{$property->sleeps}} Sleeps</span><span> · </span>
                                @endif
                                @if($property->bedroom)
                                    <span>{{$property->bedroom}} bedrooms</span><span> · </span>
                                @endif
                                @if($property->bathroom)
                                    <span>{{$property->bathroom}} bathrooms</span>
                                @endif</div>
                        </div>
                    </div>
     
          <div class="book-area">
              <h6>Your booking is confirmed. You will receive an email with further details.</h6>
              <p class="shortly"><span class="book-no">Your Booking No # is: {{$booking['id']}}</p>
          
   
                
          </div>
          
        </div>
        <div class="btn-area"><a href="{{ url('/') }}" class="btn main-btn"><span>Home</span></a></div>
    </div>
</div>
</section>
@stop