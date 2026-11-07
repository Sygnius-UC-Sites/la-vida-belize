 @php
        $currency=ModelHelper::getDataFromSetting('payment_currency');
        $start_date=$main_data["start_date"];
        $end_date=$main_data["end_date"];
        $adults=$main_data["adults"];
        $child=$main_data["child"];
        $total_guests=$adults+$child;
        $gross_amount=$main_data['gross_amount'];

        $day=$main_data['total_night'];
        $extra_guest_fee=0;        
         if($property->guest_fee){
              if($property->guest_fee>0){
                  if($property->no_of_guest){
                      if($property->no_of_guest<$total_guests){
                          $single_guest_fee=$property->guest_fee;
                          $rest_guests=$total_guests-$property->no_of_guest;
                          $guest_fee=$single_guest_fee*$day*$rest_guests;
                          $extra_guest_fee = $guest_fee;                
                      }
                  }
              }
          }       

        $discount=0;
        $temp = $temp1 = $temp2=0;                 
        $min_stay=$c_start_date=$c_end_date=$c_property_id=null;                               
        $coupon=App\Models\Coupon::where(["code"=>Request::get("coupon")])->first();
        if($coupon){
         if(!empty($coupon->min_stay)){$min_stay = $coupon->min_stay;}
         if(!empty($coupon->start_date)){$c_start_date = $coupon->start_date;}
         if(!empty($coupon->end_date)){$c_end_date = $coupon->end_date;}
         if(!empty($coupon->property_id)){$c_property_id = $coupon->property_id;}
        }
        if(Request::get("coupon")){
            $coupon=App\Models\Coupon::where(["code"=>Request::get("coupon")])
                                        ->when($c_property_id !== null, function($q) use ($property){
                                           $q->where("property_id", $property->id);
                                        })
                                        ->when($c_start_date !== null, function($q) use ($start_date){
                                           $q->whereDate("start_date", '<=', $start_date);
                                        })
                                        ->when($c_end_date !== null, function($q) use ($end_date){
                                           $q->whereDate("end_date", '>=', $end_date);
                                        })
                                       ->when($min_stay !== null, function($q) use ($min_stay, $start_date, $end_date) {
                                          $day = Helper::calculateDays($start_date, $end_date);                                                 
                                          $q->where("min_stay", "<=", $day);  
                                        })  
                                       ->first(); 
            if($coupon){
                if($coupon->type=="excat"){
                    if($gross_amount<$coupon->amount){
                    }else{
                        $discount=$coupon->amount;
                    }
                }else{
                    if($coupon->amount>100){
                    }else{
                        $discount=(($gross_amount+$extra_guest_fee)*$coupon->amount)/100;                                 
                    }
                }
            }
        }

        $day=$main_data['total_night'];
        $sub_total=$gross_amount;
        $total_amount=$gross_amount;
        
        if($discount>0){
          $total_amount=$gross_amount-$discount;
          $temp = $sub_total-$discount;          
        }
        $temp1 = $sub_total;

        $before_total_fees=[];
        $after_total_fees=[];
        $total_guests=$main_data["adults"]+$main_data["childs"];
        $total_pets=$main_data['pet_fee_data_guarav'];
        $heating_pool_fee=$main_data['heating_pool_fee'];
        $extra_amount=$main_data['extra_amount'];
        $pet_fee=0;
        $guest_fee=0;
        $rest_guests=0;
        $single_guest_fee=0;
        $tax=0;
        $define_tax=$property->tax;
        
         $security_deposit_data = [];
        if($property->security_deposit_amount){
          if($property->security_deposit_text){
             $security_deposit_data = [ 'text' =>$property->security_deposit_text,'amount' => $property->security_deposit_amount];
           }
        }
        
    @endphp

         
    @if($property->guest_fee)
        @if($property->guest_fee>0)
            @if($property->no_of_guest)
                @if($property->no_of_guest<$total_guests)
                
                    @php $single_guest_fee=$property->guest_fee; @endphp
                    @php $rest_guests=$total_guests-$property->no_of_guest; @endphp
                    @php $guest_fee=$single_guest_fee*$day*$rest_guests;   @endphp
                    @php 
                        $sub_total+=$guest_fee;$total_amount+=$guest_fee; 
                    @endphp
                @endif
            @endif
        @endif
    @endif


    @if($property->pet_fee_type =="taxable")   

                @if($total_pets>0)
                        @php 
                            if($total_pets==1){
                                $pet_fee=$property->one_pet_fee;
                            }
                            if($total_pets==2){
                                $pet_fee=$property->two_pet_fee;
                            }
                            if($total_pets==3){
                                $pet_fee=$property->three_pet_fee;
                            }
                            if($total_pets==4){
                                $pet_fee=$property->four_pet_fee;
                            }
                            if($total_pets==5){
                                $pet_fee=$property->five_pet_fee;
                            }
                           // $pet_fee=$property->pet_fee;
                        
                     
                            $sub_total+=$pet_fee;$total_amount+=$pet_fee; 
                        @endphp
                 
                @endif
         
    @endif
    @if($extra_amount)
        @if($extra_amount>0)
        @php     $sub_total+=$extra_amount;$total_amount+=$extra_amount; @endphp
           
        @endif
    @endif
     @php
        $listData=collect() /* Phase 3: typed addon removed; UX rewire pending */;
        $visitor_hookup_json=[];
        $ids=[];
        if((Request::get("visitor_rate_id"))){
            if(isset(Request::get("visitor_rate_id")['id'])){
                $ids=Request::get("visitor_rate_id")['id'];
            }
        }
        $fields=[];
        if((Request::get("visitor_rate_id"))){
            if(isset(Request::get("visitor_rate_id")['field'])){
                $fields=Request::get("visitor_rate_id")['field'];
            }
        }
     
    @endphp
    @if(count($listData)>0)
        @foreach($listData as $c)
            @php 
                $sub_amount_data=0;
                $abc_amount=0;
                $status="false";
                $value_1=1;
                if(isset($fields[$c->id])){
                    $value_1=$fields[$c->id];
                }
                if(in_array($c->id,$ids)){
                    $abc_amount=$c->visitor_rate*$value_1*$day;
                    $sub_total+=$abc_amount;$total_amount+=$abc_amount; 
                    $status="true";
                }
                $visitor_hookup_json[]=["visitor_name"=>$c->visitor_name,"visitor_helping_text"=>$c->visitor_helping_text,"id"=>$c->id,"visitor_status"=>$c->visitor_status,"visitor_rate"=>$c->visitor_rate,"visitor_type"=>$c->visitor_type,"value"=>$value_1,"amount"=>$abc_amount,"status"=>$status];
            @endphp
        @endforeach
    @endif


    @php
        $listData=collect() /* Phase 3: typed addon removed; UX rewire pending */;
        $firewood_json=[];
        $ids=[];
        if((Request::get("firewood_rate_id"))){
            if(isset(Request::get("firewood_rate_id")['id'])){
                $ids=Request::get("firewood_rate_id")['id'];
            }
        }
        $fields=[];
        if((Request::get("firewood_rate_id"))){
            if(isset(Request::get("firewood_rate_id")['field'])){
                $fields=Request::get("firewood_rate_id")['field'];
            }
        }
     
    @endphp
    @if(count($listData)>0)
        @foreach($listData as $c)
            @php 
                $sub_amount_data=0;
                $abc_amount=0;
                $status="false";
                $value_1=1;
                if(isset($fields[$c->id])){
                    $value_1=$fields[$c->id];
                }
                if(in_array($c->id,$ids)){
                    //if($property->firebase_base_price){
                       // $abc_amount=($c->firewood_rate*$value_1 )+ $property->firebase_base_price;
                    //}else{
                         $abc_amount=($c->firewood_rate*$value_1 );
                    //}
                    $sub_total+=$abc_amount;$total_amount+=$abc_amount; 
                    $status="true";
                }
                $firewood_json[]=["firewood_name"=>$c->firewood_name,"firewood_helping_text"=>$c->firewood_helping_text,"id"=>$c->id,"firewood_status"=>$c->firewood_status,"firewood_rate"=>$c->firewood_rate,"firewood_type"=>$c->firewood_type,"value"=>$value_1,"amount"=>$abc_amount,"status"=>$status];
            @endphp
        @endforeach
    @endif



    @php
        $listData=collect() /* Phase 3: typed addon removed; UX rewire pending */;
        $hottub_json=[];
        $ids=[];
        if((Request::get("hottub_rate_id"))){
            if(isset(Request::get("hottub_rate_id")['id'])){
                $ids=Request::get("hottub_rate_id")['id'];
            }
        }
        $fields=[];
        if((Request::get("hottub_rate_id"))){
            if(isset(Request::get("hottub_rate_id")['field'])){
                $fields=Request::get("hottub_rate_id")['field'];
            }
        }
    @endphp
    @if(count($listData)>0)
        @foreach($listData as $c)
            @php 
                $sub_amount_data=0;
                $abc_amount=0;
                $status="false";
                $value_1=1;
                if(isset($fields[$c->id])){
                    $value_1=$fields[$c->id];
                }
                if(in_array($c->id,$ids)){
                    $abc_amount=$c->hottub_rate*$value_1;
                    $sub_total+=$abc_amount;$total_amount+=$abc_amount; 
                    $status="true";
                }
                $hottub_json[]=["hottub_name"=>$c->hottub_name,"hottub_helping_text"=>$c->hottub_helping_text,"id"=>$c->id,"hottub_status"=>$c->hottub_status,"hottub_rate"=>$c->hottub_rate,"hottub_type"=>$c->hottub_type,"value"=>$value_1,"amount"=>$abc_amount,"status"=>$status];
            @endphp
        @endforeach
    @endif

    @php
        $listData=collect() /* Phase 3: typed addon removed; UX rewire pending */;
        $rv_hookup_json=[];
        $ids=[];
        if((Request::get("rvhookup_rate_id"))){
            if(isset(Request::get("rvhookup_rate_id")['id'])){
                $ids=Request::get("rvhookup_rate_id")['id'];
            }
        }
        $fields=[];
        if((Request::get("rvhookup_rate_id"))){
            if(isset(Request::get("rvhookup_rate_id")['field'])){
                $fields=Request::get("rvhookup_rate_id")['field'];
            }
        }
        $quantity=[];
        if((Request::get("rvhookup_rate_quantity"))){
            if(isset(Request::get("rvhookup_rate_quantity")['field'])){
                $quantity=Request::get("rvhookup_rate_quantity")['field'];
            }
        }
     
    @endphp
    @if(count($listData)>0)
        @foreach($listData as $c)
            @php 
                $sub_amount_data=0;
                $abc_amount=0;
                $status="false";
                $value_1=1;
                  $value_q1=1;
                if(isset($fields[$c->id])){
                    $value_1=$fields[$c->id];
                }
                if(isset($quantity[$c->id])){
                    $value_q1=$quantity[$c->id];
                }
                if(in_array($c->id,$ids)){
                    $abc_amount=$c->rvhookup_rate*$value_1*$value_q1;
                    $sub_total+=$abc_amount;$total_amount+=$abc_amount; 
                    $status="true";
                }
                $rv_hookup_json[]=["rvhookup_name"=>$c->rvhookup_name,"rvhookup_helping_text"=>$c->rvhookup_helping_text,"id"=>$c->id,"quantity"=>$value_q1,"rvhookup_status"=>$c->rvhookup_status,"rvhookup_rate"=>$c->rvhookup_rate,"rvhookup_type"=>$c->rvhookup_type,"value"=>$value_1,"amount"=>$abc_amount,"status"=>$status];
            @endphp
        @endforeach
    @endif


    @php
        $heating_pool_fee1=0;
    @endphp
    @if($property->heating_pool_fee_type =="taxable")
        @if($property->heating_pool_fee)
            @if($property->heating_pool_fee>0)
                @if($heating_pool_fee=="Yes")
                    @php
                         $sub_total+=$property->heating_pool_fee;$total_amount+=$property->heating_pool_fee; 
                         $heating_pool_fee1=$property->heating_pool_fee;
                        @endphp
                 
                   
                @endif
            @endif
        @endif
    @endif

   @foreach(App\Models\PropertyFee::where("property_id",$property->id)->where("fee_apply","gross")->get() as $c)
        @php  $fee=Helper::getFeeAmountAndName($c,$gross_amount); @endphp
        @if($fee['status']==true)
        
            @php 

                $sub_total+=$fee['amount'];$total_amount+=$fee['amount']; 
                $before_total_fees[]=[
                    "name"=>$fee['name'],
                    "amount"=>$fee['amount'],
                    "fee_name"=>$c->fee_name,
                    "fee_rate"=>$c->fee_rate,
                    "fee_type"=>$c->fee_type,
                    "fee_apply"=>$c->fee_apply,
                    "fee_status"=>$c->fee_status
                ];
            @endphp
        @endif
   @endforeach
 
   
   
    @if($define_tax)
        @php
            $tax_subtotal = 0;
            if($discount > 0){            
                $tax_subtotal = $sub_total - $discount;
            }else{
                $tax_subtotal = $sub_total;
            }
            $tax=round(($tax_subtotal*$define_tax)/100,2);
            $sub_total+=$tax;
            $total_amount+=$tax;
        @endphp
     
    @endif

    @if($property->pet_fee_type !="taxable")   
       
                @if($total_pets>0)
                 @php 
                
                            if($total_pets==1){
                                $pet_fee=$property->one_pet_fee;
                            }
                            if($total_pets==2){
                                $pet_fee=$property->two_pet_fee;
                            }
                            if($total_pets==3){
                                $pet_fee=$property->three_pet_fee;
                            }
                            if($total_pets==4){
                                $pet_fee=$property->four_pet_fee;
                            }
                            if($total_pets==5){
                                $pet_fee=$property->five_pet_fee;
                            }
                           // $pet_fee=$property->pet_fee;
                        
                 
                        $total_amount+=$pet_fee; 
                        @endphp
                 
                @endif
         
    @endif


    @if($property->heating_pool_fee_type !="taxable")
        @if($property->heating_pool_fee)
            @if($property->heating_pool_fee>0)
                @if($heating_pool_fee=="Yes")
                    @php
                        $total_amount+=$property->heating_pool_fee; 
                         $heating_pool_fee1=$property->heating_pool_fee;
                        @endphp
                 
                @endif
            @endif
        @endif
    @endif      
   
    @php $temp1 = $sub_total;@endphp  
    @foreach(App\Models\PropertyFee::where("property_id",$property->id)->where("fee_apply","total")->get() as $c)
         @php 
         if($temp > 0){$sub_total = $temp;}
         if($temp1 > $temp){$sub_total = $temp1-$discount;}
         $fee = Helper::getFeeAmountAndName($c, $sub_total);
         if(isset($fee)){
             if($fee['status'] == true){ $total_amount += $fee['amount'];
                 if(Helper::checkCreditCardString($fee['name']) === true){ $temp2+=$fee['amount']; }
                 //else{$temp2+=$fee['amount'];}
             }        
             $after_total_fees[]=[
                 "name"=>$fee['name'],
                 "amount"=>$fee['amount'],
                 "fee_name"=>$c->fee_name,
                 "fee_rate"=>$c->fee_rate,
                 "fee_type"=>$c->fee_type,
                 "fee_apply"=>$c->fee_apply,
                 "fee_status"=>$c->fee_status
             ];
         }   
         @endphp
    @endforeach   

    @php
        $deducted_amount = 0;
        $card_need_hide = 0;
        if(request()->selected_option){
            $selectedOption = request()->selected_option;
            if(gettype($selectedOption) == 'string'){
                if($selectedOption !== 'Credit Card'){
                    foreach(App\Models\PropertyFee::where("property_id", $property->id)->where("fee_apply", "total")->get() as $c){
                        if($temp > 0){$sub_total = $temp;}
                        if($temp1 > $temp){ $sub_total = $temp1-$discount;}
                        $fee = Helper::getFeeAmountAndName($c, $sub_total);
                        if(isset($fee)){
                            if($fee['status'] == true){
                                if(Helper::checkCreditCardString($fee['name']) === true)
                                {
                                    $deducted_amount = number_format($fee['amount'], 2);  
                                    $card_need_hide = 1;                  
                                }                                
                            }
                        }                                               
                    }
                    $total_amount -= $temp2;
                }
            }
        }
        if($temp1 > $temp){
            $sub_total = $temp1;
            if($discount > 0){
                $sub_total -= floatval($discount);
            }            
        }
        $remaining_total_amount=$total_amount;
        $days=Helper::calculateDays(date('Y-m-d'),$start_date);
        $payment_interval=ModelHelper::getDataFromSetting("payment_interval");
        if($payment_interval){
        }else{
            $payment_interval=1;
        }
        $total_payment=$payment_interval;
        $amount_data=[];
        if($payment_interval==1){
                $first_amount=$remaining_total_amount;
                $first_message="Total Amount Due ";
                $second_message = "(Includes $1000 Refundable Dep. + CC proc. fee)";
                $total_payment=1;
                $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message,"sub_message"=>$second_message];
        }elseif($payment_interval==2){
            $second_days=ModelHelper::getDataFromSetting('second_min_total_days');
            if($days>=$second_days){
                $second_per=ModelHelper::getDataFromSetting('second_payment_per');
                $second_amount=round(($remaining_total_amount*$second_per)/100,2);
                $first_amount=$remaining_total_amount-$second_amount;
                $second_days=ModelHelper::getDataFromSetting('second_how_many_days');
                $second_date=date('F jS, Y',strtotime('- '.$second_days.' days',strtotime($start_date)));
                $first_message="Initial Payment to Secure your dates";
                $second_message="Final Payment Due on or Before ".$second_date;
                $total_payment=2;
                $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message];
                $amount_data[]=["amount"=>$second_amount,"type"=>"second","message"=>$second_message]; 
            }else{
                $first_amount=$remaining_total_amount;
                $first_message="Total Amount Due ";
                $second_message = "(Includes $1000 Refundable Dep. + CC proc. fee)";
                $total_payment=1;
                $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message,"sub_message"=>$second_message];
            } 
        }elseif($payment_interval==3){
            $second_days=ModelHelper::getDataFromSetting('second_min_total_days');
            $third_days=ModelHelper::getDataFromSetting('third_min_total_days');
            if($days>=$third_days){
                $second_per=ModelHelper::getDataFromSetting('second_third_payment_per');
                $third_per=ModelHelper::getDataFromSetting('third_payment_per');
                $second_amount=round(($remaining_total_amount*$second_per)/100,2);
                $third_amount=round(($remaining_total_amount*$third_per)/100,2);
                $first_amount=$remaining_total_amount-$second_amount-$third_amount;
                $second_days=ModelHelper::getDataFromSetting('second_third_how_many_days');
                $third_days=ModelHelper::getDataFromSetting('third_how_many_days');
                $second_date=date('F jS, Y',strtotime('- '.$second_days.' days',strtotime($start_date)));
                $third_date=date('F jS, Y',strtotime('- '.$third_days.' days',strtotime($start_date)));
                $total_payment=3;
                $first_message="Initial Payment to Secure your dates";
                $second_message="Next Payment Due on or Before ".$second_date;
                $third_message="Final Payment Due on or Before ".$third_date;
                $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message];
                $amount_data[]=["amount"=>$second_amount,"type"=>"second","message"=>$second_message]; 
                $amount_data[]=["amount"=>$third_amount,"type"=>"third","message"=>$third_message]; 
            }elseif($days>=$second_days){
                $second_per=ModelHelper::getDataFromSetting('second_payment_per');
                $second_amount=round(($remaining_total_amount*$second_per)/100,2);
                $first_amount=$remaining_total_amount-$second_amount;
                $second_days=ModelHelper::getDataFromSetting('second_how_many_days');
                $second_date=date('F jS, Y',strtotime('- '.$second_days.' days',strtotime($start_date)));
                $first_message="Initial Payment to Secure your dates";
                $second_message="Final Payment Due on or Before ".$second_date;
                $total_payment=2;
                $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message];
                $amount_data[]=["amount"=>$second_amount,"type"=>"second","message"=>$second_message]; 
            }else{
                $first_amount=$remaining_total_amount;
                $total_payment=1;
                $first_message="Total Amount Due ";
                $second_message = "(Includes $1000 Refundable Dep. + CC proc. fee)";
                $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message,"sub_message"=>$second_message];
            }   
        }
    @endphp

        <input type="hidden" name="discount" value="{{ $discount }}" />
        <input type="hidden" name="discount_coupon" value="{{ Request::get('coupon') }}" />
        <input type="hidden" name="after_discount_total" value="{{ $remaining_total_amount }}" />
        <input type="hidden" name="total_pets" value="{{ $total_pets }}">
        <input type="hidden" name="pet_fee" value="{{ $pet_fee }}">
        <input type="hidden" name="guest_fee" value="{{ $guest_fee }}">
        <input type="hidden" name="rest_guests" value="{{ $rest_guests }}">
        <input type="hidden" name="single_guest_fee" value="{{ $single_guest_fee }}">
        <input type="hidden" name="total_payment" value="{{ $total_payment }}">
        <input type="hidden" name="amount_data" value="{{ json_encode($amount_data) }}">
        <input type="hidden" name="property_id" value="{{ $property->id }}">
        <input type="hidden" name="checkin" value="{{ $start_date }}" >
        <input type="hidden" name="checkout" value="{{ $end_date }}" >
        <input type="hidden" name="total_guests" value="{{ $total_guests }}" >
        <input type="hidden" name="adults" value="{{ $adults }}" >
        <input type="hidden" name="child" value="{{ $child }}" >
        <input type="hidden" name="gross_amount" value="{{ $gross_amount }}" >
        <input type="hidden" name="total_night" value="{{ $day }}" >
        <input type="hidden" name="sub_amount" value="{{ $sub_total }}" >
        <input type="hidden" name="total_amount" value="{{ $total_amount }}" >
        <input type="hidden" name="after_total_fees" value="{{ json_encode($after_total_fees) }}">
        <input type="hidden" name="before_total_fees" value="{{ json_encode($before_total_fees) }}">
        <input type="hidden" name="request_id" value="{{ uniqid() }}" >
        <input type="hidden" name="tax" value="{{ $tax }}" >
        <input type="hidden" name="define_tax" value="{{ $define_tax }}" >
        <input type="hidden" name="pet_fee_type" value="{{ $property->pet_fee_type }}" />
        <input type="hidden" name="heating_pool_fee_type" value="{{ $property->heating_pool_fee_type }}" />
        <input type="hidden" name="heating_pool_fee" value="{{ $heating_pool_fee1 }}" />
        <input type="hidden" name="extra_amount" value="{{ $extra_amount }}" />
        <input type="hidden" name="rv_hookup_json" value="{{ json_encode($rv_hookup_json)  }}" />
        <input type="hidden" name="visitor_hookup_json" value="{{ json_encode($visitor_hookup_json)  }}" />
        <input type="hidden" name="hottub_json" value="{{ json_encode($hottub_json)  }}" />
        <input type="hidden" name="firewood_json" value="{{ json_encode($firewood_json)  }}" />
        <input type="hidden" name="firewood_json" value="{{ json_encode($firewood_json)  }}" />
        @if($property->security_deposit_amount)
        <input type="hidden" name="security_deposit_data" value="{{ json_encode($security_deposit_data)}}" />
        @endif
        <input type="hidden" name="card_need_hide" value="{{ $card_need_hide }}">

