<?php
namespace App\Http\Controllers\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BookingRequest;
use App\Models\Property;
use App\Models\Payment;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
use Session;
use ModelHelper;
use MailHelper;
use Helper;
use App\Models\State;
use App\Models\Country;
use App\Models\Location;

class AuthorizeNetController extends Controller
{
    function index(Request $request,$id){
        // Phase 4 (2026-05-07): legacy `which_payment_gateway` cross-redirects
        // removed — see StripeController/PaypalController for context.
        $booking=BookingRequest::find($id);
        if($booking){
           	if($booking->operation=="check-booking"){
                // Mark booking as booked when guest confirms check payment.
                // Was previously a no-op `find()->where(...)` chain (audit M-1).
                BookingRequest::where("id",$id)->update(["booking_status"=>"booked"]);
                $booking=BookingRequest::find($id);
                return redirect()->to('check-payment/success/'.$booking->id);
            }elseif($booking->operation=="venmo-booking"){
                BookingRequest::where("id",$id)->update(["booking_status"=>"booked"]);
                $booking=BookingRequest::find($id);
                return redirect()->to('venmo-payment/success/'.$booking->id);
            }
            if($booking->payment_status=="paid"){
                return redirect('/')->with("success","Already paid for this booking");
            }
            if($booking->booking_status!="cancelled"){}else{
                return redirect()->to('/')->with("danger","Booking Cancelled, Please contact Administrator");
            }
            $property=Property::find($booking->property_id);
            if($property){
                $data = new \stdClass();
                $data->name=" Payment Request ";
                $data->meta_title=" Payment Request ";
                $data->meta_keywords=" Payment Request ";
                $data->meta_description=" Payment Request ";
                return view("front.booking.payment.authorize",compact("booking","data","property"));
            }
        }
        return abort(404);
    }

