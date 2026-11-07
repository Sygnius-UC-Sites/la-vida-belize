@php
    $start_date=$main_data["start_date"];
    $end_date=$main_data["end_date"];
    
    $adults=$main_data["adults"];
    $child=$main_data["child"];
    $total_guests=$adults+$child;
    
    $gross_amount=$main_data['gross_amount'];
    
    $day=$main_data['total_night'];
    
    $sub_total=$gross_amount;
    $total_amount=$gross_amount;
    
    $before_total_fees=[];
    $after_total_fees=[];
    $custom_before_total_fees=[];
        
    
    $total_guests=$main_data["adults"]+$main_data["childs"];
    $total_pets=$main_data['pet_fee_data_guarav'];
    $extra_amount=$main_data['extra_amount'];
    $pet_fee=0;
    $guest_fee=0;
    $rest_guests=0;
    $single_guest_fee=0;
    
    $extra_discount=0;
    $discount=0;
    $apply=0;
    
    $heating_pool_fee=$main_data['heating_pool_fee'];
    $tax=0;
    $define_tax=$property->tax;
@endphp

            <div class="col-md-12">
            <table class="table table-bordered">
                <tr>
                  <th>Check IN</th>
                  <th>Check Out</th>
                  <th>Total Guests</th>
                  <th>Total Nights</th>
                  <th   align="right" style="text-align: right !important;">Gross Amount</th>
               </tr>
                <tr>
                  <td>{{date('F jS, Y',strtotime($start_date))}}</td>
                  <td>{{date('F jS, Y',strtotime($end_date))}}</td>
                  <td>{{$total_guests}} Guests   ({{ $adults }} Adults , {{ $child }} Child)</td>
                  <td>{{$day}}</td>
                  <td  align="right">{!! ModelHelper::getDataFromSetting('payment_currency') !!}{{number_format($gross_amount,2)}}</td>
               </tr>
                   @if(Request::get("product_amount"))
           @foreach(Request::get("product_amount") as $key=>$c)
               
                @if($c)
                    <tr>
                      <td colspan="4"  align="left">{{ Request::get("product_name")[$key] }}</td>
                      <td align="right">{!! ModelHelper::getDataFromSetting('payment_currency') !!}{{number_format($c,2)}}</td>
                   </tr>
                    @php 

                        $sub_total+=$c;$total_amount+=$c; 
                        $custom_before_total_fees[]=[
                            "product_name"=>Request::get("product_name")[$key],
                            "product_amount"=>$c,
                          
                        ];
                    @endphp
                @endif
           @endforeach
    @endif
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

        
                <tr>
                  <td colspan="4"  align="left">Additional Guest Fee <br> <span style="font-size:13px;">({{$day}} nights * {!! $setting_data['payment_currency'] !!}{{$single_guest_fee}} * {{$rest_guests}} Guests)</span></td>
                  <td align="right"> {!! $setting_data['payment_currency'] !!} {{number_format($guest_fee,2)}}</td>
                </tr>
            @endif
        @endif
    @endif
@endif
    @if($extra_amount)
        @if($extra_amount>0)
        @php    // $sub_total+=$extra_amount;$total_amount+=$extra_amount; @endphp
           
                        <tr>
                            <td colspan="4"  align="left">Extra Amount </td>
                  <td align="right"> {!! $setting_data['payment_currency'] !!} {{number_format($extra_amount,2)}}</td>
                </tr>
        @endif
    @endif

@php
    $listData=collect() /* Phase 3: typed addon removed; UX rewire pending */;
    $ids=[];
    $rv_hookup_json=[];
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
@endphp
@if(count($listData)>0)
    <tr>
        <th colspan="4">
            <p><strong>RV Hookup</strong><br></p>
        </th>
        <td align="right"></td>
    </tr>
     @foreach($listData as $c)
      @php
            if(isset($abc_amount)){
                unset($abc_amount);
            }
            $status="false";
            $abc_amount=0;
            $value_1=1;
            if(isset($fields[$c->id])){
                $value_1=$fields[$c->id];
            }
            if(in_array($c->id,$ids)){
                $abc_amount=$c->rvhookup_rate*$value_1;
                $sub_total+=$abc_amount;$total_amount+=$abc_amount; 
                $status="true";
            }
            $rv_hookup_json[]=["rvhookup_name"=>$c->rvhookup_name,"rvhookup_helping_text"=>$c->rvhookup_helping_text,"id"=>$c->id,"rvhookup_status"=>$c->rvhookup_status,"rvhookup_rate"=>$c->rvhookup_rate,"rvhookup_type"=>$c->rvhookup_type,"value"=>$value_1,"amount"=>$abc_amount,"status"=>$status];
        @endphp
     <tr>
        <th colspan="4">
            <input type="checkbox" {{ in_array($c->id,$ids)?'checked':'' }} name="rvhookup_rate_id[id][{{$c->id}}]" value="{{ $c->id }}" class="common-field-show-rate rvhookup-rate" data-target="rvhookup_rate_id{{$c->id}}field" >
            <input type="text" value="{{ $value_1 }}" name="rvhookup_rate_id[field][{{$c->id}}]" id="rvhookup_rate_id{{$c->id}}field" class="{{ in_array($c->id,$ids)?'':'d-none' }}  rate-calculateion-data"> {{$c->rvhookup_name}}<sub>{{$c->rvhookup_helping_text}}</sub>
        </th>
        <td align="right">
            @if(isset($abc_amount))
                {!! ModelHelper::getDataFromSetting('payment_currency') !!}{{number_format($abc_amount,2)}}
            @endif
        </td>
     </tr>
    @endforeach
