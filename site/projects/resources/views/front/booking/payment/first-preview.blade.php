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
                 <h3 style="color:#c6a867;">(Be sure to check your Spam or Junk Folder for our reply, as it might land there)</h3>
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
                                @if($property->beds)
                                    <span>{{$property->beds}} Individual Beds</span><span> · </span>
                                @endif
                                @if($property->bathroom)
                                    <span>{{$property->bathroom}} bathrooms</span>
                                @endif</div>
                        </div>
                    </div>
           <div class="middle-area">
          <h6>Payment Detail</h6>
          <div class="book-rec-detail">
              <div class="cont">
                  <div class="left">Payment Date:</div>
                  <div class="right">{{ date('F d-Y',strtotime($payment->created_at)) }}</div>
              </div>
               <div class="cont">
                  <div class="left">Amount:</div>
                  <div class="right">{!! $payment_currency !!}{{ number_format($payment->amount,2) }}</div>
              </div>
              <div class="cont">
                  <div class="left">Payment Mode:</div>
                  <div class="right">{{$payment->type }}</div>
              </div>
              <div class="cont">
                  <div class="left">Tran ID:</div>
                  <div class="right">{{$payment->tran_id }}</div>
              </div>
               @if($payment->balance_transaction)
              <div class="cont">
                  <div class="left">Balance transaction ID:</div>
                  <div class="right">{{$payment->balance_transaction }}</div>
              </div>
               @endif
                @if($payment->receipt_url)
                <div class="cont">
                  <div class="left">Receipt:</div>
                  <div class="right"><a href="{{$payment->receipt_url }}">View Receipt</a></div>
              </div>
               @endif
          </div>
          </div>
          <div class="book-area">
              <h6>Your Booking Is Confirmed, Within A Few Minutes, You Will Receive An Email With Further Details,</h6>
              <p class="shortly"><span class="book-no">Your Booking No # is: {{$booking['id']}}</p>
                <p class="shortly">If You Haven't Received The Email, Please Check Your Spam And Junk Folders,  To Ensure You Continue To Receive Your Messages, Please Add Our Email Address To Your Contact. </p>                             
          </div>          
        </div>
        <div class="btn-area"><a href="{{ url('/') }}" class="btn main-btn"><span>Home</span></a></div>
    </div>
</div>
</section>
@stop