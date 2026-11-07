@extends("front.layouts.shared-master")
@section("title",$data->meta_title)
@section("keywords",$data->meta_keywords)
@section("description",$data->meta_description)

@section("container")

    @php
        $name = $data->name;
        $bannerImage = asset('front/themes/base/img/placeholder.svg');
        $payment_currency = $setting_data['payment_currency'] ?? 'USD';
    @endphp
    <!-- start banner sec -->
@include("front.layouts.banner")
<section class="booking-reciept">
<div class="container">
    <div class="row">
        <div class="top-area">
                 <h3>Booking Request Submitted Successfully.</h3>
                 <h3 style="color:#c6a867">(Be sure to check your Spam or Junk Folder for our reply, as it might land there)</h3>
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


          <h6>Booking Details</h6>
          <div class="book-rec-detail">
              <div class="cont">
                  <div class="left">Full Name:</div>
                  <div class="right">{{$booking->name}}</div>
              </div>
               <div class="cont">
                  <div class="left">Email:</div>
                  <div class="right">{{$booking->email}}</div>
              </div>
              <div class="cont">
                  <div class="left">Mobile:</div>
                  <div class="right">{{$booking->mobile}}</div>
              </div>
              <div class="cont">
                  <div class="left">Start Date:</div>
                  <div class="right">{{ date('F d, Y',strtotime($booking->checkin))}}</div>
              </div>
              <div class="cont">
                  <div class="left">End Date:</div>
                  <div class="right">{{ date('F d, Y',strtotime($booking->checkout))}}</div>
              </div>
          </div>
          </div>
          <div class="book-area">
              <h6>You will receive an email for the booking request.</h6>
              <p class="shortly"><span class="book-no">Your Booking No # is: {{$booking->id}}.</span> We will contact you shortly.<br/>
                If You Haven't Received The Email, Please Check Your Spam And Junk Folders,  To Ensure You Continue To Receive Your Messages, Please Add Our Email Address To Your Contact. 
              </p>            
          </div>
        </div>
        <div class="btn-area"><a href="{{ url('/') }}" class="btn main-btn"><span>Home</span></a></div>
    </div>
</div>
</section>
@stop
@section("css")
@parent
<link rel="stylesheet" type="text/css" href="{{ asset('drag-drop-image-uploader/src/image-uploader.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/css/select2.min.css" />
<link rel="stylesheet" href="{{ asset('front')}}/css/get-quote.css" />
<link rel="stylesheet" href="{{ asset('front')}}/css/get-quote-responsive.css" />
@stop 
@section("js")
@parent
<script src="{{ asset('drag-drop-image-uploader/src/image-uploader.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/js/select2.min.js" ></script>
<script src="{{ asset('front')}}/js/get-quote.js" ></script>

@stop
