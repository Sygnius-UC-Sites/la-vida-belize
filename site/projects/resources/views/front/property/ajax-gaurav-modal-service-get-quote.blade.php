    @php
        $start_date=$main_data["start_date"];
        $end_date=$main_data["end_date"];
  


        $gross_amount=$main_data['gross_amount'];
        $day=$main_data['total_night'];
        $sub_total=$gross_amount;
        $total_amount=$gross_amount;
  $heating_pool_fee_data_guarav="No";
        $heating_pool_fee_data_guarav=$main_data['heating_pool_fee_data_guarav'];
        
        $total_guests=$main_data["adults"]+$main_data["childs"];
        $total_pets=$main_data['pet_fee_data_guarav'];
        $before_total_fees=[];
        $after_total_fees=[];
         $extra_amount=$main_data['extra_amount'];
        $tax=0;
    @endphp
    
@if($property->pet_fee_type =="taxable")    
    @if($property->pet_fee)
        @if($property->pet_fee>0)
            @if($total_pets>0)
             @php 
            if($property->pet_fee_interval=="per_pet")
            {
                $pet_fee=$property->pet_fee*$total_pets;
            }else{
                $pet_fee=$property->pet_fee;
            }
             
                    $sub_total+=$pet_fee;$total_amount+=$pet_fee; 
                    @endphp
                    
                 
            @endif
        @endif
    @endif
@endif
@if($property->guest_fee)
    @if($property->guest_fee>0)
        @if($property->no_of_guest)
            @if($property->no_of_guest<$total_guests)
                @php $rest_guests=$total_guests-$property->no_of_guest; @endphp
                @php $guest_fee=$property->guest_fee*$day*$rest_guests; @endphp
                @php 
                $sub_total+=$guest_fee;$total_amount+=$guest_fee; 
                @endphp
       
            @endif
        @endif
    @endif
@endif
@if($extra_amount)
    @if($extra_amount>0)
    @php     $sub_total+=$extra_amount;$total_amount+=$extra_amount; @endphp
  
    @endif
@endif
@if($property->heating_pool_fee_type =="taxable")
    @if($property->heating_pool_fee)
        @if($heating_pool_fee_data_guarav=="Yes")
        @php
        
                    $sub_total+=$property->heating_pool_fee;$total_amount+=$property->heating_pool_fee; 
                    @endphp
                    
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

@if($property->tax)
    @php
        $tax=round(($sub_total*$property->tax)/100,2);
        $sub_total+=$tax;$total_amount+=$tax; 
        
    @endphp
    



@endif




@php $service_fee=0; @endphp
@if($property->heating_pool_fee_type !="taxable")
    @if($property->heating_pool_fee)
        @if($heating_pool_fee_data_guarav=="Yes")
        @php
        
                    $service_fee+=$property->heating_pool_fee;$total_amount+=$property->heating_pool_fee; 
                    @endphp
                      <div class="row">
            		    <div class="col-md-9">
            		            Heating the Swimming Pool fee
            		    </div>
            		    <div class="col-md-3">
            		       {!! $setting_data['payment_currency'] !!} {{number_format($property->heating_pool_fee,2)}}
            		    </div>
            		</div>
                    
        @endif
    @endif
@endif
    
@if($property->pet_fee_type !="taxable")    
    @if($property->pet_fee)
        @if($property->pet_fee>0)
            @if($total_pets>0)
             @php 
            if($property->pet_fee_interval=="per_pet")
            {
                $pet_fee=$property->pet_fee*$total_pets;
            }else{
                $pet_fee=$property->pet_fee;
            }
             
                    $service_fee+=$pet_fee;$total_amount+=$pet_fee; 
                    @endphp
                     <div class="row">
            		    <div class="col-md-9">
            		        Pet Fee
            		    </div>
            		    <div class="col-md-3">
            		       {!! $setting_data['payment_currency'] !!} {{number_format($pet_fee,2)}}
            		    </div>
            		</div>
                 
            @endif
        @endif
    @endif
@endif
@foreach(App\Models\PropertyFee::where("property_id",$property->id)->where("fee_apply","total")->get() as $c)
    @php  $fee=Helper::getFeeAmountAndName($c,$sub_total); @endphp
    @if($fee['status']==true)
      <div class="row">
		    <div class="col-md-9">
		        {{ $fee['name'] }}
		    </div>
		    <div class="col-md-3">
		       {!! $setting_data['payment_currency'] !!} {{number_format($fee['amount'],2)}}
		    </div>
		</div>
       
        @php $total_amount+=$fee['amount']; $service_fee+=$fee['amount'];
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
