<?php 
namespace App\Helper;
use Auth;
use DB;
use App\Models\BasicSetting;
use App\Models\Pms\PmsProperty;
use App\Models\Pms\PmsRoom;
use App\Models\Pms\PmsImage;
use App\Models\Pms\PmsAmenityGroup;
use App\Models\Pms\PmsAmenityGroupItem;
use App\Models\Pms\PmsBooking;
use App\Models\Pms\PmsUnavailableDate;
use App\Models\BookingRequest;
use Helper;
use MailHelper;
use Mail;
use Config;
use ModelHelper;
use Guzzle\Http\Exception\ClientException;
use GuzzleHttp;
use Illuminate\Support\Facades\Log;

class LodgifyApi{
    protected $adapter;

    public function __construct(){
        $this->token = config('services.lodgify.api_key', '');
        // Resolve active PMS adapter from service container dynamically
        $this->adapter = app(\App\Modules\VacationRental\Pms\PmsManager::class)->driver();
    }

    public function getAllRates($value=''){
        $error=["status"=>400];
        $client = new GuzzleHttp\Client();
        try{
            $response = $client->request('GET', 'https://api.lodgify.com/v1/channel/rates', ['headers' => ['X-ApiKey' => $this->token,'accept' => 'application/json',]]);
            $data=json_decode($response->getBody(), true);
            foreach($data as $d){
                
            }
        }catch(\GuzzleHttp\Exception\ClientException $e){
            $error['error'] = $e->getMessage();
        }catch(\GuzzleHttp\Exception\RequestException $se){
            $error['error'] = $se->getMessage();
        }catch(Exception $e){
            $error['error'] = $e->getMessage();
        }
        return $error;
    }

    public function getAllProperties($value=''){
     // dd($this->getAllRates());
        $error=["status"=>400];
        $client = new GuzzleHttp\Client();
        try{
            $response = $client->request('GET', 'https://api.lodgify.com/v1/properties', ['headers' => ['X-ApiKey' => $this->token,'accept' => 'application/json',]]);
            $data=json_decode($response->getBody(), true);
            foreach($data as $d){
                $ar["name"]=$d['name'];
                $ar["pms_property_id"]=$d['id'];
                if(isset($d['latitude'])){$ar["latitude"]=$d['latitude'];}
                if(isset($d['longitude'])){$ar["longitude"]=$d['longitude'];}
                $prop=PmsProperty::where(["pms_property_id"=>$d['id']])->first();
                if($prop){
                    PmsProperty::where(["pms_property_id"=>$d['id']])->update($ar);
                    $prop=PmsProperty::where(["pms_property_id"=>$d['id']])->first();
                    $this->getSinglePropertyDetail($prop->pms_property_id);
                }else{
                    $seo_url=Helper::getSeoUrlGet(strtolower(str_replace(" ","-",$ar['name'])).'-'.$d['id']);
                    $ar['seo_url']=$seo_url;
                    $ar['meta_title']=substr($ar['name'],0,60);
                    $ar['meta_keywords']=$ar['name'];
                    $ar['meta_description']=substr($ar['name'],0,160);
                    PmsProperty::create($ar);
                }
            }
        }catch(\GuzzleHttp\Exception\ClientException $e){
            $error['error'] = $e->getMessage();
        }catch(\GuzzleHttp\Exception\RequestException $se){
            $error['error'] = $se->getMessage();
        }catch(Exception $e){
            $error['error'] = $e->getMessage();
        }
        return $error;
    }

    public function getSinglePropertyDetail($property_id){
        $error=["status"=>400];
        $client = new GuzzleHttp\Client();
        try{
          	$property_id=$property_id;
            $response = $client->request('GET', 'https://api.lodgify.com/v1/properties/'.$property_id, ['headers' => ['X-ApiKey' =>  $this->token,'accept' => 'application/json']]);
            $data=json_decode($response->getBody(), true);
        	PmsUnavailableDate::where(["property_id"=>$property_id])->delete();
            $rooms=[];
            if(isset($data['in_out'])){
              if(isset($data['in_out']['not_available'])){
                foreach($data['in_out']['not_available'] as $cav){
                 	PmsUnavailableDate::create(["property_id"=>$property_id,"un_date"=>$cav['date']]);
                }
            }
            unset($data['in_out']);}
            if(isset($data['owner'])){$data['owner']=json_encode($data['owner']);}
            if(isset($data['rooms'])){$rooms=($data['rooms']);}
            $data['rooms']=json_encode($rooms);
            PmsProperty::where(["pms_property_id"=>$property_id])->update($data);
            foreach($rooms as $room){
                $this->getSingleRoomDetail($property_id,$room['id']);
            }
        }catch(\GuzzleHttp\Exception\ClientException $e){
            $error['error'] = $e->getMessage();
        }catch(\GuzzleHttp\Exception\RequestException $se){
            $error['error'] = $se->getMessage();
        }catch(Exception $e){
            $error['error'] = $e->getMessage();
        }
        return $error;
    }

