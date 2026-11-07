<title></title>
<meta http–equiv=“Content-Type” content=“text/html; charset=UTF-8” /><meta http–equiv=“X-UA-Compatible” content=“IE=edge” /><meta name=“viewport” content=“width=device-width, initial-scale=1.0 “ />
<style type="text/css">@media screen {
	@font-face {
		font-family: 'Lato';
		font-style: normal;
		font-weight: 400;
		src: local('Lato Regular'), local('Lato-Regular'), url(https://fonts.gstatic.com/s/lato/v11/qIIYRU-oROkIk8vfvxw6QvesZW2xOQ-xsNqO47m55DA.woff) format('woff');
	}
	body {margin:0px !important; padding:0px !important; display:block !important; min-width:100% !important; width:100% !important; -webkit-text-size-adjust:none;font-family: 'Poppins', sans-serif;}
	table {border-spacing:0; mso-table-lspace:0pt; mso-table-rspace:0pt;}
	table td {border-collapse: collapse;}
	strong {font-weight: bold !important;}
	td img {-ms-interpolation-mode:bicubic; display:block; width:auto; max-width:auto; height:auto; margin:auto;display:block!important;border:0px!important;}
	td a {text-decoration:none !important;}
    b, strong{
        font-weight: bold;
    }
    td{
        border: 1px solid #ff7713; font-weight: bold; font-size: 15px;
    }
	@media only screen and (min-width:481px) and (max-width:699px) {


	}

	@media screen and (max-width: 480px) {



	}
</style>
<table align="center" border="0" cellpadding="0" cellspacing="0" style="width:90%; margin: auto;">
	<tbody>
		<tr>
			<td align="center" bgcolor="#ffffff" style="padding:15px;" valign="top">
			<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">
				<tbody>
					<tr>
						<td align="center" valign="top">
						<div><span style="display: block; font-size: 30px; font-weight: 600; margin: 20px 0 10px 0; color: #ff7713; font-family: 'Poppins', sans-serif;">Booking Confirmation</span></div>
						</td>
					</tr>
				</tbody>
			</table>
			</td>
		</tr>
		<tr>
			<td align="left" bgcolor="#f9f9f9" style="padding:20px;" valign="top">
			<table align="left" border="0" cellpadding="0" cellspacing="0" width="100%">
				<tbody>
					<tr>
						<td align="left" valign="top">
						<h4 style="font-size: 17px; color: #000; font-weight: 600; margin-bottom: 0px; margin-top: 0px; font-family: 'Poppins', sans-serif;">Hey {{ModelHelper::getDataFromSetting('mailer_admin_name') }},</h4>

						<p style=" font-size: 15px; color: #000; line-height: 24px; font-weight: 400; margin: 0 0 15px 0; text-align: left; font-family: 'Poppins', sans-serif;">Payment.</p>
						
						<table align="left" border="0" cellpadding="0" cellspacing="0" width="100%">
							<tbody>
								<tr>
									<th colspan="2" align="center" style="padding: 10px; background: #ff7713; color: #fff; text-align: center; font-size: 15px;" valign="top"><strong>Property Detail </strong></th>
								</tr>

								<tr>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; font-family: 'Poppins', sans-serif; color:#000; border-right:0px solid #ff7713;" valign="top"><strong>Property Name :</strong></td>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; font-family: 'Poppins', sans-serif; color:#000;" valign="top">{{$property->name }}</td>
								</tr>
							</tbody>
						</table>
						<table align="left" border="0" cellpadding="0" cellspacing="0" width="100%">
							<tbody>
								<tr>
									<th colspan="2" align="center" style="padding: 10px; background: #ff7713; color: #fff; text-align: center; font-size: 15px;" valign="top"><strong>Payment Detail </strong></th>
								</tr>

								<tr>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; font-family: 'Poppins', sans-serif; color:#000; border-right:0px solid #ff7713;" valign="top"><strong>Amount :</strong></td>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; font-family: 'Poppins', sans-serif; color:#000;" valign="top">{!! ModelHelper::getDataFromSetting('payment_currency') !!}{{$payment->amount }}</td>
								</tr>
								<tr>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; font-family: 'Poppins', sans-serif; color:#000; border-right:0px solid #ff7713;" valign="top"><strong>Payment Mode :</strong></td>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; font-family: 'Poppins', sans-serif; color:#000;" valign="top">{{$payment->type }}</td>
								</tr>
								<tr>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; font-family: 'Poppins', sans-serif; color:#000; border-right:0px solid #ff7713;" valign="top"><strong>Tran ID :</strong></td>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; font-family: 'Poppins', sans-serif; color:#000;" valign="top">{{$payment->tran_id }}</td>
								</tr>
								@if($payment->balance_transaction)
								<tr>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; font-family: 'Poppins', sans-serif; color:#000; border-right:0px solid #ff7713;" valign="top"><strong>Balance transaction ID:</strong></td>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; font-family: 'Poppins', sans-serif; color:#000;" valign="top">{{$payment->balance_transaction }}</td>
								</tr>
								@endif
								@if($payment->receipt_url)
								<tr>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; font-family: 'Poppins', sans-serif; color:#000; border-right:0px solid #ff7713;" valign="top"><strong>Receipt:</strong></td>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; font-family: 'Poppins', sans-serif; color:#000;" valign="top"><a href="{{$payment->receipt_url }}">View Receipt</a></td>
								</tr>
								@endif
							</tbody>
						</table>
						<table align="left" border="0" cellpadding="0" cellspacing="0" width="100%">
							<tbody>
								<tr>
									<th colspan="2" align="center" style="padding: 10px; background: #ff7713; color: #fff; text-align: center; font-size: 15px;" valign="top"><strong>User Detail </strong></th>
								</tr>
								<tr>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; color:#000; border-right:0px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top"><strong>Name :</strong></td>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; color:#000; border-bottom:0px solid #ff7713;" valign="top">{{$data['name']}}</td>
								</tr>
								<tr>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; color:#000; border-right:0px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top"><strong>Email :</strong></td>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; color:#000; border-bottom:0px solid #ff7713;" valign="top">{{$data['email']}}</td>
								</tr>
								<tr>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; color:#000; border-right:0px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top"><strong>Mobile:</strong></td>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; color:#000; border-bottom:0px solid #ff7713;" valign="top">{{$data['mobile']}}</td>
								</tr>
								<tr>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; color:#000; border-right:0px solid #ff7713;" valign="top"><strong>Message :</strong></td>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; color:#000;" valign="top">
										{{$data['message']}}
									</td>
								</tr>
								
								@if($data['rental_aggrement_signature'])
								<tr>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; color:#000; border-right:0px solid #ff7713;" valign="top"><strong>Rental Agreement Signature :</strong></td>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; color:#000;" valign="top">
										<img src="{{asset($data['rental_aggrement_signature'])}}" style="width:160px;height:100px;" />
									</td>
								</tr>
								@endif
								@if($data['rental_aggrement_images'])
								<tr>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; color:#000; border-right:0px solid #ff7713;" valign="top"><strong>Rental Agreement Image :</strong></td>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; color:#000;" valign="top">
										<img src="{{asset($data['rental_aggrement_images'])}}" style="width:160px;height:100px;" />
									</td>
								</tr>
								@endif
								@if($data['rental_agreement_link'])
								<tr>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; color:#000; border-right:0px solid #ff7713;" valign="top"><strong>Rental Agreement Link :</strong></td>
									<td align="left" style="padding: 10px; border: 1px solid #ff7713; font-weight: bold; font-size: 15px; color:#000;" valign="top">
										<a href="{{asset($data['rental_agreement_link'])}}"  > Click Here</a>
									</td>
								</tr>
								@endif
							</tbody>
						</table>
						@include("mail.pms-booking-common-data")
						</td>
					</tr>
				</tbody>
			</table>
			</td>
		</tr>
		<tr>
			<td align="center" bgcolor="#ffffff" style="padding:15px;" valign="top">
			<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">
				<tbody>
					<tr>
						<td align="center" valign="top">
						<div><span style="display: block; font-size: 16px; font-weight: 600; margin: 0px 0 10px 0; color: #000;">Thanks for reading!</span></div>

						<p style=" font-size: 14px; color: #000; line-height: 24px; font-weight: 400; margin: 0 0 5px 0;">{!! ModelHelper::getDataFromSetting('mail_footer') !!}</p>
						</td>
					</tr>
				</tbody>
			</table>
			</td>
		</tr>
	</tbody>
</table>
