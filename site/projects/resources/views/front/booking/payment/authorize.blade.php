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
                    <h4>Your Booking</h4>
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
 @php
    $months = array(1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec');
@endphp
                 <div class="get-payment">
                    <div class="head">
                        <h4>Pay with</h4>
                        <div class="card-images">
                                            <img src="{{ asset('front')}}/images/icon.svg" class="img-fluid visa" alt="Visa">
                        </div>
                    </div>
                      @if(session('success_msg'))
                    <div class="alert alert-success fade in alert-dismissible show">                
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                         <span aria-hidden="true" style="font-size:20px">×</span>
                        </button>
                        {{ session('success_msg') }}
                    </div>
                    @endif
                    @if(session('error_msg'))
                    <div class="alert alert-danger fade in alert-dismissible show">
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                          <span aria-hidden="true" style="font-size:20px">×</span>
                        </button>    
                        {{ session('error_msg') }}
                    </div>
                    @endif

                    <!-- Error Message Container (Initially Hidden) -->
                    <div class="alert alert-danger fade in alert-dismissible show" style="display: none;" id="error-alert">
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true" style="font-size:20px">×</span>
                        </button>    
                        <span id="error-message">Invalid card number.</span>
                    </div>

                    <form id="payment-card-info" method="post" action="{{ route('authorize.submit',[$booking['id']]) }}">
                        @csrf
                         <input type="hidden" name="amount" value="{{ round($amount,2) }}">
                        
                       
                            
                        <div class="row card-detail">
                            <div class="col-12 form-floating">
                                <input type="text" class="form-control card-cvc" id="address" name="address" placeholder="address" value="{{ old('address') }}" required>
                                <label for="address">Billing Address</label>
                            </div>
                            <div class="col-12 form-floating">
                                <input type="text" class="form-control card-cvc" id="city" name="city" placeholder="city" value="{{ old('city') }}" required>
                                <label for="city">City</label>
                            </div>
                            <div class="col-12 form-floating">
                                <input type="text" class="form-control card-cvc" id="zipcode" name="zipcode" placeholder="zipcode" value="{{ old('zipcode') }}" required>
                                <label for="zipcode">Zip Code</label>
                            </div>
                            <div class="col-12 form-floating">
                                {!! Form::select("country",ModelHelper::countryDataSelect(),233,["class"=>"card-expiry-year","placeholder"=>"Select Country","required","id"=>"country-data"]) !!}
                            </div>
                            <div class="col-12 form-floating">
                                {!! Form::select("state",[],null,["class"=>"form-5456control","required","id"=>"state-data","placeholder"=>"Select state",]) !!}
                            </div>
                            <div class="col-12 form-floating">
                                <input type="text" class="form-control card-cvc" id="card-name" placeholder="Jhon Deo" name="owner" required>
                                <label for="Card name">Name on Card <i class="fa-solid fa-user"></i></label>
                            </div>
                            {{-- Phase 2D: Accept.js-managed inputs. The `name` attribute is REMOVED
                                 so these values are NEVER serialized into the form POST.
                                 Accept.js intercepts on submit and tokenizes them client-side. --}}
                            <div class="col-12 form-floating">
                                <input type="text" class="form-control card-cvc" id="cardNumber" placeholder="0000 0000 0000 0000" required>
                                <label for="cardNumber">Card number <i class="fa-solid fa-lock"></i></label>
                            </div>

                            <div class="col-4 form-floating expire">
                                <select id="expMonth" class="card-expiry-month" required>
                                    <option value="" disabled selected>Expiry Month *</option>
                                    @foreach(["01"=>"JAN","02"=>"FEB","03"=>"MAR","04"=>"APR","05"=>"MAY","06"=>"JUN","07"=>"JUL","08"=>"AUG","09"=>"SEP","10"=>"OCT","11"=>"NOV","12"=>"DEC"] as $v => $l)
                                        <option value="{{ $v }}">{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-4 form-floating expire">
                                <select id="expYear" class="card-expiry-year" required>
                                    <option value="" disabled selected>Expiry Year *</option>
                                    @for($y = (int)date('Y'); $y <= (int)date('Y') + 20; $y++)
                                        <option value="{{ $y }}">{{ $y }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-4 form-floating">
                                <input type="text" class="form-control card-cvc" id="cardCode" placeholder="123" maxlength="4" required>
                                <label for="cardCode">CVV</label>
                            </div>

                            {{-- Hidden inputs for the Accept.js opaque payment nonce. --}}
                            <input type="hidden" name="dataValue" id="dataValue">
                            <input type="hidden" name="dataDescriptor" id="dataDescriptor">
                        </div>
                        
                     
                         @if( $property->cancellation_policy )
                            <div class="cancellation">
                                <h4>Cancellation policy</h4>
                                <div class="cancel-policy">
                                    {!! $property->cancellation_policy !!}
                                </div>
                            </div>
                        @endif

                        @if($property->booking_policy)
                        <div class="rules">
                            <h4>Ground rules</h4>
                            <div class="rule">
                                <!--<p>We ask every guest to remember a few simple things about what makes a great guest.</p>-->
                                <!--<ul>-->
                                <!--    <li>Follow the house rules</li>-->
                                <!--    <li>Treat your Host’s home like your own</li>-->
                                <!--</ul>-->
                                {!! $property->booking_policy !!}
                            </div>
                        </div>
                        @endif
              
                        <div class="form-btn">
                            <button type="submit" class="cnfrm main-btn" id="pay-now"><span>Confirm & Pay ({!! $currency !!}{{ $amount }})</span></button>
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
                            <p class="home-type d-none">{{$property->heading}}</p>
                            <h4 class="pro-name">{{$property->name}}</h4>
                             <div class="ammenity-home">
                                @if($property->sleeps)
                                    <span>{{$property->sleeps}} Sleeps</span><span> · </span>
                                @endif
                                @if($property->beds)
                                    <span>{{$property->beds}} Individual Beds</span><span> · </span>
                                @endif
                                @if($property->bathroom)
                                    <span>{{$property->bathroom}} Bathrooms</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="price-details">
                        <h4>Booking Invoice</h4>
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
                                                    <p class="value">Fee (Min. stay not met)<span class="icon">!<span class="box-msg"> Minimum nights not met </span></span></p>
                                                    <p class="total">{{ $currency }} {{number_format($booking->extra_amount,2)}}</p>
                                                </div>
                    
                                    @endif
                                @endif
                                
                                 @if($booking->visitor_hookup_json )
                                    @php $ij=0; @endphp
                                    @endif
                                 @if($booking->firewood_json )
                                    @php $ij=0; @endphp
                                    @endif      

                                @if($booking->hottub_json )
                                @php $ij=0; @endphp
                                @endif
                                      
                                @if($booking->rv_hookup_json )
                                    @php $ij=0; @endphp
                                    @endif
                               
                                                                                                                                   
                                @if($booking->pet_fee_type=="taxable")
                                    @if($booking->total_pets)
                                        @if($booking->total_pets>0)
                                            @if($booking->pet_fee)
                                                @if($booking->pet_fee>0)                                              
                                                <div class="prc">
                                                    <p class="value">Pet Fee * {{$booking->total_pets}} :</p>
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
                                    
                                    @if($booking->before_total_fees)
                                    @foreach(json_decode($booking->before_total_fees) ?: [] as $c)
                                
                                                <div class="prc">
                                                    <p class="value">{{$c->name}} </p>
                                                    <p class="total">{{ $currency }}{{number_format($c->amount,2) }}</p>
                                                </div>
                                    @endforeach
                                    @endif

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
                
                                <div class="total-amt d-none">
                                    <p class="value">Total Amount after Discount:</p>
                                    <p class="total">{{ $currency }}{{number_format($booking->after_discount_total,2) }}</p>
                                </div>
                                  
                                @endif
                                   
                                    
                                    @if($booking->tax)
                                                <div class="total-amt">
                                                    <p class="value">Sub Total</p>
                                                    <p class="total">{{ $currency }}{{number_format($booking->sub_amount - $booking->tax,2)}}</p>
                                                </div>    
                                                <div class="prc">
                                                    <p class="value">Lodging Tax ({{ $booking->define_tax ?? '' }}%) :</p>
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
                                    @php $temp_sub_total =0; $card_flage = false; @endphp 
                                    @if($booking->after_total_fees)
                                                <div class="total-amt">
                                                    <p class="value"> Total Booking Cost</p>
                                                    <p class="total">{{ $currency }}{{number_format($booking->sub_amount,2)}}</p>
                                                </div>  
                                    @foreach(json_decode($booking->after_total_fees) ?: [] as $c)
                                                <div class="prc">
                                                    <p class="value">
                                                     @if(Helper::checkCreditCardString($c->name) === true)
                                                     @php $temp_sub_total = $c->name; $card_flage = true; @endphp 
                                                       {{$c->name}}
                                                     @else
                                                        {{$c->name}} 
                                                     @endif
                                                    </p>
                                                    <p class="total">{{ $currency }}{{number_format($c->amount,2) }}</p>
                                                </div>
                                    @endforeach
                                    @endif
                                                                                                                 
                        </div>
                                <div class="total-amt">
                                    <p class="value">Total @if($card_flage === true)with credit card processing fee @endif:</p>
                                    <p class="total">{{ $currency }}{{number_format($booking->total_amount,2)}}</p>
                                </div>
                                
                                    
                                    @if($booking->amount_data)
                                        @php
                                            $amount_data=json_decode($booking->amount_data,true);
                                        @endphp
                                        @if(is_array($amount_data))
                                            @foreach($amount_data as $c)
                                                @php $status='';@endphp
                                                @if(isset($c->status))
                                                    @php $status='(<span style="color:green; display:inline">Paid</span>)'; @endphp

                                                @endif
                                           
                                                     <div class="total-amt">
                                                        <p class="value">{{$c['message']}} {!! $status !!}:</p>
                                                        <p class="total">{{ $currency }}{{number_format($c['amount'],2) }}</p>
                                                    </div>
                                            @endforeach
                                        @endif
                                    @endif
                                 
                                 @php $deposit_flag = false; $deposit_amount = 0; $deposite_text = ''; @endphp
                                 @if($booking->security_deposit_data)
                                 @php $security_deposit_data = json_decode($booking->security_deposit_data, true); 
                                     $deposit_flag = true; 
                                     $deposit_amount = number_format($security_deposit_data['amount'],2);
                                     $deposite_text = $security_deposit_data['text'];
                                @endphp
                                  <div class="total-amt">
                                        <p class="value">{!! $deposite_text !!}</p>
                                        <p class="total">{{ $currency }}{{ $deposit_amount }}</p>
                                  </div>
                                 @endif
                </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Phase 2D: Accept.js wiring (master layout only yields "container", so this lives here, not in the unused "js" section). --}}
@php
    $authEnv = \ModelHelper::getDataFromSetting('authorize_environment') ?: 'sandbox';
    $acceptJsUrl = $authEnv === 'production'
        ? 'https://js.authorize.net/v1/Accept.js'
        : 'https://jstest.authorize.net/v1/Accept.js';
    $apiLoginId  = \ModelHelper::getDataFromSetting('AUTHORIZED_MERCHANT_LOGIN_ID');
    $clientKey   = \ModelHelper::getDataFromSetting('AUTHORIZED_MERCHANT_PUBLIC_CLIENT_KEY');
@endphp
<script src="{{ $acceptJsUrl }}" charset="utf-8"></script>
<script>
(function () {
    var form = document.getElementById('payment-card-info');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        if (document.getElementById('dataValue').value &&
            document.getElementById('dataDescriptor').value) {
            return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        if (!form.checkValidity()) { form.reportValidity(); return; }

        var alertBox = document.getElementById('error-alert');
        var errMsg   = document.getElementById('error-message');
        function showError(msg) {
            if (errMsg) errMsg.textContent = msg;
            if (alertBox) alertBox.style.display = 'block';
        }

        var authData = {
            clientKey:  @json($clientKey ?? ''),
            apiLoginID: @json($apiLoginId ?? '')
        };
        if (!authData.clientKey || !authData.apiLoginID) {
            showError('Payment is not configured. Please contact the host.');
            return;
        }

        var cardData = {
            cardNumber: document.getElementById('cardNumber').value.replace(/\s+/g, ''),
            month:      document.getElementById('expMonth').value,
            year:       document.getElementById('expYear').value,
            cardCode:   document.getElementById('cardCode').value
        };
        Accept.dispatchData({ authData: authData, cardData: cardData }, function (response) {
            if (response.messages.resultCode === 'Error') {
                var msg = response.messages.message.map(function (m) { return m.text; }).join('; ');
                showError(msg);
                return;
            }
            document.getElementById('dataValue').value      = response.opaqueData.dataValue;
            document.getElementById('dataDescriptor').value = response.opaqueData.dataDescriptor;
            form.submit();
        });
    }, true);
})();
</script>