    public function getSingleRoomDetail($property_id,$room_id){
        $error=["status"=>400];
        $client = new GuzzleHttp\Client();
        try{
            $response = $client->request('GET', 'https://api.lodgify.com/v1/properties/'.$property_id.'/rooms/'.$room_id, ['headers' => ['X-ApiKey' =>  $this->token,'accept' => 'application/json']]);
            $data=json_decode($response->getBody(), true);
            $images=[];
            $amenities=[];
            $data['pms_property_id']=$property_id;
            $data['pms_room_id']=$room_id;
            if(isset($data['images'])){$images=$data['images'];unset($data['images']);}
            if(isset($data['amenities'])){$amenities=$data['amenities'];unset($data['amenities']);}
            if(isset($data['currency'])){$data['currency']=json_encode($data['currency']);}
            if(isset($data['id'])){unset($data['id']);}
            $data['images_json']=json_encode($images);
            $data['amenities_json']=json_encode($amenities);
            $seo_url=Helper::getSeoUrlGet(strtolower(str_replace(" ","-",$data['name'])).'-'.$room_id);
            $data['seo_url']=$seo_url;
            $data['meta_title']=substr($data['name'],0,60);
            $data['meta_keywords']=$data['name'];
            $data['meta_description']=substr($data['name'],0,160);

            $room_data=PmsRoom::where(["pms_property_id"=>$property_id,"pms_room_id"=>$room_id])->first();
            if($room_data){
                PmsRoom::where(["pms_property_id"=>$property_id,"pms_room_id"=>$room_id])->update($data);
            }else{
                PmsRoom::create($data);
            }
            $ar1=['pms_property_id'=>$property_id,'pms_room_id'=>$room_id];
            PmsImage::where($ar1)->delete();
            foreach($images as $image){
                $ar=$ar1;
                if(isset($image['text'])){$ar['text']=$image['text'];}
                if(isset($image['url'])){$ar['url']=$image['url'];}
                PmsImage::create($ar);
            }
            PmsAmenityGroup::where($ar1)->delete();
            PmsAmenityGroupItem::where($ar1)->delete();
            foreach($amenities as $group=>$amenity){
                $ar=$ar1;
                if(isset($group)){$ar['text']=$group;}
                $amenity_group=PmsAmenityGroup::create($ar);
                foreach($amenity as $item){
                    $ar2=$ar1;
                    $ar2['pms_amenity_group_id']=$amenity_group->id;
                    if(isset($item['name'])){$ar2['name']=$item['name'];}
                    if(isset($item['prefix'])){$ar2['prefix']=$item['prefix'];}
                    if(isset($item['bracket'])){$ar2['bracket']=$item['bracket'];}
                    if(isset($item['text'])){$ar2['text']=$item['text'];}
                    PmsAmenityGroupItem::create($ar2);
                }
            }
        }catch(\GuzzleHttp\Exception\ClientException $e){
            $error['error'] = $e->getMessage();
        }catch(\GuzzleHttp\Exception\RequestException $se){
            $error['error'] = $se->getMessage();
        }catch(Exception $e){
            $error['error'] = $e->getMessage();
        }
        return $error;
    }

