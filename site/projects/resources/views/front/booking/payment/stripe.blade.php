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
                            <img src="{{ asset('front')}}/images/icon.svg" class="img-fluid visa" alt="Visa">
             
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
                    <form role="form" action="{{ route('stripe.post',$booking['id']) }}" method="post" class="require-validation"
                                                     data-cc-on-file="false"
                                                    data-stripe-publishable-key="{{ ModelHelper::getDataFromSetting('stripe_publish_key') }}"
                                                    id="payment-form">
                        @csrf
                         <input type="hidden" name="amount" value="{{ round($amount,2) }}">
  
                        <div class="row card-detail">
                            <div class="col-12 form-floating">
                                <input type="text" class="form-control" id="card-name" placeholder="Jhon Deo" name="card_name" required>
                                <label for="Card name">Name on Card <i class="fa-solid fa-user"></i></label>
                            </div>
                            <div class="col-12 form-floating">
                                <input type="text" class="form-control card-number" id="card-number" placeholder="0000 0000 0000 0000" name="card_number" required>
                                <label for="Card number">Card number <i class="fa-solid fa-lock"></i></label>
                            </div>
                            <div class="col-4 form-floating expire">
                              
                                {!! Form::select("month",["01"=>"JAN","02"=>"FEB","03"=>"MAR","04"=>"APR","05"=>"MAY","06"=>"JUN","07"=>"JUL","08"=>"AUG","09"=>"SEP","10"=>"OCT","11"=>"NOV","12"=>"DEC"],null,["class"=>"card-expiry-month","placeholder"=>"Expiry Month *","required"]) !!}
                            </div>
                            <div class="col-4 form-floating expire">
                                {!! Form::selectRange("year",date('Y'),(date('Y')+50),null,["class"=>"card-expiry-year","placeholder"=>"Expiry Year *","required"]) !!}
                            </div>
                            <div class="col-4 form-floating">
                                <input type="number" class="form-control card-cvc" id="cvv" placeholder="123" name="cvv" min="100" max="9999" required>
                                <label for="cvv">CVV</label>
                            </div>
                        </div>
                        
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
              
                        <div class="form-btn">
                            <button type="submit" class="cnfrm main-btn"><span>Confirm & Pay ({!! $currency !!}{{ $amount }})</span></button>
                        </div>
                    </form>
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
                                                    <p class="value">Fee (Min. stay not met)
                                    <span class="icon">!<span class="box-msg">
                                        Minimum nights not met 
                                    </span></span></p>
                                                    <p class="total">{{ $currency }} {{number_format($booking->extra_amount,2)}}</p>
                                                </div>
                    
                                    @endif
                                @endif
                                            
        @if($booking->rv_hookup_json )
            @php $ij=0; @endphp
            @endif
        @if($booking->visitor_hookup_json )
            @php $ij=0; @endphp
            @endif
        @if($booking->hottub_json )
            @php $ij=0; @endphp
            @endif
        @if($booking->firewood_json )
            @php $ij=0; @endphp
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
@include('front.partials.payment-gateway-jquery')
<script type="text/javascript" src="https://js.stripe.com/v2/"></script>
  
<script type="text/javascript">
$(function() {
    var $form         = $(".require-validation");
  $('form.require-validation').bind('submit', function(e) {
    var $form         = $(".require-validation"),
        inputSelector = ['input[type=email]', 'input[type=password]',
                         'input[type=text]', 'input[type=file]',
                         'textarea'].join(', '),
        $inputs       = $form.find('.required').find(inputSelector),
        $errorMessage = $form.find('div.error'),
        valid         = true;
        $errorMessage.addClass('hide');
 
        $('.has-error').removeClass('has-error');
    $inputs.each(function(i, el) {
      var $input = $(el);
      if ($input.val() === '') {
        $input.parent().addClass('has-error');
        $errorMessage.removeClass('hide');
        e.preventDefault();
      }
    });
  
    if (!$form.data('cc-on-file')) {
      e.preventDefault();
      // Guard: real Stripe publishable keys are ~107 chars. A short placeholder (e.g. the
      // 32-char dummy) makes Stripe.js v2 silently never call back → the page "hangs" after
      // the guest hits Pay. Fail loudly with a clear message instead.
      var pk = String($form.data('stripe-publishable-key') || '');
      if (pk.length < 80 || pk.indexOf('pk_') !== 0) {
        $('.error').removeClass('hide').find('.alert')
          .text('Online card payment is not set up yet for this site. Please contact the host to complete your booking — they will share a secure payment link.');
        return false;
      }
      Stripe.setPublishableKey(pk);
      Stripe.createToken({
        number: $('.card-number').val(),
        cvc: $('.card-cvc').val(),
        exp_month: $('.card-expiry-month').val(),
        exp_year: $('.card-expiry-year').val()
      }, stripeResponseHandler);
    }
  
  });
  

  
  function stripeResponseHandler(status, response) {
      console.log(status);
      console.log(response);
        if (response.error) {
            $('.error')
                .removeClass('hide')
                .find('.alert')
                .text(response.error.message);
        } else {
            // token contains id, last4, and card type
            var $form = $("#payment-form");
            var token = response['id'];
            // insert the token into the form so it gets submitted to the server
            //$form.find('input[type=text]').empty();
            $form.append("<input type='hidden' name='stripeToken' value='" + token + "'/>");
            $form.get(0).submit();
        }
    }
  
});
</script>
<script>
  
</script>
@stop