@stop
@section('scripts')
@include('front.partials.payment-gateway-jquery')
<script type="text/javascript" src="https://js.stripe.com/v2/"></script>
<script type="text/javascript">
    $(document).ready(function() {
        const alphabetRegex = /[a-zA-Z]/;  // Regex to detect any alphabet characters

        $('#card-number').on('input', function() {
            const cardNumber = $(this).val();

            // Check if card number has alphabet characters or is greater than 16 digits
            if (alphabetRegex.test(cardNumber) || cardNumber.length > 16) {
                $('#error-message').text("Invalid card number: Must be up to 16 digits with no letters."); // Custom error message
                $('#error-alert').show();  // Show alert if invalid
                $(this).addClass('invalid');
            } else {
                $('#error-alert').hide();  // Hide alert if valid
                $(this).removeClass('invalid');
            }
        });

        // Close alert on button click
        $('.close').on('click', function() {
            $(this).closest('.alert').hide();
        });
    });
</script>

<style>
    .invalid { border-color: red; }
</style>
  
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
      Stripe.setPublishableKey($form.data('stripe-publishable-key'));
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
   $(document).on("change","#country-data",function(){
        $.post("{{ route('stateDateSelect') }}",{_token:"{{ csrf_token() }}",id:$("#country-data").val()},function(data){
            $("#state-data").html(data)
        });
    });
    $(function(){
         $.post("{{ route('stateDateSelect') }}",{_token:"{{ csrf_token() }}",id:$("#country-data").val()},function(data){
            $("#state-data").html(data)
        });
    });
    $(document).on("submit","#payment-card-info",function(){
        $("#confirm-purchase").attr("disabled",true);
        $("#pay-now").hide();
        $("#sygnius-loader").removeClass("d-none");
    });

</script>

@stop