    public function getSearchAvailability($checkin,$checkout){
        $error=["status"=>400];
        $client = new GuzzleHttp\Client();
        try{
            $checkin=$checkin ."  12:00:00";
            $checkout=$checkout ."  12:00:00";
            $response = $client->request('GET', 'https://api.lodgify.com/v1/availability?periodStart='.$checkin.'&periodEnd='.$checkout.'&includeDetails=true', ['headers' => ['X-ApiKey' => $this->token,'accept' => 'application/json']]);
            $data=json_decode($response->getBody(), true);
            return  $data;
        }catch(\GuzzleHttp\Exception\ClientException $e){
            $error['error'] = $e->getMessage();
        }catch(\GuzzleHttp\Exception\RequestException $se){
            $error['error'] = $se->getMessage();
        }catch(Exception $e){
            $error['error'] = $e->getMessage();
        }
        return $error;
    }

    public function getRatesData(){
        $error=["status"=>400];
        $client = new GuzzleHttp\Client();
        try{
            $response = $client->request('GET', 'https://api.lodgify.com/v1/rates', ['headers' => ['X-ApiKey' => $this->token,'accept' => 'application/json']]);
            $data=json_decode($response->getBody(), true);
            return  $data;
        }catch(\GuzzleHttp\Exception\ClientException $e){
            $error['error'] = $e->getMessage();
        }catch(\GuzzleHttp\Exception\RequestException $se){
            $error['error'] = $se->getMessage();
        }catch(Exception $e){
            $error['error'] = $e->getMessage();
        }
        return $error;
    }
  
  

    public function getDailyRateData($room,$checkin,$checkout,$numberOfPeople,$requestData){
        $error=["status"=>400];
        $propertyId=$room->pms_property_id;
        $roomTypeId=$room->pms_room_id;
        $client = new GuzzleHttp\Client();
        try{

             $url='https://api.lodgify.com/v1/rates/calendar?RoomTypeId='.$roomTypeId.'&HouseId='.$propertyId.'&StartDate='.$checkin.'&EndDate='.$checkout;
            // $checkin=$checkin ." 13:01:38";
            // $checkout=$checkout ." 13:01:38";
            
            $response = $client->request('GET', $url, ['headers' => ['X-ApiKey' => $this->token,'accept' => 'application/json']]);
            $data=json_decode($response->getBody(), true);
            return ["data"=>$data,"status"=>"true","url"=>$url,"requestData"=>$requestData,"room"=>$room];
        }catch(\GuzzleHttp\Exception\ClientException $e){
            $er =(string)$e->getResponse()->getBody();
            $ar=json_decode($er,true);
            $error['error']=$ar;
            $error['status']=401;
            return $error;
        }catch(\GuzzleHttp\Exception\RequestException $se){
            $error['error'] = $se->getMessage();
            return $error;
        }catch(Exception $e){
            $error['error'] = $e->getMessage();
            return $error;
        }
    }


    public function getQuoteData($room,$checkin,$checkout,$numberOfPeople,$requestData){
        $error=["status"=>400];
        $propertyId=$room->pms_property_id;
        $roomTypeId=$room->pms_room_id;
        $client = new GuzzleHttp\Client();
        try{
            $checkin=$checkin ." 13:01:38";
            $checkout=$checkout ." 13:01:38";
            if(isset($requestData['coupon'])){
                $url='https://api.lodgify.com/v2/quote/'.$propertyId.'?roomTypes[0].Id='.$roomTypeId.'&roomTypes[0].People='.$numberOfPeople.'&arrival='.$checkin.'&departure='.$checkout.'&promotionCode='.$requestData['coupon'];   
            }else{
                $url='https://api.lodgify.com/v2/quote/'.$propertyId.'?roomTypes[0].Id='.$roomTypeId.'&roomTypes[0].People='.$numberOfPeople.'&arrival='.$checkin.'&departure='.$checkout;
            }
            if(isset($requestData['add_on'])){
                $ijk=0;
                foreach($requestData['add_on'] as $key=>$v){
                    if($v!=0){
                        $url.="&addOns[".$ijk."].Id=".$key."&addOns[".$ijk."].Units=".$v;
                        $ijk++;
                    }
                }
            }
            $response = $client->request('GET', $url, ['headers' => ['X-ApiKey' => $this->token,'accept' => 'application/json']]);
            $data=json_decode($response->getBody(), true);
            return ["data"=>$data,"status"=>"true","url"=>$url,"requestData"=>$requestData,"room"=>$room];
        }catch(\GuzzleHttp\Exception\ClientException $e){
            $er =(string)$e->getResponse()->getBody();
            $ar=json_decode($er,true);
            $error['error']=$ar;
            $error['status']=401;
            return $error;
        }catch(\GuzzleHttp\Exception\RequestException $se){
            $error['error'] = $se->getMessage();
            return $error;
        }catch(Exception $e){
            $error['error'] = $e->getMessage();
            return $error;
        }
    }