@endif




@php
    $listData=collect() /* Phase 3: typed addon removed; UX rewire pending */;
    $ids=[];
    $hottub_json=[];
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
    <tr>
        <th colspan="4">
            <p><strong>RV Hookup</strong><br></p>
        </th>
        <td align="right"></td>
    </tr>
     @foreach($listData as $c)
      @php
            if(isset($abc_amount)){
                unset($abc_amount);
            }
            $status="false";
            $abc_amount=0;
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
     <tr>
        <th colspan="4">
            <input type="checkbox" {{ in_array($c->id,$ids)?'checked':'' }} name="hottub_rate_id[id][{{$c->id}}]" value="{{ $c->id }}" class="common-field-show-rate hottub-rate" data-target="hottub_rate_id{{$c->id}}field" >
            <input type="text" value="{{ $value_1 }}" name="hottub_rate_id[field][{{$c->id}}]" id="hottub_rate_id{{$c->id}}field" class="{{ in_array($c->id,$ids)?'':'d-none' }}  rate-calculateion-data"> {{$c->hottub_name}}<sub>{{$c->hottub_helping_text}}</sub>
        </th>
        <td align="right">
            @if(isset($abc_amount))
                {!! ModelHelper::getDataFromSetting('payment_currency') !!}{{number_format($abc_amount,2)}}
            @endif
        </td>
     </tr>
    @endforeach
@endif




@php
    $listData=collect() /* Phase 3: typed addon removed; UX rewire pending */;
    $ids=[];
    $firewood_json=[];
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
    <tr>
        <th colspan="4">
            <p><strong>RV Hookup</strong><br></p>
        </th>
        <td align="right"></td>
    </tr>
     @foreach($listData as $c)
      @php
            if(isset($abc_amount)){
                unset($abc_amount);
            }
            $status="false";
            $abc_amount=0;
            $value_1=1;
            if(isset($fields[$c->id])){
                $value_1=$fields[$c->id];
            }
            if(in_array($c->id,$ids)){
                $abc_amount=$c->firewood_rate*$value_1;
                $sub_total+=$abc_amount;$total_amount+=$abc_amount; 
                $status="true";
            }
            $firewood_json[]=["firewood_name"=>$c->firewood_name,"firewood_helping_text"=>$c->firewood_helping_text,"id"=>$c->id,"firewood_status"=>$c->firewood_status,"firewood_rate"=>$c->firewood_rate,"firewood_type"=>$c->firewood_type,"value"=>$value_1,"amount"=>$abc_amount,"status"=>$status];
        @endphp
     <tr>
        <th colspan="4">
            <input type="checkbox" {{ in_array($c->id,$ids)?'checked':'' }} name="firewood_rate_id[id][{{$c->id}}]" value="{{ $c->id }}" class="common-field-show-rate firewood-rate" data-target="firewood_rate_id{{$c->id}}field" >
            <input type="text" value="{{ $value_1 }}" name="firewood_rate_id[field][{{$c->id}}]" id="firewood_rate_id{{$c->id}}field" class="{{ in_array($c->id,$ids)?'':'d-none' }}  rate-calculateion-data"> {{$c->firewood_name}}<sub>{{$c->firewood_helping_text}}</sub>
        </th>
        <td align="right">
            @if(isset($abc_amount))
                {!! ModelHelper::getDataFromSetting('payment_currency') !!}{{number_format($abc_amount,2)}}
            @endif
        </td>
     </tr>
    @endforeach
