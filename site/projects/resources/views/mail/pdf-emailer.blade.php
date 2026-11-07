<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $setting_data['website'] }}</title>
   	@php
        $payment_currency= $setting_data['payment_currency'];
    @endphp
    <style>
        .clearfix:after {
         content: "";
         display: table;
         clear: both;
       }
       body {
         width: 100%;  
         height: 100%; 
         margin: 0 auto; 
         color: #000;
         background: #FFFFFF; 
         font-family: Arial, sans-serif; 
         font-size: 12px; 
         font-family: Arial;
       }
       a{
           color:#000;
           text-decoration: none;
       }
       p{
           margin-top: 0px;
           margin-bottom: 0px;
           line-height: 1.5;
           font-weight: 500;
       }
       table{
           border-spacing: 0px;
           width:100%;
       }
       .main-area{
           width:700px;
           margin: auto;
       }
       table.header {
    padding: 5px 0;
        }
        table.header td{
            font-weight: bold;
        }
       td.logo {
        text-align: center;
        }
        td.logo img{
            width: 120px;
        }
        td.date {
    text-align: right;
    }
    .pro-area {
    border: 1px solid #dddddd;
    border-radius: 12px;
    padding: 10px 15px;
    padding-bottom: 0px;
}
    table.pro-sec {
    padding: 10px 0;
    padding-bottom: 20px;
}
td.pro-img {
    width: 45%;
    text-align: right;
    padding-right: 10px;
}
td.pro-detail {
    width: 55%;
    vertical-align: top;
}
p.pro-type {
    font-size: 11px;
}
h1.pro-name {
    font-size: 16px;
    font-family: Cambria, Cochin, Georgia, Times, 'Times New Roman', serif;
    margin: 0;
}
p.pro-value {
    font-size: 13px;
}
td.pro-img img {
    width: 100px;
    border-radius: 8px;
    height: 85px;
    object-fit: cover;
}
.trip-sec {
    padding: 20px 0;
    border-top: 1px solid #dddddd;
}
h2 {
    font-size: 16px;
    font-family: Cambria, Cochin, Georgia, Times, 'Times New Roman', serif;
    margin: 0;
    margin-bottom: 15px;
}
p.guest-head {
    margin-top: 10px;
}
.price {
    padding: 20px 0;
    border-top: 1px solid #dddddd;
}
table.prices td{
    padding-bottom: 15px;
}
tr.total td, tr.discount td, tr.amt-discount td{
    padding-top: 15px;
    border-top: 1px solid #dddddd;
}
td.amt {
    text-align: right;
}
table.prices span {
    display: block;
    margin-top: 3px;
} 
table.footer img{
    width: 15px;
    display: inline-block !important;
    -ms-interpolation-mode: bicubic;
    vertical-align: middle;
}  
table.footer td.addr {
    text-align: right;
} 
table.footer td.mail, table.footer td.web-address {
    text-align: center;
}
table.footer b, table.copyright td{
font-size: 11px;
}
table.footer td{
    align-items: center;

}

table.footer {
    margin-top: 20px;
    margin-bottom: 20px;
}
.page-break {
    page-break-after: always;
}
           </style>
           <style>



.document-html h1, .document-html h2, .document-html h3, .document-html h4, .document-html h5, .document-html h6 {
    font-size: 20px;
    line-height: 1.3;
    font-weight: 700;
    color: #000;
    margin-bottom: 20px !important;
    
}
.document-html h2 {
    font-size: 18px;
}
.document-html h3 {
    font-size: 17px;
}
.document-html h4 {
    font-size: 15px);
}
.document-html h5 {
    font-size: 14px;
}
.document-html h6 {
    font-size: 13px;
}
.document-html p, .document-html span, .document-html div, .document-html td, .document-html th, .document-html li, .document-html pre, .document-html post, .document-html b, .document-html strong {
    font-size: 11px;
  
    margin-bottom: 15px;
    line-height: 1.5;
}
.document-html b, .document-html strong {
    font-weight:700;
}

.document-html ul, .document-html ol{
    padding-left: 20px;
}
.document-html th, .document-html td{
    display:block;
}
.document-html img{
    width: 100%;
    height: auto;
    max-height: 300px;
    object-fit: cover;
}