    public function getQuoteData1($property_id,$checkin,$checkout,$numberOfPeople,$requestData){
        $error=["status"=>400];
        $property=PmsProperty::find($property_id->id);
        if($property){
            $room=PmsRoom::where(["pms_property_id"=>$property->pms_property_id])->first();
            $propertyId=$room->pms_property_id;
            $roomTypeId=$room->pms_room_id;
            $client = new GuzzleHttp\Client();
            try{
                $checkin=$checkin ."  00:00:00";
                $checkout=$checkout ."  00:00:00";
                $url='https://api.lodgify.com/v1/quote/'.$propertyId.'?roomTypes[0].Id='.$roomTypeId.'&roomTypes[0].People='.$numberOfPeople.'&arrival='.$checkin.'&departure='.$checkout;
                if(isset($requestData['add_on'])){
                    $ijk=0;
                    foreach($requestData['add_on'] as $key=>$v){
                        if($v!=0){
                            $url.="&addOns[".$ijk."].Id=".$key."&addOns[".$ijk."].Units=".$v;
                            $ijk++;
                        }
                    }
                }
                $response = $client->request('GET', $url, ['headers' => ['X-ApiKey' => $this->token,'accept' => 'application/json']]);
                $data=json_decode($response->getBody(), true);
                return ["data"=>$data,"status"=>"true","url"=>$url,"requestData"=>$requestData,"room"=>$room];
            }catch(\GuzzleHttp\Exception\ClientException $e){
                $er =(string)$e->getResponse()->getBody();
                $ar=json_decode($er,true);
                $error['error']=$ar;
                $error['status']=401;
            }catch(\GuzzleHttp\Exception\RequestException $se){
                $error['error'] = $se->getMessage();
            }catch(Exception $e){
                $error['error'] = $e->getMessage();
            }
        }else{
            $error=["status"=>400];
            $error['error'] = "Property Not found";
        }
        return $error;
    }
  
  
   
    public function getAddonDataProperty($room,$checkin,$checkout,$numberOfPeople){       
        $propertyId=$room->pms_property_id;
        $roomTypeId=$room->pms_room_id;
        $adapter = app(\App\Modules\VacationRental\Pms\PmsManager::class)->driver($room->pms_source ?? 'lodgify');
        
        $res = $adapter->getPropertyAddons((string) $propertyId, [
            'start' => $checkin . " 12:00:00",
            'end' => $checkout . " 12:00:00",
            'rid' => $roomTypeId
        ]);
        
        if (isset($res['error'])) {
            return ["status"=>401, "error" => $res['error']];
        }
        if (!isset($res['data'])) {
            return ["status"=>401, "error" => "No addon data returned"];
        }
        return ["data" => $res['data'], "status" => "true"];
    }

    public static function getAddonDataPropertyWithout($room){
        $error=["status"=>400];
        if($room){
            $propertyId = $room->pms_property_id;
            $adapter = app(\App\Modules\VacationRental\Pms\PmsManager::class)->driver($room->pms_source ?? 'lodgify');

            $res = $adapter->getPropertyAddons((string) $propertyId);
            if (isset($res['error'])) {
                return ["status"=>401, "error" => $res['error']];
            }
            if (!isset($res['data'])) {
                return ["status"=>401, "error" => "No addon data returned"];
            }
            return ["data" => $res['data'], "status" => "true"];
        }else{
            $error=["status"=>400];
            $error['error'] = "Property Not found";
        }
        return $error;
    }

    public function getDeleteBooking($pms_booking_id){
        $res = $this->adapter->cancelReservation([
            'reservation_id' => $pms_booking_id,
            'action' => 'delete'
        ]);
        Log::channel('lodgify')->info('PMS Booking Delete', ['Response' => $res]);
        return $res;
    }
  
    public function declinedBooking($pms_booking_id){
        $res = $this->adapter->cancelReservation([
            'reservation_id' => $pms_booking_id,
            'action' => 'decline'
        ]);
        Log::channel('lodgify')->info('PMS Booking Decline', ['Response' => $res]);
        return $res;
    }
  
