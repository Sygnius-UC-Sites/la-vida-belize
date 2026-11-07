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
        $amount=$booking['total_amount'];
    @endphp
     @if($booking['discount'])
        @if($booking['discount']!="")
            @if($booking['discount']!=0)
            @php $amount=$booking['after_discount_total']; @endphp
            @endif
        @endif
    @endif
    @php
  $payment_currency= $setting_data['payment_currency'];
    @endphp
      @if($booking['amount_data'])
            @php
                $amount_data=json_decode($booking['amount_data'],true);
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
    <!-- start banner sec -->
    @php //$amount=1; 
    
    
use Square\SquareClient;
use Square\Environment;
use Square\Exceptions\ApiException;
    
try {

$save_data=[];

    $email = $booking['email'];
    $amt = $request_data['pat'];
    $requestid = $request_data['request_id'];
    $paymentID = $statusMsg = ''; 
    $ordStatus = 'error'; 
    $responseArr = array(1 => 'Approved', 2 => 'Declined', 3 => 'Error', 4 => 'Held for Review'); 
    // Set the transaction's reference ID 
    $refID = 'REF'.time(); 
    $amount_money = new \Square\Models\Money();
    $amount_money->setAmount($amt*100);
    $amount_money->setCurrency('USD');
    $body = new \Square\Models\CreatePaymentRequest(
        $request_data['sourceId'],
        uniqid(),
        $amount_money
    );
    if(ModelHelper::getDataFromSetting('square_tes_or_live')=="live"):

        $client = new SquareClient([
            'accessToken' => $ACCESS_TOKEN,
            'environment' => Environment::PRODUCTION,
        ]);
    else:
          $client = new SquareClient([
            'accessToken' => $ACCESS_TOKEN,
            'environment' => Environment::SANDBOX,
        ]);
    endif;
    
    $api_response = $client->getPaymentsApi()->createPayment($body);
    
    if ($api_response->isSuccess()) {
        $result = $api_response->getResult();
         $status = $result->getPayment()->getStatus();
        $payment_id=$result->getPayment()->getId();

        $save_data['transaction_id']=$payment_id;
        $save_data['payment_status']=$status;
        // Idempotent insert: if the same Square payment_id was already recorded
        // (e.g. customer reloaded the success page), reuse the existing row
        // rather than double-counting installments. See audit C-5.
        $payment=App\Models\Payment::firstOrCreate(
            ['tran_id'=>$payment_id],
            [
                'booking_id'=>$booking1->id,
                'receipt_url'=>'' ,
                'customer_id'=>'' ,
                'amount'=>$amt,
                'description'=>json_encode($request_data),
                'type'=>"square",
                'status'=>"complete"
            ]
        );
        // Pass "payment" so the helper finalizes booking AND sends customer/admin
        // confirmation emails (matches Stripe/PayPal/AuthorizeNet behavior).
        // Only finalize if this insert is fresh — wasRecentlyCreated guards
        // against duplicate counter increments on browser reload.
        if ($payment->wasRecentlyCreated) {
            ModelHelper::finalEmailAndUpdateBookingPayment($id,$booking1,$payment,$property,"payment");
        }

                $url=url('payment/mail-success/'.$payment->id);
               // dd($url);
    } else {
       $errors = $api_response->getErrors();
       //dd($errors);
             $url=route('square_payment',$id);
       
    }


  


} catch (\SquareConnect\ApiException $e) {


$result=$e->getResponseBody();


  $save_data['payment_status']='Failed';
    $url=route('square_payment',$id);


}

    
    @endphp
    
 
   <script>
       location.href="{{ $url }}";
   </script>

@stop