@endif


@php
    $listData=collect() /* Phase 3: typed addon removed; UX rewire pending */;
    $ids=[];
    $visitor_hookup_json=[];
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
    <tr>
        <th colspan="4">
            <p><strong>RV Hookup</strong><br></p>
        </th>
        <td align="right"></td>
    </tr>
     @foreach($listData as $c)
      @php
            if(isset($abc_amount)){
                unset($abc_amount);
            }
            $status="false";
            $abc_amount=0;
            $value_1=1;
            if(isset($fields[$c->id])){
                $value_1=$fields[$c->id];
            }
            if(in_array($c->id,$ids)){
                $abc_amount=$c->visitor_rate*$value_1;
                $sub_total+=$abc_amount;$total_amount+=$abc_amount; 
                $status="true";
            }
            $visitor_hookup_json[]=["visitor_name"=>$c->visitor_name,"visitor_helping_text"=>$c->visitor_helping_text,"id"=>$c->id,"visitor_status"=>$c->visitor_status,"visitor_rate"=>$c->visitor_rate,"visitor_type"=>$c->visitor_type,"value"=>$value_1,"amount"=>$abc_amount,"status"=>$status];
        @endphp
     <tr>
        <th colspan="4">
            <input type="checkbox" {{ in_array($c->id,$ids)?'checked':'' }} name="visitor_rate_id[id][{{$c->id}}]" value="{{ $c->id }}" class="common-field-show-rate visitor-rate" data-target="visitor_rate_id{{$c->id}}field" >
            <input type="text" value="{{ $value_1 }}" name="visitor_rate_id[field][{{$c->id}}]" id="visitor_rate_id{{$c->id}}field" class="{{ in_array($c->id,$ids)?'':'d-none' }}  rate-calculateion-data"> {{$c->visitor_name}}<sub>{{$c->visitor_helping_text}}</sub>
        </th>
        <td align="right">
            @if(isset($abc_amount))
                {!! ModelHelper::getDataFromSetting('payment_currency') !!}{{number_format($abc_amount,2)}}
            @endif
        </td>
     </tr>
    @endforeach
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
             
                    
                <tr>
                  <td colspan="4"  align="left"> Pet Fee</td>
                  <td align="right"> {!! $setting_data['payment_currency'] !!} {{number_format($pet_fee,2)}}</td>
                </tr>
            @endif
    
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
                <tr>
                  <td colspan="4"  align="left"> Heating the Swimming Pool fee</td>
                  <td align="right"> {!! $setting_data['payment_currency'] !!} {{number_format($heating_pool_fee1,2)}}</td>
                </tr>
            @endif
        @endif
    @endif
@endif
         
           
@foreach(App\Models\PropertyFee::where("property_id",$property->id)->where("fee_apply","gross")->get() as $c)
    @php  $fee=Helper::getFeeAmountAndName($c,$gross_amount); @endphp
    @if($fee['status']==true)
        <tr>
          <td colspan="4"  align="left">{{ $fee['name'] }}</td>
          <td align="right">{!! ModelHelper::getDataFromSetting('payment_currency') !!}{{number_format($fee['amount'],2)}}</td>
       </tr>
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
        $tax=round(($sub_total*$define_tax)/100,2);
        $sub_total+=$tax;$total_amount+=$tax; 
        
    @endphp
 
    <tr>
      <td colspan="4"  align="left"> Tax ({{ $define_tax }}%)</td>
      <td align="right">{!! $setting_data['payment_currency'] !!} {{number_format($tax,2)}}</td>
   </tr>

@endif
@if($sub_total!=$gross_amount)
   @if(App\Models\PropertyFee::where("property_id",$property->id)->where("fee_apply","total")->count()>0)
   <tr>
        <td colspan="4"  align="left"><strong>Sub total</strong></td>
        <td align="right">{!! ModelHelper::getDataFromSetting('payment_currency') !!}{{number_format($sub_total,2)}}</td>
   </tr>
   @endif
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
        <tr>
          <td colspan="4"  align="left"> Pet Fee</td>
          <td align="right"> {!! $setting_data['payment_currency'] !!} {{number_format($pet_fee,2)}}</td>
        </tr>
    @endif
@endif
@php $heating_pool_fee1=0; @endphp
@if($property->heating_pool_fee_type !="taxable")
    @if($property->heating_pool_fee)
        @if($property->heating_pool_fee>0)
            @if($heating_pool_fee=="Yes")
                @php $total_amount+=$property->heating_pool_fee; 
                     $heating_pool_fee1=$property->heating_pool_fee; @endphp
                    <tr>
                      <td colspan="4"  align="left"> Heating the Swimming Pool fee</td>
                      <td align="right"> {!! $setting_data['payment_currency'] !!} {{number_format($heating_pool_fee1,2)}}</td>
                   </tr>
            @endif
        @endif
    @endif
