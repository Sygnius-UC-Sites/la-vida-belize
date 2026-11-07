@extends('front.layouts.brad')
@section("title",$data->meta_title)
@section("keywords",$data->meta_keywords)
@section("description",$data->meta_description)
@section('body-class', 'page booking-page')

@section('head')
@include('front.partials.booking-funnel-head')
@endsection

@section('content')
@include("front.booking.payment.square.vendor.autoload")
@php
$USE_PROD = 'false';


$ACCESS_TOKEN = ModelHelper::getDataFromSetting("square_ACCESS_TOKEN");
$applicationId=ModelHelper::getDataFromSetting("square_Application_id");//// LIVE 
$locationId=ModelHelper::getDataFromSetting("square_location_id");//// LIVE or TEST payment

@endphp
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
                        <div class="payment">
                           
                                <div class="container">
                                    <form id="payment-form">
                                      
                                        <div id="card-container"></div>
                                        <div id="payment-status-container"></div>
                                        <div class="col-50"style="text-align:center;" >
                                            <span class="payment-errors" style="color: red;"></span>
                                            <input name="email" type="hidden" id="email" maxlength="43" value="<?= $booking->email?>" style="width: 250px;"/>
                                            <input name="request_id" type="hidden" id="request_id" maxlength="43" value="<?php echo $booking['id'];?>" style="width: 250px;"/>
                                            <input class="inputfld" name="pat" id="pat_id" type="hidden" maxlength="43" value="<?php echo $amount;?>" style="width: 250px;background-color: #f2f2f2;border: none;font-size: 17px;" readonly/>
                                            <button class="cnfrm main-btn" id="card-button" type="button" style="">Pay $ <?php echo $amount;?></button>
                                          </div>
                                    </form>
                                    <form id="main-form-after-submit" method="post" action="{{ route('square.submit',$booking->id) }}">
                                        @csrf
                                        <input name="email" type="hidden" id="email" maxlength="43" value="<?= $booking->email?>" />
                                        <input name="request_id" type="hidden" value="<?php echo $booking->id;?>" />
                                        <input name="pat" type="hidden"value="<?php echo $amount;?>" />
                                        <input type="hidden" value="<?php echo $locationId;?>" name="locationId" />
                                        <input type="hidden" name="sourceId" id="sourceId_data" />
                                    </form>
                                  
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
@include('front.partials.payment-gateway-jquery')

{{-- Phase 2D: prefer the new `square_environment` setting; fall back to the
     legacy `square_tes_or_live='live'` so existing installs keep working. --}}
@php
    $sqEnv = \ModelHelper::getDataFromSetting('square_environment')
        ?: (\ModelHelper::getDataFromSetting('square_tes_or_live') === 'live' ? 'production' : 'sandbox');
    $squareSdkUrl = $sqEnv === 'production'
        ? 'https://web.squarecdn.com/v1/square.js'
        : 'https://sandbox.web.squarecdn.com/v1/square.js';
@endphp
<script type="text/javascript" src="{{ $squareSdkUrl }}"></script>
    <script>
        const appId = '<?php echo $applicationId;?>';
        const locationId = '<?php echo $locationId;?>';

        async function initializeCard(payments) {
            const card = await payments.card();
            await card.attach('#card-container');
            console.log(card)
            return card;
        }

        async function createPayment(token) {
            const body = {
                locationId,
                sourceId: token,
                pat: <?php echo $amount;?>,
                request_id: '<?php echo $booking->id;?>',
                email: '<?= $booking->email?>',
            };

            $("#sourceId_data").val(token);
          //  alert("hi")
            $("#main-form-after-submit").submit();
            

            
        }

        async function tokenize(paymentMethod) {
            const tokenResult = await paymentMethod.tokenize();
            if (tokenResult.status === 'OK') {
                return tokenResult.token;
            } else {
                let errorMessage = `Tokenization failed with status: ${tokenResult.status}`;
                if (tokenResult.errors) {
                    errorMessage += ` and errors: ${JSON.stringify(
              tokenResult.errors
            )}`;
                }

                throw new Error(errorMessage);
            }
        }

        // status is either SUCCESS or FAILURE;
        function displayPaymentResults(status) {
            const statusContainer = document.getElementById(
                'payment-status-container'
            );
            if (status === 'SUCCESS') {
                statusContainer.classList.remove('is-failure');
                statusContainer.classList.add('is-success');
            } else {
                statusContainer.classList.remove('is-success');
                statusContainer.classList.add('is-failure');
            }

            statusContainer.style.visibility = 'visible';
        }

        document.addEventListener('DOMContentLoaded', async function() {
            if (!window.Square) {
                throw new Error('Square.js failed to load properly');
            }

            let payments;
            try {
                payments = window.Square.payments(appId, locationId);
            } catch {
                const statusContainer = document.getElementById(
                    'payment-status-container'
                );
                statusContainer.className = 'missing-credentials';
                statusContainer.style.visibility = 'visible';
                return;
            }

            let card;
            try {
                card = await initializeCard(payments);
            } catch (e) {
                console.error('Initializing Card failed', e);
                return;
            }

            // Checkpoint 2.
            async function handlePaymentMethodSubmission(event, paymentMethod) {
                event.preventDefault();

                try {
                    // disable the submit button as we await tokenization and make a payment request.
                    cardButton.disabled = true;
                    const token = await tokenize(paymentMethod);
                    const paymentResults = await createPayment(token);
                    displayPaymentResults('SUCCESS');

                    console.debug('Payment Success', paymentResults);
                } catch (e) {
                    cardButton.disabled = false;
                    displayPaymentResults('FAILURE');
                    console.error(e.message);
                }
            }

            const cardButton = document.getElementById('card-button');
            cardButton.addEventListener('click', async function(event) {
                console.log(card);
                await handlePaymentMethodSubmission(event, card);
            });
        });
    </script>
           

@stop