@php
    $start_date=$main_data["start_date"];
    $end_date=$main_data["end_date"];
    $gross_amount=$main_data['gross_amount'];
    $day=$main_data['total_night'];
    $sub_total=$gross_amount;
    $total_amount=$gross_amount;
    $pet_fee=0;
    $guest_fee=0;
    $before_total_fees=[];
    $after_total_fees=[];
    $heating_pool_fee_data_guarav="No";
    $heating_pool_fee_data_guarav=$main_data['heating_pool_fee_data_guarav'];
    $extra_amount=$main_data['extra_amount'];
    $total_guests=$main_data["adults"]+$main_data["childs"];
    $total_pets=$main_data['pet_fee_data_guarav'];
    $start_date=$main_data["start_date"];
    $end_date=$main_data["end_date"];
    $gross_amount=$main_data['gross_amount'];
    $day=$main_data['total_night'];
    $sub_total=$gross_amount;
    $total_amount=$gross_amount;
    $before_total_fees=[];
    $after_total_fees=[];
    $security_deposit_data = [];
    if($property->security_deposit_amount){
      if($property->security_deposit_text){
         $security_deposit_data = [ 'text' =>$property->security_deposit_text,'amount' => $property->security_deposit_amount];
       }
     }
     $is_creadit = false;
@endphp