    function indexPost(Request $request,$id){
        // Detect AJAX/JSON callers (Plan D Task 10, spec D23). Declared at the
        // top of the method so every nested return path can branch on it.
        $wantsJson = $request->wantsJson() || $request->expectsJson();
        $booking=BookingRequest::find($id);
        $data = $request->all();
        if($booking){
            $property=Property::find($booking->property_id);
            if($property){
                try {
                    $input = $request->input();

                    // Phase 2D: Accept.js client-side tokenization. Card data NEVER reaches our server.
                    // The form posts dataValue + dataDescriptor (the opaque payment nonce) instead of
                    // raw cardNumber / cvv / expiration. PCI scope drops to SAQ-A.
                    $dataValue      = (string) ($input['dataValue'] ?? '');
                    $dataDescriptor = (string) ($input['dataDescriptor'] ?? '');
                    if ($dataValue === '' || $dataDescriptor === '') {
                        if ($wantsJson) {
                            return response()->json([
                                'success' => false,
                                'error_code' => 'tokenization_failed',
                                'error_message' => 'Payment was not tokenized correctly. Please refresh and try again.',
                            ], 200);
                        }
                        return redirect()->back()->with("danger", "Payment was not tokenized correctly. Please refresh and try again.");
                    }

                    // User/address validation kept — these still come through the form (not card-sensitive)
                    $user = explode(' ', (string) ($input['owner'] ?? ''));
                    if (!isset($user[0]) || $user[0] === '') {
                        if ($wantsJson) {
                            return response()->json([
                                'success' => false,
                                'error_code' => 'validation_failed',
                                'error_message' => 'Please Enter Name on Card',
                            ], 200);
                        }
                        return redirect()->back()->with("danger", "Please Enter Name on Card");
                    }

                    $booking = BookingRequest::find($id);
                    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
                    $merchantAuthentication->setName(ModelHelper::getDataFromSetting('AUTHORIZED_MERCHANT_LOGIN_ID'));
                    $merchantAuthentication->setTransactionKey(ModelHelper::getDataFromSetting('AUTHORIZED_MERCHANT_TRANSACTION_KEY'));
                    $refId = 'ref' . time();

                    // Build OpaqueData payment from the Accept.js nonce
                    $opaqueData = new AnetAPI\OpaqueDataType();
                    $opaqueData->setDataDescriptor($dataDescriptor);
                    $opaqueData->setDataValue($dataValue);
                    $paymentOne = new AnetAPI\PaymentType();
                    $paymentOne->setOpaqueData($opaqueData);

                    $transactionRequestType = new AnetAPI\TransactionRequestType();
                    $transactionRequestType->setTransactionType("authCaptureTransaction");
                    $transactionRequestType->setAmount($input['amount']);
                    $transactionRequestType->setPayment($paymentOne);

                    $request = new AnetAPI\CreateTransactionRequest();
                    $request->setMerchantAuthentication($merchantAuthentication);
                    $request->setRefId($refId);
                    $request->setTransactionRequest($transactionRequestType);

                    $controller = new AnetController\CreateTransactionController($request);

                    // Environment switch: prefer the new `authorize_environment` setting; fall back to
                    // the legacy `authorized_test_or_live` for backward compatibility.
                    $env = ModelHelper::getDataFromSetting('authorize_environment')
                        ?: (ModelHelper::getDataFromSetting('authorized_test_or_live') === 'live' ? 'production' : 'sandbox');
                    if ($env === 'production' || $env === 'live') {
                        $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::PRODUCTION);
                    } else {
                        $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);
                    }

                    $refundable_amount = 200;
    
                    if ($response != null) {
                        if(isset($response->transactionResponse)){
                            if(isset($response->transactionResponse->errors)){
                                if(isset($response->transactionResponse->errors[0]->errorText)){
                                    $message = $response->transactionResponse->errors[0]->errorText;
                                    if($wantsJson){
                                        return response()->json([
                                            'success' => false,
                                            'error_code' => 'card_declined',
                                            'error_message' => $message,
                                        ], 200);
                                    }
                                       return redirect()->back()->with("danger",$message);
                                }
                            }
                        }

                        // Check to see if the API request was successfully received and acted upon

                        if ($response->getMessages()->getResultCode() == "Ok") {
                            // Since the API request was successful, look for a transaction response
                            // and parse it to display the results of authorizing the card
                            $tresponse = $response->getTransactionResponse();
                            if ($tresponse != null && $tresponse->getMessages() != null) {
                                $message_text = $tresponse->getMessages()[0]->getDescription().", Transaction ID: " . $tresponse->getTransId();
                                $msg_type = "success_msg";
                                // Idempotent insert keyed on AuthorizeNet transaction id —
                                // protects against duplicate Payment rows / double counter
                                // increments if the customer reloads (audit C-5 pattern).
                                $payment=Payment::firstOrCreate(
                                    ['tran_id'=>$tresponse->getTransId()],
                                    ['booking_id'=>$booking->id,'receipt_url'=>'' ,'customer_id'=>'' ,'amount'=>$input['amount'],'description'=>json_encode($tresponse),'type'=>"Authorize.net",'status'=>"complete"]
                                );
                                if($payment->wasRecentlyCreated){
                                    ModelHelper::finalEmailAndUpdateBookingPayment($id,$booking,$payment,$property,"payment");
                                }
                                if($wantsJson){
                                    return response()->json([
                                        'success' => true,
                                        'redirect_url' => url('payment/success/'.$payment->id),
                                    ]);
                                }
                                return redirect('payment/success/'.$payment->id)->with("success","successfully Payment");
                            } else {
                                $message_text = 'There were some issue with the payment. Please try again later.';
                                $msg_type = "error_msg";
                                if ($tresponse->getErrors() != null) {
                                    $message_text = $tresponse->getErrors()[0]->getErrorText();
                                    $msg_type = "error_msg";
                                }
                            }
                            // Or, print errors if the API request wasn't successful
                        } else {
                            $message_text = 'There were some issue with the payment. Please try again later.';
                            $msg_type = "error_msg";
                            $tresponse = $response->getTransactionResponse();
                            if ($tresponse != null && $tresponse->getErrors() != null) {
                                $message_text = $tresponse->getErrors()[0]->getErrorText();
                                $msg_type = "error_msg";
                            } else {
                                $message_text = $response->getMessages()->getMessage()[0]->getText();
                                $msg_type = "error_msg";
                            }
                        }
                    } else {
                        $message_text = "No response returned";
                        $msg_type = "error_msg";
                    }
                    if($wantsJson){
                        return response()->json([
                            'success' => false,
                            'error_code' => 'gateway_error',
                            'error_message' => $message_text,
                        ], 200);
                    }
                    return back()->with($msg_type, $message_text);
                }  catch (Exception $e) {
                    $message =$e->getError()->message ;
                    $errorCode = 'gateway_error';
                }
           }else{
              $message="property is not longer";
              $errorCode = 'invalid_booking';
           }
        }else{
            $message="Booking is invalid";
            $errorCode = 'invalid_booking';
        }
        if($wantsJson){
            return response()->json([
                'success' => false,
                'error_code' => $errorCode ?? 'gateway_error',
                'error_message' => $message,
            ], 200);
        }
        return redirect()->back()->with("danger",$message);
    }

    function createCustomerProfile($email,$card_no,$cvv,$exp_year,$exp_month,$first_name,$last_name,$mobile,$city,$state,$country,$zipcode,$address){
        if($exp_month<10){
            $exp_month='0'.$exp_month;
        }
       // dd($exp_month);
            //dd($email,$card_no,$cvv,$exp_year,$exp_month,$first_name,$last_name,$mobile,$city,$state,$country,$zipcode,$address);
         /* Create a merchantAuthenticationType object with authentication details
        
       retrieved from the constants file */
        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName(ModelHelper::getDataFromSetting('AUTHORIZED_MERCHANT_LOGIN_ID'));
        $merchantAuthentication->setTransactionKey(ModelHelper::getDataFromSetting('AUTHORIZED_MERCHANT_TRANSACTION_KEY'));
    
        // Set the transaction's refId
        $refId = 'ref' . time();

        // Create a Customer Profile Request
        //  1. (Optionally) create a Payment Profile
        //  2. (Optionally) create a Shipping Profile
        //  3. Create a Customer Profile (or specify an existing profile)
        //  4. Submit a CreateCustomerProfile Request
        //  5. Validate Profile ID returned

        // Set credit card information for payment profile
        $creditCard = new AnetAPI\CreditCardType();
        $creditCard->setCardNumber($card_no);
        $creditCard->setExpirationDate($exp_year."-".$exp_month);
        $creditCard->setCardCode($cvv);
        $paymentCreditCard = new AnetAPI\PaymentType();
        $paymentCreditCard->setCreditCard($creditCard);

        // Create the Bill To info for new payment type
        $billTo = new AnetAPI\CustomerAddressType();
        $billTo->setFirstName($first_name);
        $billTo->setLastName($last_name);
        $billTo->setAddress($address);
        $billTo->setCity($city);
        $billTo->setState($state);
        $billTo->setZip($zipcode);
        $billTo->setCountry($country);
        $billTo->setPhoneNumber($mobile);
        $billTo->setfaxNumber($mobile);
        // Create an array of any shipping addresses
        $shippingProfiles[] = $billTo;
        // Create a new CustomerPaymentProfile object
        $paymentProfile = new AnetAPI\CustomerPaymentProfileType();
        $paymentProfile->setCustomerType('individual');
        $paymentProfile->setBillTo($billTo);
        $paymentProfile->setPayment($paymentCreditCard);
        $paymentProfiles[] = $paymentProfile;
        // Create a new CustomerProfileType and add the payment profile object
        $customerProfile = new AnetAPI\CustomerProfileType();
        $customerProfile->setDescription("Customer 2 Test PHP");
        $customerProfile->setMerchantCustomerId("M_" . time());
        $customerProfile->setEmail($email);
        $customerProfile->setpaymentProfiles($paymentProfiles);
        $customerProfile->setShipToList($shippingProfiles);
        // Assemble the complete transaction request
        $request = new AnetAPI\CreateCustomerProfileRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setProfile($customerProfile);
        // Create the controller and get the response
        $controller = new AnetController\CreateCustomerProfileController($request);

        if(ModelHelper::getDataFromSetting('authorized_test_or_live') == "live"){
            $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::PRODUCTION);
        }else{
            $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);
        }
        
        if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
            // echo "Succesfully created customer profile : " . $response->getCustomerProfileId() . "\n";
            $paymentProfiles = $response->getCustomerPaymentProfileIdList();
            // echo "SUCCESS: PAYMENT PROFILE ID : " . $paymentProfiles[0] . "\n";
            $ar=[
                "status"=>200,
                "customer_profile_id"=> $response->getCustomerProfileId(),
                "customer_profile_payment_id"=> $paymentProfiles[0],
                'customer_json_data'=>json_encode($response)
            ];
        } else {
            // echo "ERROR :  Invalid response\n";
            $errorMessages = $response->getMessages()->getMessage();
            // echo "Response : " . $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText() . "\n";
             $ar=[
                "status"=>400,
                "message"=> $errorMessages[0]->getText(),
                'customer_json_data'=>json_encode($response)
            ];
        }
        //dd($ar);
        return $ar;
    }
}