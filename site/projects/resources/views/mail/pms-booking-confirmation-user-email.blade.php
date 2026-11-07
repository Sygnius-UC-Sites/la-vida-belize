<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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

           </style>
</head>
<body style=" width: 100%; height: 100%; margin: 0 auto; color: #000; background: #FFFFFF; font-family: Arial, sans-serif; font-size: 12px; font-family: Arial;">
    <div class="main-area" style=" width:700px; margin: auto;">
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
	                            ·  {{$property->bedroom}} bedrooms
	                        @endif
	                        @if($property->bathroom)
	                            · {{$property->bathroom}} bathrooms
	                        @endif
	                     
	                    </p>
	                </td>
	            </tr>
	        </table>
	        <p style=" font-size: 15px; color: #454545; line-height: 24px; font-weight: 400; margin: 0 0 15px 0; text-align: left">Your booking has been accepted. Please click on pay now button below to make the payment.</p>
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
	        
	        @include("mail.pms-booking-common-data")
	        	<div style="text-align: center;"><a href="{{ url('booking/rental-aggrement/'.$booking['id']) }}" style="background: #00a9dd;color: #fff;border: 1px solid #00a9dd;margin-top: 2rem;cursor: pointer;text-decoration: none;position: relative;padding: 1rem 3rem;font-size: 1rem;font-weight: 600;box-sizing: border-box;">Pay Now</a></div>
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