    public function expireTentativeBooking($bookingId)
    {
        $res = $this->adapter->cancelReservation([
            'reservation_id' => $bookingId,
            'action' => 'expire'
        ]);
        Log::channel('lodgify')->info('PMS Booking Expire', ['Response' => $res]);
        return $res;
    }

    public function getAllBookingData(){
        $error=["status"=>400];
        $client = new GuzzleHttp\Client();
        try{
            $url='https://api.lodgify.com/v1/reservation?offset=0&limit=50';
            $data=($this->getLimitBookingData($url));
            $new_data=[];
            $new_data=$data['items'];
            if(isset($data['total'])){
                if($data['total']){
                    for($i=50;$i<=$data['total'];$i+=50){
                        $url='https://api.lodgify.com/v1/reservation?offset='.$i.'&limit='.($i+50);
                        $da=($this->getLimitBookingData($url));
                        $new_data=array_merge($new_data,$da['items']);
                    }
                }
            }
            DB::table('pms_bookings')->truncate();
            foreach($new_data as $data1){
                if(isset($data1['id'])){$data1['pms_booking_id']=$data1['id'];unset($data1['id']);}
                
                $guestObj = $data1['guest'] ?? [];
                if(isset($data1['guest'])){$data1['guest']=json_encode($data1['guest']);}
                if(isset($data1['currency'])){$data1['currency']=json_encode($data1['currency']);}
                
                $adults = 0;
                $child = 0;
                if(isset($data1['rooms'])){
                    $room_ids=[];
                    foreach($data1['rooms'] as $c){
                        $room_ids[]=(string)$c['room_type_id'];
                        if (isset($c['guest_breakdown'])) {
                            $adults += $c['guest_breakdown']['adults'] ?? 0;
                            $child += $c['guest_breakdown']['children'] ?? 0;
                        }
                    }
                    $data1['rooms']=json_encode($data1['rooms']);
                    $data1['room_ids']=json_encode($room_ids);
                }
                
                PmsBooking::create($data1);
                
                // Bridge to legacy CRM BookingRequest table
                \App\Models\BookingRequest::updateOrCreate(
                    ['pms_booking_id' => $data1['pms_booking_id']],
                    [
                        'property_id' => $data1['property_id'] ?? 0,
                        'checkin' => $data1['arrival'] ?? date('Y-m-d'),
                        'checkout' => $data1['departure'] ?? date('Y-m-d', strtotime('+1 day')),
                        'total_guests' => $data1['people'] ?? 1,
                        'adults' => $adults,
                        'child' => $child,
                        'total_amount' => $data1['total_amount'] ?? 0,
                        'before_total_fees' => '[]',
                        'after_total_fees' => '[]',
                        'name' => $guestObj['name'] ?? 'Unknown Guest',
                        'email' => $guestObj['email'] ?? 'guest@lodgify.com',
                        'status' => $data1['status'] ?? null,
                        'booking_type_admin' => 'lodgify-sync'
                    ]
                );
            }
        }catch(\GuzzleHttp\Exception\ClientException $e){
            $error['error'] = $e->getMessage();
        }catch(\GuzzleHttp\Exception\RequestException $se){
            $error['error'] = $se->getMessage();
        }catch(Exception $e){
            $error['error'] = $e->getMessage();
        }
        return $error;
    }

    public function getLimitBookingData($url){
        $error=["status"=>400];
        $client = new GuzzleHttp\Client();
        try{
            $response = $client->request('GET', $url, ['headers' => ['X-ApiKey' => $this->token,'accept' => 'application/json']]);
            $data=json_decode($response->getBody(), true);
            return $data;
        }catch(\GuzzleHttp\Exception\ClientException $e){
            $error['error'] = $e->getMessage();
        }catch(\GuzzleHttp\Exception\RequestException $se){
            $error['error'] = $se->getMessage();
        }catch(Exception $e){
            $error['error'] = $e->getMessage();
        }
        return $error;
    }
 
