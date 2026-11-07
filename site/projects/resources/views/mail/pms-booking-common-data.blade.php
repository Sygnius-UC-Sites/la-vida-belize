

<table align="left" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 30px;">
							<tbody>
								<tr>
									<th colspan="5" align="center" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000;" valign="top"><strong>Booking Detail </strong></th>
								</tr>

								<tr>
									<th align="left" style="padding: 10px; background: #ff7713; color: #fff; text-align: center; font-size: 15px;" valign="top"><strong>Checkin :</strong></th>
									<th align="left" style="padding: 10px; background: #ff7713; color: #fff; text-align: center; font-size: 15px;" valign="top"><strong>Checkout :</strong></th>
									<th align="left" style="padding: 10px; background: #ff7713; color: #fff; text-align: center; font-size: 15px;" valign="top"><strong>Total Guest :</strong></th>
									<th align="left" style="padding: 10px; background: #ff7713; color: #fff; text-align: center; font-size: 15px;" valign="top"><strong>Total Night :</strong></th>
									<th align="center" style="padding: 10px; background: #ff7713; color: #fff; text-align: center; font-size: 15px;" valign="top"><strong>Gross Amount :</strong></th>
									
								</tr>
								<tr>
									<td align="left" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #ff7713; border-right:0px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top">{{$data['checkin'] }}</td>
									<td align="left" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #ff7713; border-right:0px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top">{{$data['checkout'] }}</td>
									<td align="left" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #ff7713; border-right:0px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top">{{$data['total_guests'] }} ({{$data['adults']}} Adults, {{$data['child']}} Child)</td>
									<td align="left" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #ff7713; border-right:0px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top">{{$data['total_night'] }}</td>
									<td align="center" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($data['gross_amount'],2) }}</td>
								</tr>
								
								
								
								
								@foreach(json_decode($data['before_total_fees']) as $c)
								<tr>
									<td align="left" colspan="4" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #ff7713; border-right:0px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top"><strong>{{$c->name}} :</strong></td>
									<td align="center" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($c->value ?? $c->amount ?? 0,2) }}</td>
								</tr>
								@endforeach
								
							
								
								@foreach(json_decode($data['after_total_fees']) as $c)
								<tr>
									<td align="left" colspan="4" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #ff7713; border-right:0px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top"><strong>{{$c->name}} ({{ $c->details }}) :</strong></td>
									<td align="center" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($c->value ?? $c->amount ?? 0,2) }}</td>
								</tr>
								@endforeach
								
							
								
                                
								 @if($data['tax'])
                                   
                                    	<tr>
        									<td align="left" colspan="4" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #ff7713; border-right:0px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top"><strong>Tax :</strong></td>
        									<td align="center" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($data['tax'],2) }}</td>
        								</tr>
                                   
                                @endif
								
								
								 @if($data['sub_amount']!=$data['gross_amount'])
                                    @if(count(json_decode($data['after_total_fees']))>0)
                                    	<tr>
        									<td align="left" colspan="4" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #ff7713; border-right:0px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top"><strong>Sub Total :</strong></td>
        									<td align="center" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($data['sub_amount'],2) }}</td>
        								</tr>
                                    @endif
                                @endif
								
								
								<tr>
									<td align="left" colspan="4" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #ff7713; border-right:0px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top"><strong>Total :</strong></td>
									<td align="center" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($data['total_amount'],2) }}</td>
								</tr>
								
								<tr>
									<td align="left" colspan="4" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #ff7713; border-right:0px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top"><strong>Reservation deposit <span style="color:green;">(Paid)</span>:</strong></td>
									<td align="center" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #ff7713; border-bottom:0px solid #ff7713;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($data['total_payment'],2) }}</td>
								</tr>
								


							</tbody>
						</table>