@endif
         
           @foreach(App\Models\PropertyFee::where("property_id",$property->id)->where("fee_apply","total")->get() as $c)
                @php  $fee=Helper::getFeeAmountAndName($c,$sub_total); @endphp
                @if($fee['status']==true)
                    <tr>
                      <td colspan="4"  align="left">{{ $fee['name'] }}</td>
                      <td align="right">{!! ModelHelper::getDataFromSetting('payment_currency') !!}{{number_format($fee['amount'],2)}}</td>
                   </tr>
                    @php 
                        $total_amount+=$fee['amount'];
                        $after_total_fees[]=[
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
            <tr >
                <td colspan="4"  align="left"><strong>Total</strong></td>
                <td align="right">{!! ModelHelper::getDataFromSetting('payment_currency') !!}{{number_format($total_amount,2)}}</td>
           </tr>
             
					    @if($main_data['coupon_discount'])
                            @if($main_data['coupon_discount']!="")
                                @if($main_data['coupon_discount']!=0)
                                
                                @php
                                $coupon=App\Models\Coupon::where(["property_id"=>Request::get("property_id"),"code"=>$main_data['coupon_discount_code']])->first();
                          
                                if($coupon){
                                
                                    if($coupon->type=="excat"){
                                        if($total_amount<$coupon->amount){
                                            $error=1;
                                        }else{
                                            $gaurav_discount=1; $apply=1; //$discount=$main_data['coupon_discount'];
                                            $discount=$coupon->amount;
                                            $apply_button_name="Applied";
                                        }
                                    }else{
                                        if($coupon->amount>100){
                                         $error=1;
                                        }else{
                                            $gaurav_discount=1; $apply=1; //$discount=$main_data['coupon_discount'];
                                            $discount=($total_amount*$coupon->amount)/100;
                                            $apply_button_name="Applied";
                                        }
                                    }
                                }
                                @endphp
                                
                                <tr>
                                    <td align="left" colspan="4" style="padding: 10px; " valign="top"><strong>Discount ({{ $main_data['coupon_discount_code'] }}):</strong></td>
                                    <td align="right" style="padding: 10px; " valign="top">- {!! $setting_data['payment_currency']  !!}{{number_format($discount,2) }}</td>
                                </tr>
                              
                                @endif
                            @endif
                        @endif
            
           @php
           
           $remaining_total_amount=$total_amount;
           if($main_data['extra_discount']){
               if($main_data['extra_discount']!=""){
                    if($main_data['extra_discount']>0){
                        if($main_data['extra_discount']<$remaining_total_amount  ){
                            $extra_discount=$main_data['extra_discount'];
                            $remaining_total_amount=$remaining_total_amount-$extra_discount;
                            $apply=1;
                        }
                    }
               }
           }
           
           $remaining_total_amount=$remaining_total_amount-$discount; @endphp
           @if($extra_discount>0)
                 <tr>
                        <td colspan="4"  align="left"><strong>Extra Discount  </strong></td>
                        <td align="right">-{!! ModelHelper::getDataFromSetting('payment_currency') !!}{{number_format($extra_discount,2)}}</td>
                   </tr>
           @endif
            @if($apply==1)
                  <tr>
                        <td colspan="4"  align="left"><strong>Total Amount after Discount</strong></td>
                        <td align="right">{!! ModelHelper::getDataFromSetting('payment_currency') !!}{{number_format($remaining_total_amount,2)}}</td>
                   </tr>
            @endif

@php
$days=Helper::calculateDays(date('Y-m-d'),$start_date);


$payment_interval=ModelHelper::getDataFromSetting("payment_interval");
if($payment_interval){
}else{
    $payment_interval=1;
}
//dd($payment_interval,$days);
$total_payment=$payment_interval;
$amount_data=[];
if($payment_interval==1){
        $first_amount=$remaining_total_amount;

        $first_message="Total Amount to be Paid";
        $total_payment=1;
        $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message];
}
elseif($payment_interval==2){

     $second_days=ModelHelper::getDataFromSetting('second_min_total_days');

    if($days>=$second_days){

        $second_per=ModelHelper::getDataFromSetting('second_payment_per');

        $second_amount=round(($remaining_total_amount*$second_per)/100,2);
        $first_amount=$remaining_total_amount-$second_amount;

        $second_days=ModelHelper::getDataFromSetting('second_how_many_days');
        $second_date=date('F jS, Y',strtotime('- '.$second_days.' days',strtotime($start_date)));


        $first_message="Initial Deposit to be Paid";
        $second_message="Final Remaining Amount Due on ".$second_date;
   
        $total_payment=2;
        $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message];
        $amount_data[]=["amount"=>$second_amount,"type"=>"second","message"=>$second_message]; 

    }else{
        $first_amount=$remaining_total_amount;

        $first_message="Total Amount to be Paid";
        $total_payment=1;
        $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message];
    } 
}
elseif($payment_interval==3){
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
        $first_message="Initial Deposit to be Paid";
        $second_message="Second Remaining Amount Due on ".$second_date;
        $third_message="Final Remaining Amount Due on ".$third_date;

        $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message];
        $amount_data[]=["amount"=>$second_amount,"type"=>"second","message"=>$second_message]; 
        $amount_data[]=["amount"=>$third_amount,"type"=>"third","message"=>$third_message]; 

    }elseif($days>=$second_days){

        $second_per=ModelHelper::getDataFromSetting('second_payment_per');

        $second_amount=round(($remaining_total_amount*$second_per)/100,2);
        $first_amount=$remaining_total_amount-$second_amount;

        $second_days=ModelHelper::getDataFromSetting('second_how_many_days');
        $second_date=date('F jS, Y',strtotime('- '.$second_days.' days',strtotime($start_date)));


        $first_message="Initial Deposit to be Paid";
        $second_message="Final Remaining Amount Due on ".$second_date;
   
        $total_payment=2;
        $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message];
        $amount_data[]=["amount"=>$second_amount,"type"=>"second","message"=>$second_message]; 

    }else{
        $first_amount=$remaining_total_amount;
        $total_payment=1;
        $first_message="Total Amount to be Paid";

        $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message];
    }   
}
@endphp
        @foreach($amount_data as $c)
            <tr>
                <td colspan="4"  align="left"><strong>{{$c['message']}}</strong></td>
                <td align="right">{!! ModelHelper::getDataFromSetting('payment_currency') !!}{{number_format($c['amount'],2)}}</td>
            </tr>
        @endforeach

       </table>
            </div>

  
       
       
      <input type="hidden" name="discount" value="{{ $main_data['coupon_discount'] }}"  id="coupon_discount"  />
      <input type="hidden" name="discount_coupon" value="{{ $main_data['coupon_discount_code'] }}"  id="coupon_discount_code"  />
      <input type="hidden" name="after_discount_total" value="{{ $remaining_total_amount }}" />
      
  
    
        
        <input type="hidden" name="pet_fee" value="{{ $pet_fee }}">
        
        <input type="hidden" name="guest_fee" value="{{ $guest_fee }}">
        
        <input type="hidden" name="rest_guests" value="{{ $rest_guests }}">
        
        <input type="hidden" name="single_guest_fee" value="{{ $single_guest_fee }}">
        
        
        <input type="hidden" name="total_payment" value="{{ $total_payment }}">
        <input type="hidden" name="amount_data" value="{{ json_encode($amount_data) }}">
 
        <input type="hidden" name="total_guests" value="{{ $total_guests }}" >

        <input type="hidden" name="gross_amount" value="{{ $gross_amount }}" >
        <input type="hidden" name="total_night" value="{{ $day }}" >
        <input type="hidden" name="sub_amount" value="{{ $sub_total }}" >
        <input type="hidden" name="total_amount" value="{{ $total_amount }}" >
        <input type="hidden" name="after_total_fees" value="{{ json_encode($after_total_fees) }}">
        <input type="hidden" name="before_total_fees" value="{{ json_encode($before_total_fees) }}">
         <input type="hidden" name="custom_before_total_fees" value="{{ json_encode($custom_before_total_fees) }}">
        <input type="hidden" name="request_id" value="{{ uniqid() }}" >
     <input type="hidden" name="tax" value="{{ $tax }}" >
        <input type="hidden" name="define_tax" value="{{ $define_tax }}" >
        
<input type="hidden" name="pet_fee_type" value="{{ $property->pet_fee_type }}" />
<input type="hidden" name="heating_pool_fee_type" value="{{ $property->heating_pool_fee_type }}" />
<input type="hidden" name="heating_pool_fee" value="{{ $property->heating_pool_fee }}" />
<input type="hidden" name="extra_amount" value="{{ $extra_amount }}" />