    public function saveBookingLodgify($pms_property_id,$pms_room_id,$people,$start_date,$end_date,$name,$email,$mobile,$total_amount){
        $error=["status"=>400];
        $client = new GuzzleHttp\Client();
        $data_object= '{"source_text":"Manual","arrival":"'.$start_date.' 12:00:00","departure":"'.$end_date.' 12:00:00","property_id":'.$pms_property_id.',"status":"Booked","rooms":[{"room_type_id":'.$pms_room_id.',"people":'.$people.'}],"guest":{"name":"'.$name.'","email":"'.$email.'","phone":"'.$mobile.'"},"messages":[{"subject":"string","message":"string","type":"Owner","send_notification":true}],"bookability":"InstantBooking","ip_created":"string","total":'.$total_amount.',"currency_code":"USD"}';
        try{
           $response = $client->request('POST', 'https://api.lodgify.com/v1/reservation/booking', [
              'body' => $data_object,
              'headers' => ['X-ApiKey' => $this->token,'accept' => 'application/json','content-type' => 'application/*+json']
            ]); 
            $data=json_decode($response->getBody()->getContents(), true);
            return ["status"=>200,"data"=>$data,"obj"=>$data_object];
        }catch(\GuzzleHttp\Exception\ClientException $e){
            $error['error'] = $e->getMessage();
            $error['obj'] = $data_object;
            return $error;
        }catch(\GuzzleHttp\Exception\RequestException $se){
            $error['error'] = $se->getMessage();
            $error['obj'] = $data_object;
            return $error;
        }catch(Exception $e){
            $error['error'] = $e->getMessage();
            $error['obj'] = $data_object;
            return $error;
        }
    }
  
   
    public function createBookingQuoteLodgify($logify_booking_id,$pms_room_id,$card_name,$card_number,$card_cvv,$card_month,$card_year){
        $error=["status"=>400];
        $client = new GuzzleHttp\Client();
        try{
            $response = $client->request('POST', 'https://api.lodgify.com/v1/reservation/booking/'.$logify_booking_id.'/quote', [
                'body' => '{"is_policy_active":true,"room_types":[{"room_type_id":'.$pms_room_id.'}],"payment_method":{"number":"'.$card_number.'","cvv":"'.$card_cvv.'","full_name":"'.$card_name.'","month":'.$card_month.',"year":'.$card_year.'}}',
                'headers' => ['X-ApiKey' => $this->token,'accept' => 'application/json','content-type' => 'application/*+json']
            ]); 
            $data=json_decode($response->getBody()->getContents(), true);
            return ["status"=>200,"data"=>$data,"main_data"=>array('POST', 'https://api.lodgify.com/v1/reservation/booking/'.$logify_booking_id.'/quote', [
                'body' => '{"is_policy_active":true,"room_types":[{"room_type_id":'.$pms_room_id.'}],"payment_method":{"number":"'.$card_number.'","cvv":"'.$card_cvv.'","full_name":"'.$card_name.'","month":'.$card_month.',"year":'.$card_year.'}}',
                'headers' => ['X-ApiKey' => $this->token,'accept' => 'application/json','content-type' => 'application/*+json']
            ])];
        }catch(\GuzzleHttp\Exception\ClientException $e){
            $error['error'] = $e->getMessage();
            $error['main_data']=array('POST', 'https://api.lodgify.com/v1/reservation/booking/'.$logify_booking_id.'/quote', [
                'body' => '{"is_policy_active":true,"room_types":[{"room_type_id":'.$pms_room_id.'}],"payment_method":{"number":"'.$card_number.'","cvv":"'.$card_cvv.'","full_name":"'.$card_name.'","month":'.$card_month.',"year":'.$card_year.'}}',
                'headers' => ['X-ApiKey' => $this->token,'accept' => 'application/json','content-type' => 'application/*+json']
            ]);
            return $error;
        }catch(\GuzzleHttp\Exception\RequestException $se){
            $error['error'] = $se->getMessage();
            $error['main_data']=array('POST', 'https://api.lodgify.com/v1/reservation/booking/'.$logify_booking_id.'/quote', [
                'body' => '{"is_policy_active":true,"room_types":[{"room_type_id":'.$pms_room_id.'}],"payment_method":{"number":"'.$card_number.'","cvv":"'.$card_cvv.'","full_name":"'.$card_name.'","month":'.$card_month.',"year":'.$card_year.'}}',
                'headers' => ['X-ApiKey' => $this->token,'accept' => 'application/json','content-type' => 'application/*+json']
            ]);
            return $error;
        }catch(Exception $e){
            $error['error'] = $e->getMessage();
            $error['main_data']=array('POST', 'https://api.lodgify.com/v1/reservation/booking/'.$logify_booking_id.'/quote', [
                'body' => '{"is_policy_active":true,"room_types":[{"room_type_id":'.$pms_room_id.'}],"payment_method":{"number":"'.$card_number.'","cvv":"'.$card_cvv.'","full_name":"'.$card_name.'","month":'.$card_month.',"year":'.$card_year.'}}',
                'headers' => ['X-ApiKey' => $this->token,'accept' => 'application/json','content-type' => 'application/*+json']
            ]);
            return $error;
        }
    }