</style>
</head>
<body style=" width: 100%; height: 100%; margin: 0 auto; color: #000; background: #FFFFFF; font-family: Arial, sans-serif; font-size: 12px; font-family: Arial;">
    <div class="main-area otherpages" style=" width:700px; margin: auto;">
        <table class="header" style="padding: 5px 0; border-spacing: 0px; width:100%;">
            <tr>
                <td class="invoice" style="font-weight: bold; width: 33.33%;">Invoice No : {{$booking['id'] }}</td>
                <td class="logo" style="text-align: center; font-weight: bold; width: 33.33%;"><a href="{{ url('/') }}" style="color:#000; text-decoration: none;"><img src="{{ asset($setting_data['header_logo'] ?? 'front/images/logo.png') }}" alt="{{ $setting_data['website'] }}" style="width: 120px;"></a></td>
                <td class="date" style="text-align: right; font-weight: bold; width: 33.33%;">Date : {{date('F d-Y',strtotime($booking['created_at'])) }}</td>
            </tr>
        </table>
        <div class="pro-area" style="border: 1px solid #dddddd; border-radius: 12px; padding: 10px 15px; padding-bottom: 0px;">

	        <table class="pro-sec" style="padding: 10px 0; padding-bottom: 20px; border-spacing: 0px; width:100%;">
	            <tr>
	                <td class="pro-img" style="width: 45%; text-align: right; padding-right: 10px;">
	                    @if($property->feature_image)
	                    <img src="{{ asset($property->feature_image) }}" alt="{{$property->name }}" title="{{$property->name }}" style="width: 100px; border-radius: 8px; height: 85px; object-fit: cover;">
	                    @endif
	                </td>
	                <td class="pro-detail" style="width: 55%; vertical-align: top;">
	                    <p class="pro-type" style="font-size: 11px;">{{$property->heading }}</p>
	                    <h1 class="pro-name" style=" font-size: 16px; font-family: Cambria, Cochin, Georgia, Times, 'Times New Roman', serif; margin: 0;"><b>{{$property->name }}</b></h1>
	                    <p class="pro-value" style="font-size: 13px;">
	                        @if($property->sleeps)
	                            {{$property->sleeps}} Sleeps
	                        @endif
	                        @if($property->bedroom)
	                            路  {{$property->bedroom}} bedrooms
	                        @endif
	                        @if($property->bathroom)
	                            路 {{$property->bathroom}} bathrooms
	                        @endif
	                     
	                    </p>
	                </td>
	            </tr>
	        </table>
	        <p style=" font-size: 15px; color: #454545; line-height: 24px; font-weight: 400; margin: 0 0 15px 0; text-align: left">You have received a New Booking Request.</p>
	        <div class="trip-sec" style="padding: 20px 0; border-top: 1px solid #dddddd;">
	            <h2 style="font-size: 16px; font-family: Cambria, Cochin, Georgia, Times, 'Times New Roman', serif; margin: 0; margin-bottom: 15px;">Your trip</h2>
	            <table class="trip-detail" style="border-spacing: 0px; width:100%;">
	                <td class="trip-date">
	                    <p class="date-head"><b>Dates</b></p>
	                    <p class="tour-date">{{date('F d-Y',strtotime($booking['checkin'])) }} - {{date('F d-Y',strtotime($booking['checkout'])) }}</p>
	                    <p class="guest-head" style="margin-top: 10px;"><b>Guests</b></p>
	                    <p class="guest-type">{{$booking['total_guests'] }} Guests ({{$booking['adults']}} Adults, {{$booking['child']}} Child)</p>
	                </td>
	                <td class="renter-detail">
	                    <p><b>Renter Details</b></p>
	                    <p><b>Name :</b> {{$booking['name']}}</p>
	                    <p><b>Email :</b> {{$booking['email']}}</p>
	                    <p><b>Mobile :</b> {{$booking['mobile']}}</p>
	                </td>
	            </table>
	        </div>
	        	         @if(isset($payment))
    	        @if($payment)
	        <div class="price" style=" padding: 20px 0; border-top: 1px solid #dddddd;">
			    <h2 style="font-size: 16px; font-family: Cambria, Cochin, Georgia, Times, 'Times New Roman', serif; margin: 0; margin-bottom: 15px;">Payment details</h2>
			    <table class="prices" style="border-spacing: 0px; width:100%;">
			        
								 <tr class="price-per-night">
									<td style="padding-bottom: 15px;"><strong>Amount :</strong></td>
									<td class="amt" style="text-align: right; padding-bottom: 15px;">{!! ModelHelper::getDataFromSetting('payment_currency') !!}{{$payment->amount }}</td>
								</tr>
								 <tr class="price-per-night">
									<td style="padding-bottom: 15px;"><strong>Payment Mode :</strong></td>
									<td class="amt" style="text-align: right; padding-bottom: 15px;">{{$payment->type }}</td>
								</tr>
								 <tr class="price-per-night">
									<td style="padding-bottom: 15px;"><strong>Tran ID :</strong></td>
									<td class="amt" style="text-align: right; padding-bottom: 15px;">{{$payment->tran_id }}</td>
								</tr>
								@if($payment->balance_transaction)
								 <tr class="price-per-night">
									<td style="padding-bottom: 15px;"><strong>Balance transaction ID:</strong></td>
									<td class="amt" style="text-align: right; padding-bottom: 15px;">{{$payment->balance_transaction }}</td>
								</tr>
								@endif
								@if($payment->receipt_url)
								 <tr class="price-per-night">
									<td style="padding-bottom: 15px;"><strong>Receipt:</strong></td>
									<td class="amt" style="text-align: right; padding-bottom: 15px;"><a href="{{$payment->receipt_url }}">View Receipt</a></td>
								</tr>
								@endif
			    </table>
			</div>
			   @endif
	        @endif
	
	        
	        <div class="price" style=" padding: 20px 0; border-top: 1px solid #dddddd;">
                <h2 style="font-size: 16px; font-family: Cambria, Cochin, Georgia, Times, 'Times New Roman', serif; margin: 0; margin-bottom: 15px;">Price details</h2>
                <table class="prices" style="border-spacing: 0px; width:100%;">
                    <tr class="price-per-night">
                        <td style="padding-bottom: 15px;">Price Per Night x {{$booking['total_night'] }} nights</td>
                        <td class="amt" style="text-align: right; padding-bottom: 15px;">{!! $payment_currency !!}{{number_format($booking['gross_amount'],2) }}</td>
                    </tr>
            
                    @if($booking['custom_before_total_fees'])
                    @foreach(json_decode($booking['custom_before_total_fees']) as $c)
                    <tr class="admin-fee">
                        <td>{{$c->product_name}} :</td>
                        <td class="amt" style="text-align: right;">{!! $payment_currency !!}{{number_format($c->product_amount,2) }}</td>
                    </tr>
                    @endforeach
                    @endif
            
                    @if($booking['rest_guests'])
                        @if($booking['rest_guests']>0)
                            @if($booking['guest_fee'])
                                @if($booking['guest_fee']>0)
                                    <tr class="additional-fee">
                                        <td>Additional Guest Fee <span style="display: block; margin-top: 3px;">({{$booking['total_night']}} nights * {!! $setting_data['payment_currency'] !!}{{$booking['single_guest_fee']}} * {{$booking['rest_guests']}} Guests)</span></td>
                                        <td class="amt" style="text-align: right;">{!! $payment_currency !!}{{number_format($booking['guest_fee'],2) }}</td>
                                    </tr>
                                @endif
                            @endif
                        @endif
                    @endif
                    @if($booking['pet_fee_type']=="taxable")
                        @if($booking['total_pets'])
                            @if($booking['total_pets']>0)
                                @if($booking['pet_fee'])
                                    @if($booking['pet_fee']>0)
                                        <tr class="cleaning-fee">
                                            <td>Pet Fee :</td>
                                            <td class="amt" style="text-align: right;">{!! $payment_currency !!}{{number_format($booking['pet_fee'],2) }}</td>
                                        </tr>
                                    @endif
                                @endif
                            @endif
                        @endif
                    @endif
                    
                    @if($booking['heating_pool_fee_type']=="taxable")
                        @if($booking['heating_pool_fee'])
                            @if($booking['heating_pool_fee']>0)
                                <tr class="tax">
                                    <td>Heating the Swimming Pool fee :</td>
                                    <td class="amt" style="text-align: right;">{!! $payment_currency !!}{{number_format($booking['heating_pool_fee'],2) }}</td>
                                </tr>
                            @endif
                        @endif
                    @endif
                    @foreach(json_decode($booking['before_total_fees']) as $c)
                    <tr class="admin-fee">
                        <td>{{$c->name}} :</td>
                        <td class="amt" style="text-align: right;">{!! $payment_currency !!}{{number_format($c->amount ?? $c->value ?? 0,2) }}</td>
                    </tr>
                    @endforeach
            
                    
                    @if($booking['tax'])
                    <tr class="admin-fee">
                        <td>Tax ({{ $booking['define_tax'] ?? '' }}%):</td>
                        <td class="amt" style="text-align: right;">{!! $payment_currency !!}{{number_format($booking['tax'],2) }}</td>
                    </tr>
                    @endif
                    
                     @if($booking['sub_amount']!=$booking['gross_amount'])
                        @if(count(json_decode($booking['after_total_fees']))>0)
                            <tr class="admin-fee">
                                <td>Sub Total :</td>
                                <td class="amt" style="text-align: right;">{!! $payment_currency !!}{{number_format($booking['sub_amount'],2) }}</td>
                            </tr>
                        @endif
                    @endif
                    
                    @if($booking['pet_fee_type']!="taxable")
                        @if($booking['total_pets'])
                            @if($booking['total_pets']>0)
                                @if($booking['pet_fee'])
                                    @if($booking['pet_fee']>0)
                                        <tr class="admin-fee">
                                            <td>{{$c->name}} :</td>
                                            <td class="amt" style="text-align: right;">{!! $payment_currency !!}{{number_format($c->amount ?? $c->value ?? 0,2) }}</td>
                                        </tr>
                                    @endif
                                @endif
                            @endif
                        @endif
                    @endif
                
                    @if($booking['heating_pool_fee_type']!="taxable")
                        @if($booking['heating_pool_fee'])
                            @if($booking['heating_pool_fee']>0)
                                <tr class="admin-fee">
                                    <td>{{$c->name}} :</td>
                                    <td class="amt" style="text-align: right;">{!! $payment_currency !!}{{number_format($c->amount ?? $c->value ?? 0,2) }}</td>
                                </tr>
                            @endif
                        @endif
                    @endif
                    
                    @foreach(json_decode($booking['after_total_fees']) as $c)
                        <tr class="admin-fee">
                            <td>{{$c->name}} :</td>
                            <td class="amt" style="text-align: right;">{!! $payment_currency !!}{{number_format($c->amount ?? $c->value ?? 0,2) }}</td>
                        </tr>
                    @endforeach
            
            
            
            
                    <tr class="total">
                        <td style="padding-top: 15px; border-top: 1px solid #dddddd;"><b>Total :</b></td>
                        <td class="amt" style="text-align: right; padding-top: 15px; border-top: 1px solid #dddddd;"><b>{!! $payment_currency !!}{{number_format($booking['total_amount'],2) }}</b></td>
                    </tr>
                   
                    
            
            
                    @php $gaurav_discount=0;@endphp
                    @if($booking['discount'])
                        @if($booking['discount']!="")
                            @if($booking['discount']!=0)
                                @php $gaurav_discount=1;@endphp 
                                <tr class="discount">
                                    <td style="padding-top: 15px; border-top: 1px solid #dddddd;"><b>Discount ({{ $booking['discount_coupon'] }}) :</b></td>
                                    <td class="amt" style="text-align: right; padding-top: 15px; border-top: 1px solid #dddddd;"><b>- {!! $payment_currency !!}{{number_format($booking['discount'],2) }}</b></td>
                                </tr>
                            @endif
                        @endif
                    @endif
                    @if($booking['extra_discount'])
                        @if($booking['extra_discount']!="")
                            @if($booking['extra_discount']>0)
                                @php $gaurav_discount=1;@endphp 
                            
                                <tr class="discount">
                                    <td style="padding-top: 15px; border-top: 1px solid #dddddd;"><b>Extra Discount :</b></td>
                                    <td class="amt" style="text-align: right; padding-top: 15px; border-top: 1px solid #dddddd;"><b>- {!! $payment_currency !!}{{number_format($booking['extra_discount'],2) }}</b></td>
                                </tr>
                          
                            @endif
                        @endif
                    @endif
                    @if($gaurav_discount==1)
                    
            
                        <tr class="discount">
                            <td style="padding-top: 15px; border-top: 1px solid #dddddd;"><b>Total Amount after Discount :</b></td>
                            <td class="amt" style="text-align: right; padding-top: 15px; border-top: 1px solid #dddddd;"><b>{!! $payment_currency !!}{{number_format($booking['after_discount_total'],2) }}</b></td>
                        </tr>
                    @endif
                        
                       @if($booking['amount_data'])
                            @php
                                $amount_data=json_decode($booking['amount_data'],true);
                            @endphp
                            @if(is_array($amount_data))
                                @foreach($amount_data as $c)
                                    @php $status='';@endphp
                                    @if(isset($c['status']))
                                        @php $status='(<span style="color:green;">Paid</span>)'; @endphp
                                    @endif
                          
                                    <tr class="amt-discount">
                                        <td style="padding-top: 15px; border-top: 1px solid #dddddd;"><b>{{$c['message']}} {!! $status !!} :</b></td>
                                        <td class="amt" style="text-align: right; padding-top: 15px; border-top: 1px solid #dddddd;"><b>{!! $payment_currency !!}{{number_format($c['amount'],2) }}</b></td>
                                    </tr>
                                @endforeach
                            @endif
                        @endif
            
            
                </table>
                
                
                <div class="page-break"></div>
            <div class="trial">
           
                <table class="owner-profile">
                    <tr>
                        <th><b>Title</b></th>
                        <td>Contract Agreement</td>
                    </tr>
                    <tr>
                        <th colspan="2"><b>Rental Agreement </b></th>
                        
                    </tr>
                    <tr>
                       
                        
                    </tr>
                 
                </table>
                 <div class="document-html">
                         {!!  $property->notes !!}
                        
                        </div>
                  <div class="page-break"></div>
                    <table class="owner-profile">
                           <tr>
                        <th><b>File Name</b></th>
                        <td>{{$pdf_name}}</td>
                    </tr>
                 
                    <tr>
                        <th><b>Status</b></th>
                        <td><img src="{{ asset('front/pdf') }}/images/circle.png" alt="Circle">Signed</td>
                    </tr>
                    <tr>
                        <td>
                            <div class="sign">
                                <p><b>Renter's Signature</b></p>
                                <img src="{{ asset($booking['rental_aggrement_signature']) }}" alt="Sign" style="width:100px;">
                            </div>
                        </td>
                        <td>
                            <div class="sign">
                                <p><b>Renter's Proof</b></p>
                                <img src="{{ asset($booking['rental_aggrement_images']) }}" alt="Proof"  style="width:100px;">
                            </div>
                        </td>
                    </tr>
                </table>
                <div class="signed">
                    <p><b>This document was signed on {{url('/')}}</b></p>
                </div>
                <div class="history">
                    <h2>Document History</h2>
                    <table class="document-history">
                        <tr class="sent">
                            <td class="img">
                                <img src="{{ asset('front/pdf') }}/images/log-out.png" alt="Sent">
                                <p><b>Quote Date</b></p>
                            </td>
                            <td class="date">
                                <p><b>{{$booking['quote_date']}}</b></p>
                            </td>
                            <td class="cont">
                                <p>{{$booking['name']}} ({{$booking['email']}})</p>
                         
                                <p>IP: {{$booking['quote_ip']}}</p>
                            </td>
                        </tr>
                        <tr class="sign">
                            <td class="img">
                                <img src="{{ asset('front/pdf') }}/images/contract.png" alt="Signed">
                                <p><b>SIGNED</b></p>
                            </td>
                            <td class="date">
                                <p><b>{{$booking['rentar_agreement_date']}}</b></p>
                            </td>
                            <td class="cont">
                                <p>Signed by {{$booking['name']}} ({{$booking['email']}})</p>
                                <p>IP: {{$booking['rentar_agreement_ip']}}</p>
                            </td>
                        </tr>
                        <tr class="complete">
                            <td class="img">
                                <img src="{{ asset('front/pdf') }}/images/checked.png" alt="Completed">
                                <p><b>COMPLETED</b></p>
                            </td>
                            <td class="date">
                                <p><b>{{$booking['rentar_agreement_date']}}</b></p>
                            </td>
                            <td class="cont">
                                <p>The document has been completed.</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <table class="footer" style="margin-top: 20px; margin-bottom: 20px; border-spacing: 0px; width:100%;">
            <tr>
                <td class="ph" style="align-items: center;"><img src="{{ asset('front/emailer') }}/images/telephone.png" alt="Phone" style="width: 15px; display: inline-block !important; -ms-interpolation-mode: bicubic; vertical-align: middle;"> <b style="font-size: 11px;">{!! $setting_data['mobile'] !!}</b></td>
                <td class="mail" style="align-items: center; text-align: center;"><img src="{{ asset('front/emailer') }}/images/envelope.png" alt="Email" style="width: 15px; display: inline-block !important; -ms-interpolation-mode: bicubic; vertical-align: middle;"> <b style="font-size: 11px;">{!! $setting_data['email'] !!}</b></td>
                <td class="web-address" style="align-items: center; text-align: center;"><img src="{{ asset('front/emailer') }}/images/web.png" alt="Web Address" style="width: 15px; display: inline-block !important; -ms-interpolation-mode: bicubic; vertical-align: middle;"> <b style="font-size: 11px;">{!! $setting_data['website'] !!}</b></td>
                <td class="addr" style="align-items: center; text-align: right;"><img src="{{ asset('front/emailer') }}/images/pin.png" alt="Address" style="width: 15px; display: inline-block !important; -ms-interpolation-mode: bicubic; vertical-align: middle;"> <b style="font-size: 11px;">{!! $setting_data['address'] !!}</b></td>
            </tr>
        </table>
    </div>
</body>
</html>