<?php 
namespace App\Helper;
use App\Models\BasicSetting;
use DB;
use Auth;
use Mail;
use App\Models\EmailTemplete;
use App\Models\User;
use MailHelper;
use Session;
use LiveCart;
use Helper;
use App\Models\PropertyGallery;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyRate;
use App\Models\BookingRequest;
use App\Models\AttractionCategory;
use PDF;
use App\Models\Cms;
use App\Models\Country;
use App\Models\State;

class ModelHelper{
    public function countryDataSelect(){
        $data=[];
        $all=Country::all();
        foreach($all as $a){
            $data[$a->id]=$a->name." - ".$a->iso3;
        }
        return $data;
    }

    public function stateDateSelect($id){
        $data='<option value="">Choose State</option>';
        $all=State::where(["country_id"=>$id])->orderBy("name","asc")->get();
        foreach($all as $a){
            $data.='<option value="'.$a->id.'">'.$a->name." - ".$a->iso2.'</option>';
        }
        return $data;
    }

    function finalEmailAndUpdateBookingPayment($id,$booking,$payment,$property,$payment123="other"){
        // Decide payment status (paid vs partially). Phase 13 QuoteCalculator
        // writes a structured amount_data object that the legacy line-item
        // iteration can't read; fall back to comparing summed payments
        // against total_amount in that case.
        $new_amount_data = [];
        $amount_data = $booking->amount_data ? json_decode($booking->amount_data, true) : null;
        $isLegacyLineItems = is_array($amount_data) && !empty($amount_data) && isset($amount_data[0]) && is_array($amount_data[0]) && isset($amount_data[0]['amount']);
        $i = 0;
        if ($isLegacyLineItems) {
            $update = 0;
            foreach ($amount_data as $c) {
                $ar = $c;
                if (!isset($c['status']) && isset($c['amount'])
                    && (double) trim((string) $c['amount']) == (double) $payment->amount
                    && $update === 0) {
                    $ar['status']  = 'complete';
                    $ar['tran_id'] = $payment->tran_id;
                    $ar['mode']    = $payment->type;
                    $ar['date']    = date('Y-m-d H:i:s');
                    $update = 1;
                }
                $new_amount_data[] = $ar;
            }
            $j = 0;
            foreach ($new_amount_data as $c) {
                if (isset($c['status'])) $i++;
                $j++;
            }
            $status_payment = ($i === $j && $j > 0) ? 'paid' : 'partially';
        } else {
            $bookingTotal = (float) ($booking->total_amount ?? 0);
            $paidSoFar    = (float) DB::table('payments')->where('booking_id', $booking->id)->where('status', 'complete')->sum('amount');
            $status_payment = ($bookingTotal > 0 && $paidSoFar + 0.01 >= $bookingTotal) ? 'paid' : 'partially';
            $new_amount_data = $amount_data ?: [];
            $i = ($status_payment === 'paid') ? 1 : 0;
        }
        LiveCart::getFileIcalFileData($property->id);
        BookingRequest::find($id)->update(["booking_status"=>"booked","payment_status"=>$status_payment,"amount_data"=>json_encode($new_amount_data),"how_many_payment_done"=>$i]);
        LiveCart::getFileIcalFileData($property->id);
        $data=BookingRequest::Find($id);
        if($payment123=="payment"){
          $booking=$data->toArray();
          $booking=BookingRequest::find($booking['id'])->toArray();
          $pdf_name='invoice-'.$booking['id'].'.pdf';
          $file=[];

          // Phase 13D — confirmed/payment-received email is admin-editable on the
          // Guest Emails page (Booking lifecycle tab → Booking confirmed).
          $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $data);
          $brand    = ModelHelper::getDataFromSetting('mailer_admin_name')
                   ?: ModelHelper::getDataFromSetting('mail_from_name')
                   ?: ModelHelper::getDataFromSetting('owner_name')
                   ?: 'Notification';
          $footer   = ModelHelper::getDataFromSetting('mail_footer') ?: '';

          $subject  = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('confirmed_default_subject') ?: 'Booking confirmed: {{property_name}}'));
          $bodyHtml = $resolver->expandMergeFieldsWithQuote((string) (ModelHelper::getDataFromSetting('confirmed_default_body_html') ?: '<p>Hi {{guest_name}},</p><p>Your booking is confirmed.</p><p>{{brand}}</p>'));
          $html     = view('mail.email-shell', ['body_html_rendered' => $bodyHtml, 'brand' => $brand, 'mail_footer' => $footer, 'booking' => $data, 'property' => $property])->render();

          // Merge any admin-managed `confirmed` attachments with the existing
          // legacy invoice PDF the call site already passed in via $file.
          $confirmedFiles = array_values(array_filter(array_merge((array) $file, $resolver->attachmentsFor('confirmed'))));
          MailHelper::emailSenderByController($html, $data->email, $subject, $confirmedFiles);
          \App\Services\Email\SendLog::record((int)$data->id, 'confirmed', (string)$data->email, $subject, $html);

          // Phase 4 — post-confirmation signing flow.
          // If this property requires the agreement signed AFTER the booking
          // is confirmed AND the guest hasn't signed yet, send a magic-link
          // "please sign" email right after the confirmation email lands.
          // Fires once per booking — the rental_aggrement_status guard makes
          // it a no-op on later instalment-payment passes through this code
          // path (because by then the guest has signed and the status flipped
          // to 'true', or they're still on the same unsigned state and we
          // don't want to spam them again on every instalment).
          if ($property->requiresPostConfirmationSigning()
              && ($data->rental_aggrement_status ?? 'false') !== 'true'
              && \App\Services\Email\SendLog::latestFor((int)$data->id, 'rental-agreement-request') === null
          ) {
              $signLink = (string) \URL::temporarySignedRoute(
                  'rental-agreement.sign-link',
                  now()->addDays(14),
                  ['id' => $data->id]
              );
              $extras = ['{{rental_agreement_link}}' => $signLink];
              $signSubject = $resolver->expandMergeFields(
                  (string) (ModelHelper::getDataFromSetting('rental_agreement_request_default_subject')
                      ?: 'Please sign your rental agreement for {{property_name}}'),
                  $extras
              );
              $signBody = $resolver->expandMergeFields(
                  (string) (ModelHelper::getDataFromSetting('rental_agreement_request_default_body_html')
                      ?: '<p>Hi {{guest_name}},</p>'
                       . '<p>Your booking at <strong>{{property_name}}</strong> is confirmed — thank you!</p>'
                       . '<p>One more step: please sign the rental agreement before your check-in date.</p>'
                       . '<p><a href="{{rental_agreement_link}}" style="display:inline-block;padding:12px 24px;background:#d9a24a;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;">Sign agreement →</a></p>'
                       . '<p>This link is valid for 14 days. If you have any questions, just reply to this email.</p>'),
                  $extras
              );
              $signHtml = view('mail.email-shell', [
                  'body_html_rendered' => $signBody,
                  'brand'              => $brand,
                  'mail_footer'        => $footer,
                  'booking'            => $data,
                  'property'           => $property,
              ])->render();
              MailHelper::emailSenderByController($signHtml, $data->email, $signSubject);
              \App\Services\Email\SendLog::record((int)$data->id, 'rental-agreement-request', (string)$data->email, $signSubject, $signHtml);
          }

          $adminTo = ModelHelper::getDataFromSetting('payment_receiving_mail')
                  ?: ModelHelper::getDataFromSetting('admin_email_primary');
          if ($adminTo) {
              // Phase 13E — admin alert editable on Email Templates → Owner alerts → Booking confirmed.
              $adminSubject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('admin_alert_confirmed_subject') ?: 'Booking confirmed — {{property_name}}'));
              $adminBody    = $resolver->expandMergeFieldsWithQuote((string) (ModelHelper::getDataFromSetting('admin_alert_confirmed_body_html') ?: '<p>Booking #{{booking_id}} confirmed.</p><p><a href="{{admin_url}}">View →</a></p>'));
              $adminHtml    = view('mail.email-shell', ['body_html_rendered' => $adminBody, 'brand' => $brand, 'mail_footer' => $footer, 'booking' => $data, 'property' => $property])->render();
              MailHelper::emailSenderByController($adminHtml, $adminTo, $adminSubject, $file);
          }

          // ── Payment receipts (always fire on a successful payment, regardless of whether
          //    this is the first instalment, second, balance, or a top-up). Customer gets
          //    an itemised receipt; owner gets a concise "money received" copy. ──────
          $bookingTotal      = (float) ($data->total_amount ?? 0);
          $paidSoFar         = (float) DB::table('payments')->where('booking_id', $data->id)->where('status', 'complete')->sum('amount');
          $outstandingNumber = max(0, $bookingTotal - $paidSoFar);
          $paymentExtras = [
              '{{payment_amount}}'      => '$' . number_format((float) $payment->amount, 2),
              '{{payment_date}}'        => $payment->created_at ? \Carbon\Carbon::parse($payment->created_at)->format('M j, Y · g:i A') : now()->format('M j, Y · g:i A'),
              '{{payment_method}}'      => strtoupper((string) ($payment->type ?? 'card')),
              '{{transaction_id}}'      => (string) ($payment->tran_id ?? ''),
              '{{receipt_url}}'         => (string) ($payment->receipt_url ?? ''),
              '{{outstanding_balance}}' => $outstandingNumber > 0
                  ? '$' . number_format($outstandingNumber, 2) . ' remaining'
                  : 'Paid in full',
          ];

          $receiptSubject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('receipt_default_subject') ?: 'Payment receipt — {{property_name}}'));
          $receiptBody    = $resolver->expandMergeFieldsWithQuote((string) (ModelHelper::getDataFromSetting('receipt_default_body_html') ?: '<p>Receipt for {{payment_amount}}.</p>'));
          $receiptSubject = strtr($receiptSubject, $paymentExtras);
          $receiptBody    = strtr($receiptBody, $paymentExtras);
          $receiptHtml    = view('mail.email-shell', ['body_html_rendered' => $receiptBody, 'brand' => $brand, 'mail_footer' => $footer, 'booking' => $data, 'property' => $property])->render();

          MailHelper::emailSenderByController($receiptHtml, $data->email, $receiptSubject, $resolver->attachmentsFor('receipt'));
          \App\Services\Email\SendLog::record((int) $data->id, 'receipt', (string) $data->email, $receiptSubject, $receiptHtml);

          if ($adminTo) {
              $ownerReceiptSubject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('admin_alert_receipt_subject') ?: '[Payment received] #{{booking_id}}'));
              $ownerReceiptBody    = $resolver->expandMergeFieldsWithQuote((string) (ModelHelper::getDataFromSetting('admin_alert_receipt_body_html') ?: '<p>{{payment_amount}} received on booking #{{booking_id}}.</p>'));
              $ownerReceiptSubject = strtr($ownerReceiptSubject, $paymentExtras);
              $ownerReceiptBody    = strtr($ownerReceiptBody, $paymentExtras);
              $ownerReceiptHtml    = view('mail.email-shell', ['body_html_rendered' => $ownerReceiptBody, 'brand' => $brand, 'mail_footer' => $footer, 'booking' => $data, 'property' => $property])->render();

              MailHelper::emailSenderByController($ownerReceiptHtml, $adminTo, $ownerReceiptSubject);
              \App\Services\Email\SendLog::record((int) $data->id, 'admin-receipt', (string) $adminTo, $ownerReceiptSubject, $ownerReceiptHtml);
          }
        }
    }
    
    function getImageByProduct($product_id){
        return PropertyGallery::where("property_id",$product_id)->orderBy("sorting","asc")->get();
    }

    function saveSIngleDatePropertyRate($request,$id='default'){
        PropertyRate::where("rate_group_id",$id)->delete();
        if($request->start_date){
          	$now = strtotime($request->start_date); // or your date as well
          	$your_date = strtotime($request->end_date);
          	$datediff =  $your_date-$now;
          	$day= ceil($datediff / (60 * 60 * 24));
          	for($i=0;$i<=$day;$i++){
            	$data=$request->only(["discount_weekly","discount_monthly","is_available","platform_type","currency","base_price","notes","min_stay","base_min_stay",'property_id','checkin_day','checkout_day']);
                $data['rate_group_id']=$id;
                $date = strtotime($request->start_date);
                $date = strtotime("+".$i." day", $date);
                $date= date('Y-m-d', $date);
                $data['single_date_timestamp']=strtotime($date);
                $data["single_date"]=$date;
                if($request->type_of_price=="default"){
                    $data['price']=$request->price;
                }else{
                    $newDay = date('l', strtotime($date));
                    if($newDay=="Monday"){
                        $data['price']=$request->monday_price;
                    }else if($newDay=="Tuesday"){
                        $data['price']=$request->tuesday_price;
                    }else if($newDay=="Wednesday"){
                        $data['price']=$request->wednesday_price;
                    }else if($newDay=="Thursday"){
                        $data['price']=$request->thrusday_price;
                    }else if($newDay=="Friday"){
                        $data['price']=$request->friday_price;
                    }else if($newDay=="Saturday"){
                        $data['price']=$request->saturday_price;
                    }else if($newDay=="Sunday"){
                        $data['price']=$request->sunday_price;
                    }
                }
                PropertyRate::create($data);
          	}
        }
    }

    function getAttractionCategorySelectList(){
        $data=[];
        $all=AttractionCategory::all();
        foreach($all as $a){
            $data[$a->id]=$a->name;
        }
        return $data;
    }
    
    function showPetFee($pet_fee){
        if($pet_fee){
            if($pet_fee>0){
                return "display:flex;";
            }
        }
        return "display:none;";
    }

    function showpoolFee($pet_fee){
        if($pet_fee){
            if($pet_fee>0){
                return "display:block;";
            }
        }
        return "display:none;";
    }

    function getProperptySelectList(){
        $data=[];
        $all=Property::all();
        foreach($all as $a){
            $data[$a->id]=$a->name;
        }
        return $data;
    }
    
    function getLocationSelectList(){
        $data=[];
        $all=Location::all();
        foreach($all as $a){
            $data[$a->id]=$a->name;
        }
        return $data;
    }

    function getPropertyDropdown(){
        return \App\Models\Property::where('status', 'true')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
    
    function getPageSelectList(){
        $data=[];
        $all=Cms::all();
        foreach($all as $a){
            $data[$a->id]=$a->name;
        }
        return $data;
    }

    function getProppertySelectList(){
        $data=[];
        $all=Property::all();
        foreach($all as $a){
            $data[$a->id]=$a->name;
        }
        return $data;
    }
	
	public function getDataFromSetting($name){
		$result=null;
		$data=BasicSetting::where("name",$name)->first();
		if($data){
			$result=$data->value;
		}
		return $result;
	}
}
