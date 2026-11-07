    @php
        $start_date=$main_data["start_date"];
        $end_date=$main_data["end_date"];
  


        $gross_amount=$main_data['gross_amount'];
        $day=$main_data['total_night'];
        $sub_total=$gross_amount;
        $total_amount=$gross_amount;

        $before_total_fees=[];
        $after_total_fees=[];
    @endphp
@php
   for($i=0;$i<$day;$i++){
	         	$date = strtotime($start_date);
	            $date = strtotime("+".$i." day", $date);
	            $date= date('Y-m-d', $date);
	            $rate=App\Models\PropertyRate::where(["property_id"=>$property->id,"single_date"=>$date])->orderBy("id","desc")->first();
if($rate){
    $price=$rate->price;
}else{
    $price=$property->standard_rate;
}
@endphp



<div class="row">
    <div class="col-md-6">
        {{$date}}
    </div>
    <div class="col-md-6">
       {!! $setting_data['payment_currency'] !!} {{ number_format($price,2) }}
    </div>
</div>

@php

}
@endphp