<div class="row">
    <div class="col-md-6 days-area">
        <a href="javascript:;" class="days">{{$day}} nights</a>
        <div class="days-box">
            <div class="upper">
                <i class="fa fa-times close-date"></i>
                <h4>Days</h4>
            </div>
            <div class="inner-area">
                @php

                $child_property=[];
                $child_pobject=App\Models\Property::where("is_parent",$property->id)->get();
                foreach($child_pobject as $dbc){
                    $child_property[]=$dbc;
                }
                for($i=0;$i<$day;$i++){
                    $date = strtotime($start_date);
                    $date = strtotime("+".$i." day", $date);
                    $date= date('Y-m-d', $date);
                    if(count($child_property)==0){
                        $rate=App\Models\PropertyRate::where(["property_id"=>$property->id,"single_date"=>$date])->orderBy("id","desc")->first();
                        if($rate){
                            $price=$rate->price;
                        }else{
                            $price=$property->standard_rate;
                        }
                    }else{
                      $price=0;
                      foreach($child_property as $c_property){
                          $rate=App\Models\PropertyRate::where(["property_id"=>$c_property->id,"single_date"=>$date])->orderBy("id","desc")->first();
                          if($rate){
                              $price+=$rate->price;
                          }else{
                              $price+=$c_property->standard_rate;
                          }
                      }
                    }
                @endphp
                    <div class="row">
                        <div class="col-lg-6 col-md-6 col-sm-12 left">
                            <p>{{$date}}</p>
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-12 right">
                            <p> {!! $setting_data['payment_currency'] !!} {{ number_format($price,2) }}</p>
                        </div>
                    </div>
                @php
                }
                @endphp
            </div>
            <div class="bottom">
                <hr>
                <div class="row total-price">
                    <div class="col-lg-6 col-md-6 col-sm-12 left">
                        <p>Total Price</p>
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-12 right">
                        <p> {!! $setting_data['payment_currency'] !!} {{ number_format($gross_amount,2) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
       {!! $setting_data['payment_currency'] !!} {{ number_format($gross_amount,2) }}
    </div>
</div>
@if($extra_amount)
    @if($extra_amount>0)
    @php     $sub_total+=$extra_amount;$total_amount+=$extra_amount; @endphp
       <div class="row">
            <div class="col-md-6">
               Fee (Min. stay not met)
                <span class="icon">!<span class="box-msg">
                    Minimum nights not met 
                </span></span>
            </div>
            <div class="col-md-6">
               {!! $setting_data['payment_currency'] !!} {{number_format($extra_amount,2)}}
            </div>
        </div>
    @endif
@endif
@if($property->guest_fee)
    @if($property->guest_fee>0)
        @if($property->no_of_guest)
            @if($property->no_of_guest<$total_guests)
                @php $rest_guests=$total_guests-$property->no_of_guest;  $guest_fee=$property->guest_fee*$day*$rest_guests;$sub_total+=$guest_fee;$total_amount+=$guest_fee; 
                @endphp
                   <div class="row">
                        <div class="col-md-6">
                            Additional Guest Fee <br> <span style="font-size:13px;">({{$day}} nights * {!! $setting_data['payment_currency'] !!}{{$property->guest_fee}} * {{$rest_guests}} Guests)</span>
                        </div>
                        <div class="col-md-6">
                           {!! $setting_data['payment_currency'] !!} {{number_format($guest_fee,2)}}
                        </div>
                    </div>
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
            $sub_total+=$pet_fee;$total_amount+=$pet_fee; 
        @endphp
        <div class="row">
            <div class="col-md-6">
                Pet Fee
            </div>
            <div class="col-md-6">
               {!! $setting_data['payment_currency'] !!} {{number_format($pet_fee,2)}}
            </div>
        </div>
    @endif
@endif
@if($property->heating_pool_fee_type =="taxable")
    @if($property->heating_pool_fee)
        @if($heating_pool_fee_data_guarav=="Yes")
            @php  $sub_total+=$property->heating_pool_fee;$total_amount+=$property->heating_pool_fee; @endphp        
            <div class="row">
                <div class="col-md-6">
                    Heating the Swimming Pool fee
                </div>
                <div class="col-md-6">
                   {!! $setting_data['payment_currency'] !!} {{number_format($property->heating_pool_fee,2)}}
                </div>
            </div>
        @endif
    @endif
@endif

@foreach(App\Models\PropertyFee::where("property_id",$property->id)->where("fee_apply","gross")->get() as $c)
    @php  $fee=Helper::getFeeAmountAndName($c,$gross_amount); @endphp
    @if($fee['status']==true)
       <div class="row">
            <div class="col-md-6">
                {{ $fee['name'] }}
            </div>
            <div class="col-md-6">
               {!! $setting_data['payment_currency'] !!} {{number_format($fee['amount'],2)}}
            </div>
        </div>
        @php 
            $sub_total+=$fee['amount'];$total_amount+=$fee['amount']; 
            $before_total_fees[]=["name"=>$fee['name'],"amount"=>$fee['amount'],"fee_name"=>$c->fee_name,"fee_rate"=>$c->fee_rate,"fee_type"=>$c->fee_type,"fee_apply"=>$c->fee_apply,"fee_status"=>$c->fee_status];
        @endphp
    @endif
@endforeach
    <div class="row">
        <div class="col-md-6">
          <b>Sub-Total:</b>
        </div>
        <div class="col-md-6">
           <b>{!! $setting_data['payment_currency'] !!} {{number_format($sub_total,2)}}</b>
        </div>
    </div>
@if($property->tax)
    @php $tax=round(($sub_total*$property->tax)/100,2); $sub_total+=$tax;$total_amount+=$tax;  @endphp
    <div class="row">
        <div class="col-md-6">
           Lodging  Tax ({{ $property->tax }}%)
        </div>
        <div class="col-md-6">
           {!! $setting_data['payment_currency'] !!} {{number_format($tax,2)}}
        </div>
    </div>
@endif
     <div class="row">
        <div class="col-md-6">
            <b>TOTAL BOOKING COST</b>
        </div>
        <div class="col-md-6">
           <b>{!! $setting_data['payment_currency'] !!} {{number_format($sub_total,2)}}</b> 
        </div>
    </div>
@php $service_fee=0; $service_html=''; @endphp

@if($property->heating_pool_fee_type !="taxable")
    @if($property->heating_pool_fee)
        @if($heating_pool_fee_data_guarav=="Yes")
            @php $heating_pool_fee=$property->heating_pool_fee; $service_fee+=$property->heating_pool_fee;$total_amount+=$property->heating_pool_fee; 
              $service_html.='<div class="row">
                    <div class="col-lg-6 col-md-6 col-sm-12 ">
                        Heating Pool Fee
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-12 ">
                        '.$setting_data['payment_currency'] .' '.number_format($heating_pool_fee,2).' 
                    </div>
                </div>';
            @endphp
        @endif
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
            $service_fee+=$pet_fee;$total_amount+=$pet_fee; 
            $service_html.='<div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12 ">
                    Pet Fee
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12 ">
                    '.$setting_data['payment_currency'] .' '.number_format($pet_fee,2).'
                </div>
            </div>';
        @endphp    
    @endif
@endif
@foreach(App\Models\PropertyFee::where("property_id",$property->id)->where("fee_apply","total")->get() as $c)
    @php  $fee=Helper::getFeeAmountAndName($c,$sub_total); @endphp
    @if($fee['status']==true)
        @php $total_amount+=$fee['amount']; $service_fee+=$fee['amount']; $is_creadit = false;
            if(Helper::checkCreditCardString($fee['name']) === true){ $is_creadit = true;}
            $after_total_fees[]=["name"=>$fee['name'],"amount"=>$fee['amount'],"fee_name"=>$c->fee_name,"fee_rate"=>$c->fee_rate,"fee_type"=>$c->fee_type,"fee_apply"=>$c->fee_apply,"fee_status"=>$c->fee_status];
            $service_html.='<div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12 ">
                    '.$fee['name'].'
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12 ">
                    '.$setting_data['payment_currency'] .' '.number_format($fee['amount'],2).' 
                </div>
            </div>';
        @endphp
    @endif
@endforeach
  {!! $service_html !!}
<div class="row d-none">
    <div class="col-md-6">
        <a href="javascript:;" class="additional">Additional Fee</a>
        <div class="additional-box">
            <div class="upper">
                <i class="fa fa-times close-additional"></i>
                <h4>Additional Fee</h4>
            </div>
            <div class="inner-area">
                {!! $service_html !!}
            </div>
            <div class="bottom">
                <hr>
                <div class="row total-price">
                    <div class="col-lg-6 col-md-6 col-sm-12 left">
                        <p>Total Additional Fee</p>
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-12 right">
                        <p>{!! $setting_data['payment_currency'] !!} {{number_format($service_fee,2)}}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
       {!! $setting_data['payment_currency'] !!} {{number_format($service_fee,2)}}
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <b>Total @if($is_creadit) with credit card processing fee @endif :</b> 
    </div>
    <div class="col-md-6">
       <b>{!! $setting_data['payment_currency'] !!} {{number_format($total_amount,2)}}</b> 
    </div>
</div>

@if(isset($security_deposit_data['amount']))
<div class="row">
    <div class="col-md-6">
        <b>{!! $security_deposit_data['text'] !!} :</b> 
    </div>
    <div class="col-md-6">
       <b>{!! $setting_data['payment_currency'] !!} {{number_format($security_deposit_data['amount'] ,2)}}</b> 
    </div>
</div>
@endif