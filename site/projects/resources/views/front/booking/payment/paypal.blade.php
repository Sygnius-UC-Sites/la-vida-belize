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
   

        $currency=ModelHelper::getDataFromSetting('payment_currency');

     
        
     $amount=$booking['total_amount'];
    @endphp
    @if($booking->discount)
        @if($booking->discount!="")
            @if($booking->discount!=0)
            @php $amount=$booking->after_discount_total; @endphp
            @endif
        @endif
    @endif
      @if($booking->amount_data)
            @php
                $amount_data=json_decode($booking->amount_data,true);
            @endphp
            @if(is_array($amount_data))
                @foreach($amount_data as $c)
                    @php $status='';@endphp
                    @if(isset($c['status']))
                  
                    @else
                        @php $amount=$c['amount'];break; @endphp
                    @endif
          
                @endforeach
            @endif
        @endif

@include("front.layouts.banner")
<section class="get-quote">
    <div class="container">
        <h2> Pay Now</h2>
        <div class="row quote-area">
            <div class="col-6 quote-detail">
                <div class="trip">
                    <h4>Your trip</h4>
                    <div class="trip-date">
                        <div class="left">
                            <p class="head">Dates</p>
                            <p>{{date('F jS, Y',strtotime($booking->checkin))}} - {{date('F jS, Y',strtotime($booking->checkout))}}</p>
                        </div>
                        <div class="right">
                            
                        </div>
                    </div>
                    <div class="trip-guest">
                        <div class="left">
                            <p class="head">Guests</p>
                            <p>{{$booking->total_guests}} Guests   ({{ $booking->adults }} Adults , {{ $booking->child }} Child)</p>
                        </div>
                        <div class="right">
                           
                        </div>
                    </div>
                </div>
                 <div class="get-payment">
                    <div class="head">
                        <h4>Pay with</h4>
                        <div class="card-images">
                            <img src="{{ asset('front')}}/images/visa.svg" class="img-fluid visa" alt="Visa">
                            <img src="{{ asset('front')}}/images/amex.svg" class="img-fluid amex" alt="Amex">
                            <img src="{{ asset('front')}}/images/master.svg" class="img-fluid master-card" alt="Master Card">
                            <img src="{{ asset('front')}}/images/paypal.svg" class="img-fluid paypal" alt="Paypal">
                            <img src="{{ asset('front')}}/images/gpay.svg" class="img-fluid gpay" alt="Google Pay">
                        </div>
                    </div>
                    @if (Session::has('success'))
                        <div class="alert alert-success text-center">
                            <a href="#" class="close" data-dismiss="alert" aria-label="close">×</a>
                            <p>{{ Session::get('success') }}</p>
                        </div>
                    @endif
                   
                    <div class="error hide" >
                        <div class="alert alert-danger"></div>
                    </div>
                        <div id="paypal-button-container" style="    margin: 55px;text-align: center;"></div>

                        @if( $property->cancellation_policy )
                            <div class="cancellation">
                                <h4>Cancellation policy</h4>
                                <div class="cancel-policy">
                                    {!! $property->cancellation_policy !!}
                                </div>
                            </div>
                        @endif

                        <div class="rules">
                            <h4>Ground rules</h4>
                            <div class="rule">
                                <p>We ask every guest to remember a few simple things about what makes a great guest.</p>
                                <ul>
                                    <li>Follow the house rules</li>
                                    <li>Treat your Host’s home like your own</li>
                                </ul>
                            </div>
                        </div>
              
                       
                       
                </div>
       
            </div>
            <div class="col-6 book-detail-sec">
                <div class="sticky-area">
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
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="price-details">
                        <h4>Price details</h4>
                        <div class="price-area">
                            <div class="prc">
                                <p class="value">Price Per Night x {{$booking->total_night}} nights</p>
                                <p class="total">{{ $currency }}{{number_format($booking->gross_amount,2)}}</p>
                            </div>

                                @if($booking->rest_guests)
                                    @if($booking->rest_guests>0)
                                        @if($booking->guest_fee)
                                            @if($booking->guest_fee>0)
                                                <div class="prc">
                                                    <p class="value">Additional Guest Fee <br> <span style="font-size:13px;">({{$booking->total_night}} nights * {{ $currency }}{{$booking->single_guest_fee}} * {{$booking->rest_guests}} Guests)</span></p>
                                                    <p class="total">{{ $currency }} {{number_format($booking->guest_fee,2)}}</p>
                                                </div>
                    
                                            @endif
                                        @endif
                                    @endif
                                @endif
                                                               
                                @if($booking->extra_amount)
                                    @if($booking->extra_amount>0)
                                       
                                                <div class="prc">
                                                    <p class="value">Extra Amount</p>
                                                    <p class="total">{{ $currency }} {{number_format($booking->extra_amount,2)}}</p>
                                                </div>
                    
                                    @endif
                                @endif
                                            
                                   
                                @if($booking->pet_fee_type=="taxable")
                                    @if($booking->total_pets)
                                        @if($booking->total_pets>0)
                                            @if($booking->pet_fee)
                                                @if($booking->pet_fee>0)
                                              
                                                <div class="prc">
                                                    <p class="value">Pet Fee :</p>
                                                    <p class="total">{{ $currency }}{{number_format($booking->pet_fee,2) }}</p>
                                                </div>
                                                @endif
                                            @endif
                                        @endif
                                    @endif
                                @endif
                                
                                @if($booking->heating_pool_fee_type=="taxable")
                                    @if($booking->heating_pool_fee)
                                        @if($booking->heating_pool_fee>0)

                                                <div class="prc">
                                                    <p class="value">Heating the Swimming Pool fee :</p>
                                                    <p class="total">{{ $currency }}{{number_format($booking->heating_pool_fee,2) }}</p>
                                                </div>
                                        @endif
                                    @endif
                                @endif
                                    
                                    
                                    @foreach(json_decode($booking->before_total_fees) ?: [] as $c)
                                

                                                <div class="prc">
                                                    <p class="value">{{$c->name}} </p>
                                                    <p class="total">{{ $currency }}{{number_format($c->amount,2) }}</p>
                                                </div>
                                    @endforeach
                                   
                                    
                                    @if($booking->tax)

                                                <div class="prc">
                                                    <p class="value">Tax ({{ $booking->define_tax ?? '' }}%) :</p>
                                                    <p class="total">{{ $currency }}{{number_format($booking->tax,2) }}</p>
                                                </div>
                                    @endif
                                    

                                    
                                    
                                    
                                        
                                @if($booking->pet_fee_type!="taxable")
                                    @if($booking->total_pets)
                                        @if($booking->total_pets>0)
                                            @if($booking->pet_fee)
                                                @if($booking->pet_fee>0)

                                                <div class="prc">
                                                    <p class="value">Pet Fee :</p>
                                                    <p class="total">{{ $currency }}{{number_format($booking->pet_fee,2) }}</p>
                                                </div>
                                                @endif
                                            @endif
                                        @endif
                                    @endif
                                @endif
                                
                                @if($booking->heating_pool_fee_type!="taxable")
                                    @if($booking->heating_pool_fee)
                                        @if($booking->heating_pool_fee>0)

                                                <div class="prc">
                                                    <p class="value">Heating the Swimming Pool fee :</p>
                                                    <p class="total">{{ $currency }}{{number_format($booking->heating_pool_fee,2) }}</p>
                                                </div>
                                        @endif
                                    @endif
                                @endif
                                    
                                    @foreach(json_decode($booking->after_total_fees) ?: [] as $c)
                                    
                                                <div class="prc">
                                                    <p class="value">{{$c->name}} </p>
                                                    <p class="total">{{ $currency }}{{number_format($c->amount,2) }}</p>
                                                </div>
                                    @endforeach
                                    
                             
                                    

  
                           
                        </div>
                                <div class="total-amt">
                                    <p class="value">Total</p>
                                    <p class="total">{{ $currency }}{{number_format($booking->total_amount,2)}}</p>
                                </div>


                                        @php $gaurav_discount=0;@endphp
                                @if($booking->discount)
                                    @if($booking->discount!="")
                                        @if($booking->discount!=0)
                                               @php $gaurav_discount=1;@endphp 
                                   
                                        
                                                <div class="total-amt">
                                                    <p class="value">Discount ({{ $booking->discount_coupon }}):</p>
                                                    <p class="total">- {{ $currency }}{{number_format($booking->discount,2) }}</p>
                                                </div>
                                        @endif
                                    @endif
                                @endif
                                @if($booking->extra_discount)
                                    @if($booking->extra_discount!="")
                                        @if($booking->extra_discount>0)
                                               @php $gaurav_discount=1;@endphp 
                                     
                                                <div class="total-amt">
                                                    <p class="value">Extra Discount :</p>
                                                    <p class="total">- {{ $currency }}{{number_format($booking->extra_discount,2) }}</p>
                                                </div>
                                        @endif
                                    @endif
                                @endif
                                @if($gaurav_discount==1)
                                   
                                                     <div class="total-amt">
                                                        <p class="value">Total Amount after Discount:</p>
                                                        <p class="total">{{ $currency }}{{number_format($booking->after_discount_total,2) }}</p>
                                                    </div>
                                  
                                @endif
                                    
                                    @if($booking->amount_data)
                                        @php
                                            $amount_data=json_decode($booking->amount_data,true);
                                        @endphp
                                        @if(is_array($amount_data))
                                            @foreach($amount_data as $c)
                                                @php $status='';@endphp
                                                @if(isset($c->status))
                                                    @php $status='(<span style="color:green;">Paid</span>)'; @endphp

                                                @endif
                                           
                                                     <div class="total-amt">
                                                        <p class="value">{{$c['message']}} {!! $status !!}:</p>
                                                        <p class="total">{{ $currency }}{{number_format($c['amount'],2) }}</p>
                                                    </div>
                                            @endforeach
                                        @endif
                                    @endif
                </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@stop
@section('scripts')


@php $paypalClientId = \ModelHelper::getDataFromSetting('paypal_access_token'); @endphp

@if($paypalClientId)
<script src="https://www.paypal.com/sdk/js?client-id={{ $paypalClientId }}" data-namespace="paypal_sdk"></script>
<script>

paypal_sdk.Buttons({
createOrder : function(data, actions){
  return actions.order.create({
    purchase_units : [{
      amount : {
        value : parseFloat("{{$amount}}")
      }
    }]
  })
},
style: {
      color:  'gold',
      shape:  'rect',
      label:  'buynow',
       size: 'responsive',
       branding: true

  },
  onApprove: function(data, actions) {
    // Capture the funds from the transaction
    return actions.order.capture().then(function(details) {
        console.log(details);

        location.href="{{ route('paypal.submit',[$booking['id']]) }}?tx="+details.id+"&st="+details.status+"&amt={{$amount}}&item_number={{$booking['id']}}";

    });
  },

}).render('#paypal-button-container');
</script>
@else
<div class="alert alert-warning" style="margin: 20px;">
    <strong>PayPal is not currently configured.</strong> Please contact the host or use a different payment method.
</div>
@endif
@stop