     public function newGetquote($room,$checkin,$checkout,$numberOfPeople,$numberofAdults,$numberofchildren,$numberofPets){
        $error=["status"=>400];
        
        $addOns = [];
        if($numberofPets >= 1){
            $addOnsData =  $this->getAddonDataProperty($room,$checkin,$checkout,$numberOfPeople);   
            if($addOnsData && isset($addOnsData['status']) && $addOnsData['status'] == true){
                $filteredRecords = collect($addOnsData['data'])->filter(function ($item) use ($numberofPets) {
                    return str_contains($item['name'], $numberofPets);
                });
                
                $filteredArray = $filteredRecords->values()->all();                    
                if($filteredArray){
                    $addOns[] = ['id' => $filteredArray[0]['id'], 'units' => 1];
                }                                                          
            }
        }
       
        try{
            $input = [
                'property_id' => $room->pms_property_id,
                'room_type_id' => $room->pms_room_id,
                'checkin' => $checkin,
                'checkout' => $checkout,
                'people' => $numberOfPeople,
                'adults' => $numberofAdults,
                'children' => $numberofchildren,
                'pets' => $numberofPets,
                'add_ons' => $addOns
            ];
            
            $adapter = app(\App\Modules\VacationRental\Pms\PmsManager::class)->driver($room->pms_source ?? 'lodgify');
            $response = $adapter->getQuote($input);
            
            if (isset($response['error'])) {
                $error['error'] = $response['error'];
                $error['status'] = 401;
                Log::channel('lodgify')->info('get quote lodgify error', ['Error' => $error]);
                return $error;
            }

            if (!isset($response['data'])) {
                $error['error'] = 'No data returned from Lodgify quote API';
                $error['status'] = 401;
                return $error;
            }

            Log::channel('lodgify')->info('get quote lodgify', ['response' => $response['data']]);
            return ["data"=>$response['data'],"status"=>"true","url"=>"","requestData"=>$response['data'],"room"=>$room];
        }catch(\Exception $e){
            $error['error'] = $e->getMessage();
            Log::channel('lodgify')->info('get quote lodgify error', ['Error' => $error]);
            return $error;
        }
    }

	
     public function saveBookingLodgifyNew($pms_property_id,$pms_room_id,$people,$start_date,$end_date,$name,$email,$mobile,$total_amount,$booking){
          try {
              $payload = [
                  "source" => "Website",
                  "checkin" => $start_date,
                  "checkout" => $end_date,
                  "property_id" => $pms_property_id,
                  "status" => "Open",
                  "ip" => request()->ip(),
                  "total" => $total_amount,
                  "currency" => "USD",
                  "room_type_id" => $pms_room_id,
                  "adults" => (int) $booking->adults,
                  "children" => (int) $booking->child,
                  "infants" => 0,
                  "pets" => (int) $booking->total_pets,
                  "first_name" => $booking->first_name,
                  "last_name" => $booking->last_name,
                  "email" => $booking->email,
                  "phone" => $booking->mobile
              ];

              $room = PmsRoom::where('pms_room_id', $pms_room_id)->first();
              $adapter = app(\App\Modules\VacationRental\Pms\PmsManager::class)->driver($room->pms_source ?? 'lodgify');

              Log::channel('lodgify')->info('Lodgify Booking Request', $payload);

              $response = $adapter->createReservation($payload);
              
              if (isset($response['error'])) {
                  Log::channel('lodgify')->info('Save booking lodgify error', ['error' => $response['error']]);
                  return $response;
              }

              if (!isset($response['data'])) {
                  return ["status" => 401, "error" => "No data returned from Lodgify reservation API"];
              }

              Log::channel('lodgify')->info('Save booking lodgify', ['response' => $response['data']]);
              return ["status"=>200,"data"=>$response['data'],"obj"=>$payload];

          } catch(\Exception $e){
              $error['error'] = $e->getMessage();
              Log::channel('lodgify')->info('Save booking lodgify error', ['error' => $error]);
              return $error;
          }
      }
  
  
    public function createBookingQuoteLodgifyNew($pms_booking_id,$pms_room_id, $numberofPets, $checkin, $checkout, $numberOfPeople, $property){
        
        $addOns =[];
        if($numberofPets >= 1){
            $addOnsData =  $this->getAddonDataProperty($property,$checkin,$checkout,$numberOfPeople);   
            if($addOnsData){
                if(isset($addOnsData['status'])){
                    if($addOnsData['status'] == true){
                        $filteredRecords = collect($addOnsData['data'])->filter(function ($item) use ($numberofPets) {
                            return str_contains($item['name'], $numberofPets); // Checks if "1" is in the name
                        });
                        
                        // Convert collection to array if needed
                        $filteredArray = $filteredRecords->values()->all();                    
                        if($filteredArray){
                              //$addOns =  '&addOns[0].Id='.$filteredArray[0]['id'].'&addOns[0].Units=1';
                              $addOns[] = [
                                  'add_on_id' => $filteredArray[0]['id'],
                                  'units'     => 1
                              ];
                        }                                                          
                    }
                }
            }
        }
      
      
          $client = new \GuzzleHttp\Client();
          $payload = [
              "is_policy_active" => true,
              "room_types" => [
                  [
                      "room_type_id" => (int) $pms_booking_id
                  ]
              ]
          ];
      
          if (!empty($addOns)) {
              $payload['add_ons'] = $addOns;
          }
          //dd($payload);
          Log::channel('lodgify')->info('createBookingQuoteLodgify Request', $payload);

          try {
              $response = $client->post(
                  "https://api.lodgify.com/v1/reservation/booking/{$pms_booking_id}/quote",
                  [
                      'headers' => [
                          'X-ApiKey' => $this->token,
                          'accept' => 'application/json',
                          'content-type' => 'application/*+json',
                      ],
                      'json' => $payload, 
                  ]
              );

              $data = json_decode($response->getBody(), true);

              Log::channel('lodgify')->info('createBookingQuoteLodgify Response', ['response' => $data]);

              return $data;

          } catch (\GuzzleHttp\Exception\ClientException $e) {
              $error['error'] = $e->getMessage();
              Log::channel('lodgify')->info('createBookingQuoteLodgify error', ['Error' => $error]);
              return ['status' => 400, 'error' => $e->getMessage()];
          }
      }
  
  
    public function getPaymentLink($pms_booking_id){
        $error=["status"=>400];
        $client = new GuzzleHttp\Client();
        try{
            //$response = $client->request('GET', 'https://api.lodgify.com/v1/reservation/booking/'.$pms_booking_id.'/quote', ['headers' => ['X-ApiKey' => $this->token,'accept' => 'application/json']]);
            $response = $client->request('GET', 'https://api.lodgify.com/v1/reservation/booking/'.$pms_booking_id.'/quote/paymentLink', ['headers' => ['X-ApiKey' => $this->token,'accept' => 'application/json']]);
            $data=json_decode($response->getBody(), true);
            Log::channel('lodgify')->info('Lodgify Payment Link', ['response' => $data]);
            return $data;
        }catch(\GuzzleHttp\Exception\ClientException $e){
            $error['error'] = $e->getMessage();
            Log::channel('lodgify')->info('Lodgify Payment Link', ['response error' => $error]);
            return $error;
        }catch(\GuzzleHttp\Exception\RequestException $se){
            $error['error'] = $se->getMessage();
            Log::channel('lodgify')->info('Lodgify Payment Link', ['response error' => $error]);
            return $error;
        }catch(Exception $e){
            $error['error'] = $e->getMessage();
            Log::channel('lodgify')->info('Lodgify Payment Link', ['response error' => $error]);
            return $error;
        }
    }





}