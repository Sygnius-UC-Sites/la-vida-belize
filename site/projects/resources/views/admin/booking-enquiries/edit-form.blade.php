<div class="row">
    <div class="col-md-12">
        @if(!empty($data->rental_agreement_pdf_path))
            <div class="card mb-3" style="border-left:4px solid #28a745;">
                <div class="card-header" style="background:#f0fff4;">
                    <h5 class="mb-0">Signed Rental Agreement</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm" style="margin-bottom:12px;">
                        <tr><th style="width:200px;">Status</th><td><span class="badge bg-success">Signed</span></td></tr>
                        <tr><th>Signed at (UTC)</th><td>{{ $data->rental_agreement_signed_at }}</td></tr>
                        <tr><th>Signer email</th><td>{{ $data->email }}</td></tr>
                        <tr><th>IP address</th><td><code>{{ $data->rental_agreement_signed_ip }}</code></td></tr>
                        <tr><th>User agent</th><td><small class="text-muted">{{ $data->rental_agreement_signed_user_agent }}</small></td></tr>
                        @if($data->rental_aggrement_signature)
                            <tr><th>Signature</th><td><img src="{{ asset($data->rental_aggrement_signature) }}" style="max-width:240px; max-height:80px; border:1px solid #ddd; padding:4px; background:#fff;" alt="Signature"></td></tr>
                        @endif
                    </table>
                    <a href="{{ route('admin.booking.agreement-download', $data->id) }}" class="btn btn-primary">
                        Download signed PDF
                    </a>
                </div>
            </div>
        @elseif(!empty($data->rental_aggrement_status) && $data->rental_aggrement_status == 'true')
            <div class="alert alert-warning">
                <strong>Legacy signing (pre-Phase-2A)</strong> — no per-booking PDF artifact exists for this booking.
                Signature: {{ $data->rental_aggrement_signature }} · Date: {{ $data->rentar_agreement_date }} · IP: {{ $data->rentar_agreement_ip }}
            </div>
        @else
            <div class="alert alert-secondary">
                Rental agreement not signed.
            </div>
        @endif
    </div>

    <div class="col-md-12">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Stay Communications</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th style="width:200px;">Welcome email</th>
                        <td>
                            @if(!empty($data->welcome_email_sent_at))
                                <span class="badge bg-success">Sent</span> &mdash; {{ $data->welcome_email_sent_at }} UTC
                            @elseif($data->welcome_email == 'true')
                                <span class="badge bg-secondary">Sent (legacy — timestamp unavailable)</span>
                            @else
                                <span class="badge bg-warning text-dark">Pending</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Check-in email</th>
                        <td>
                            @if(!empty($data->checkin_email_sent_at))
                                <span class="badge bg-success">Sent</span> &mdash; {{ $data->checkin_email_sent_at }} UTC
                            @elseif($data->checkin_email == 'true')
                                <span class="badge bg-secondary">Sent (legacy — timestamp unavailable)</span>
                            @else
                                <span class="badge bg-warning text-dark">Pending</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Check-out email</th>
                        <td>
                            @if(!empty($data->checkout_email_sent_at))
                                <span class="badge bg-success">Sent</span> &mdash; {{ $data->checkout_email_sent_at }} UTC
                            @elseif($data->checkout_email == 'true')
                                <span class="badge bg-secondary">Sent (legacy — timestamp unavailable)</span>
                            @else
                                <span class="badge bg-warning text-dark">Pending</span>
                            @endif
                        </td>
                    </tr>
                </table>
                <small class="text-muted">These guest emails fire on schedule per the per-property and global timing settings. See <a href="{{ route('admin.stay-communications.edit') }}">Stay Communications settings</a>.</small>
            </div>
        </div>
    </div>

    <div class="col-md-12">
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Guest Review</h5></div>
            <div class="card-body">
                @php $review = \App\Models\Testimonial::where('booking_id', $data->id)->first(); @endphp
                @if(!$review)
                    @if(!empty($data->review_email_sent_at))
                        <p>Review request email sent {{ $data->review_email_sent_at }} UTC. Token row not found (manually deleted?).</p>
                    @else
                        <p><span class="badge bg-warning text-dark">Pending</span> &mdash; review request not sent yet.</p>
                    @endif
                @elseif($review->status === 'pending-link-sent')
                    <p><span class="badge bg-info">Link sent</span> &mdash; request emailed; guest has not submitted yet.</p>
                    @if(!empty($data->review_email_sent_at))
                        <p><small class="text-muted">Sent at: {{ $data->review_email_sent_at }} UTC</small></p>
                    @endif
                @elseif($review->status === 'false')
                    <p><span class="badge bg-warning text-dark">Awaiting moderation</span></p>
                    <p><strong>Rating:</strong> <span style="color:#f5c518;">{{ str_repeat('★', (int)$review->rating) }}{{ str_repeat('☆', 5 - (int)$review->rating) }}</span></p>
                    <p><strong>Submitted:</strong> {{ $review->submitted_at }} UTC from <code>{{ $review->submitted_ip }}</code></p>
                    <p><strong>Message:</strong></p>
                    <blockquote>{{ $review->message }}</blockquote>
                    <a href="{{ url('admin/testimonials/' . $review->id . '/edit') }}" class="btn btn-primary btn-sm">Moderate in Testimonials</a>
                @elseif($review->status === 'true')
                    <p><span class="badge bg-success">Approved</span> &mdash; visible on the property page</p>
                    <p><strong>Rating:</strong> <span style="color:#f5c518;">{{ str_repeat('★', (int)$review->rating) }}{{ str_repeat('☆', 5 - (int)$review->rating) }}</span></p>
                    @if($review->is_home == 'true')
                        <p><span class="badge bg-info">Featured on homepage</span></p>
                    @endif
                @elseif($review->status === 'rejected')
                    <p><span class="badge bg-danger">Rejected</span></p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("Booking Type") !!}
            {!! Form::select("booking_type_admin",["invoice"=>"invoice","manual"=>"manual"],null,["class"=>"form-control","required","placeholder"=>"Choose Booking Type","id"=>"booking-selector"]) !!}
            <span class="text-danger">{{ $errors->first("booking_type_admin")}}</span>
        </div>
    </div>
    <div class="col-md-12 d-none">
        <div class="form-group">
            {!! Form::label("property") !!}
            {!! Form::select("property_id",ModelHelper::getProperptySelectList(),null,["class"=>"form-control","required","placeholder"=>"Choose Property","id"=>"property-selector"]) !!}
            <span class="text-danger">{{ $errors->first("property_id")}}</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("checkin") !!}
            @php
                $enquiryBlocked = $data->property_id
                    ? (new \App\Helper\LiveCart())->iCalDataCheckInCheckOutCheckinCheckout($data->property_id)
                    : ['blocked' => [], 'checkin' => [], 'checkout' => []];
            @endphp
            {!! Form::text("checkin", null, [
                "required",
                "readonly",
                "autocomplete" => "off",
                "id" => "txtFrom",
                "placeholder" => "Check in",
                "class" => "form-control",
                "data-vr-datepicker" => "range-blocked",
                "data-end-input" => "#txtTo",
                "data-property-source" => "#property-selector",
                "data-blocked" => json_encode($enquiryBlocked['blocked'] ?? []),
                "data-no-checkin" => json_encode($enquiryBlocked['checkin'] ?? []),
                "data-no-checkout" => json_encode($enquiryBlocked['checkout'] ?? []),
            ]) !!}
            <span class="text-danger">{{ $errors->first("checkin")}}</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("checkout") !!}
            {!! Form::text("checkout", null, [
                "required",
                "readonly",
                "autocomplete" => "off",
                "id" => "txtTo",
                "placeholder" => "Check Out",
                "class" => "form-control lst",
            ]) !!}
            <span class="text-danger">{{ $errors->first("checkout")}}</span>
        </div>
    </div>
</div>
<div class="row   {{ $data->booking_type_admin=='invoice'?'':'d-none' }}"  id="gaurav-data-new-logic" >
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label("adults") !!}
            {!! Form::selectRange("adults",0,100,null,["class"=>"form-control","required","id"=>"adult_data"]) !!}
            <span class="text-danger">{{ $errors->first("adults")}}</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label("child") !!}
            {!! Form::selectRange("child",0,100,null,["class"=>"form-control","id"=>"child_data"]) !!}
            <span class="text-danger">{{ $errors->first("child")}}</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label("pets") !!}
            {!! Form::selectRange("total_pets",0,5,null,["class"=>"form-control","id"=>"pet_data"]) !!}
            <span class="text-danger">{{ $errors->first("pets")}}</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label("extra_discount") !!}
            {!! Form::number("extra_discount",null,["class"=>"form-control","id"=>"extra-discount"]) !!}
            <span class="text-danger">{{ $errors->first("extra_discount")}}</span>
        </div>
    </div>
     <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("Heating Pool") !!}
            {!! Form::select("heating_pool_fee_data_guarav",["Yes"=>"Yes","No"=>"No"],null,["class"=>"form-control","id"=>"heating_pool_fee_data_guarav"]) !!}
            <span class="text-danger">{{ $errors->first("heating_pool_fee_data_guarav")}}</span>
        </div>
    </div>
</div>
<div class="row" >
    <div class="col-md-12">
        <a href="javascript:;" class="add-space-data btn btn-info btn-xs"><i class="fa fa-plus"></i> Add Additional Price</a>
             <hr>
    </div>
</div>
<div class="row gaurav-delete-property">
     <div class="col-md-2">
        <strong>Action</strong>
    </div>
    <div class="col-md-7">
        <strong>Name</strong>
    </div>
    

    <div class="col-md-3">
        <strong>Amount</strong>
    </div>
  
    
    <div class="col-md-12">
        <br>
    </div>
</div>
<div id="space-area-section">
    @isset($data)
        @if($data->custom_before_total_fees)
            @php
                $products=json_decode($data->custom_before_total_fees,true);
            @endphp
            @foreach($products as $p)
                @isset($p['product_amount'])
                        <div class="row gaurav-delete-property-space">
                            <div class="col-md-1">
                                <a href="javascript:;" class="delete-space-data btn btn-danger btn-xs" ><i class="fa fa-times"></i> </a>
                            </div>
                            <div class="col-md-8">
                                {!! Form::text("product_name[]",$p['product_name'],["required","class"=>"form-control","placeholder"=>"Name"]) !!}
                            </div>
                            <div class="col-md-3">
                                {!! Form::text("product_amount[]",$p['product_amount'],["class"=>"form-control product_amount","placeholder"=>"Amount","required"]) !!}
                            </div>
                            <div class="col-md-12">
                                <br>
                            </div>
                        </div>
                @endisset
            @endforeach
        @endif
    @endisset
</div>


<div class="row" id="pricedata-gaurav">
    @if($data->booking_type_admin=='invoice')
      @php
        $start_date=$data->checkin;
        $end_date=$data->checkout;
        $adults=$data->adults;
        $child=$data->child;
        $total_guests=$adults+$child;
        $gross_amount=$data->gross_amount;
        $day=$data->total_night;
        $sub_total=$gross_amount;
        $total_amount=$gross_amount;
        $before_total_fees=[];
        $after_total_fees=[];
        $total_pets=$data->pet_fee_data_guarav;
        $pet_fee=0;
        $guest_fee=0;
        $rest_guests=0;
        $single_guest_fee=0;
        $extra_discount=0;
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
           
@if($data->custom_before_total_fees)
            @foreach(json_decode($data->custom_before_total_fees) ?: [] as $c)
                <tr>
                    <td align="left" colspan="4" style="padding: 10px;" valign="top"><strong>{{$c->product_name}} :</strong></td>
                    <td align="right" style="padding: 10px;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($c->product_amount,2) }}</td>
                </tr>
            @endforeach
@endif
         	@if($data->rest_guests)
    		    @if($data->rest_guests>0)
    		        @if($data->guest_fee)
    		            @if($data->guest_fee>0)
    		            <tr>
    						<td align="left" colspan="4" style="padding: 10px;" valign="top"><strong> Additional Guest Fee <br> <span style="font-size:13px;">({{$data->total_night}} nights * {!! $setting_data['payment_currency'] !!}{{$data->single_guest_fee}} * {{$data->rest_guests}} Guests)</span></strong></td>
    						<td align="right" style="padding: 10px; " valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($data->guest_fee,2) }}</td>
    					</tr>
    		            @endif
    		        @endif
    		    @endif
    		@endif
         	@if($data->extra_amount)
    		    @if($data->extra_amount>0)
    		     
    		            <tr>
    						<td align="left" colspan="4" style="padding: 10px;" valign="top"><strong> Extra Amount </strong></td>
    						<td align="right" style="padding: 10px; " valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($data->extra_amount,2) }}</td>
    					</tr>
    		        
    		    @endif
    		@endif
            
            @if(null /* Phase 3 */)
              @php $ij=0; @endphp
                @foreach(json_decode(null /* Phase 3 */) ?: [] as $c)
                    @if($c->status=="true")
                    @if($ij==0)
                         <tr><th colspan="5">RV Hookup </th></tr>
                    @endif  
                    <tr>
                        <th colspan="4">
                            <input type="checkbox" checked name="rvhookup_rate_id[id][{{$c->id}}]" value="{{ $c->id }}" class="common-field-show-rate rvhookup-rate" data-target="rvhookup_rate_id{{$c->id}}field" >
                            <input type="text" value="{{ $c->value }}" name="rvhookup_rate_id[field][{{$c->id}}]" id="rvhookup_rate_id{{$c->id}}field" class="  rate-calculateion-data"> {{$c->rvhookup_name}}<sub>{{$c->rvhookup_helping_text}}</sub>
                        </th>
                        <td align="right">
                            {!! $setting_data['payment_currency'] !!}{{number_format(($c->amount ?? $c->value ?? 0),2) }}
                        </td>
                    </tr>
                    @php $ij++;@endphp
                    @endif
                @endforeach
            @endif  
            
            @if(null /* Phase 3 */)
              @php $ij=0; @endphp
                @foreach(json_decode(null /* Phase 3 */) ?: [] as $c)
                    @if($c->status=="true")
                    @if($ij==0)
                         <tr><th colspan="5">No. of visitors </th></tr>
                    @endif  
                    <tr>
                        <th colspan="4">
                            <input type="checkbox" checked name="visitor_rate_id[id][{{$c->id}}]" value="{{ $c->id }}" class="common-field-show-rate visitor-rate" data-target="visitor_rate_id{{$c->id}}field" >
                            <input type="text" value="{{ $c->value }}" name="visitor_rate_id[field][{{$c->id}}]" id="visitor_rate_id{{$c->id}}field" class="  rate-calculateion-data"> {{$c->visitor_name}}<sub>{{$c->visitor_helping_text}}</sub>
                        </th>
                        <td align="right">
                            {!! $setting_data['payment_currency'] !!}{{number_format(($c->amount ?? $c->value ?? 0),2) }}
                        </td>
                    </tr>
                    @php $ij++;@endphp
                    @endif
                @endforeach
            @endif  
            
            @if(null /* Phase 3 */)
              @php $ij=0; @endphp
                @foreach(json_decode(null /* Phase 3 */) ?: [] as $c)
                    @if($c->status=="true")
                    @if($ij==0)
                         <tr><th colspan="5">Hot tub </th></tr>
                    @endif  
                    <tr>
                        <th colspan="4">
                            <input type="checkbox" checked name="hottub_rate_id[id][{{$c->id}}]" value="{{ $c->id }}" class="common-field-show-rate hottub-rate" data-target="hottub_rate_id{{$c->id}}field" >
                            <input type="text" value="{{ $c->value }}" name="hottub_rate_id[field][{{$c->id}}]" id="hottub_rate_id{{$c->id}}field" class="  rate-calculateion-data"> {{$c->hottub_name}}<sub>{{$c->hottub_helping_text}}</sub>
                        </th>
                        <td align="right">
                            {!! $setting_data['payment_currency'] !!}{{number_format(($c->amount ?? $c->value ?? 0),2) }}
                        </td>
                    </tr>
                    @php $ij++;@endphp
                    @endif
                @endforeach
            @endif 
            
            @if(null /* Phase 3 */)
              @php $ij=0; @endphp
                @foreach(json_decode(null /* Phase 3 */) ?: [] as $c)
                    @if($c->status=="true")
                    @if($ij==0)
                         <tr><th colspan="5">Firewood </th></tr>
                    @endif  
                    <tr>
                        <th colspan="4">
                            <input type="checkbox" checked name="firewood_rate_id[id][{{$c->id}}]" value="{{ $c->id }}" class="common-field-show-rate firewood-rate" data-target="firewood_rate_id{{$c->id}}field" >
                            <input type="text" value="{{ $c->value }}" name="firewood_rate_id[field][{{$c->id}}]" id="firewood_rate_id{{$c->id}}field" class="  rate-calculateion-data"> {{$c->firewood_name}}<sub>{{$c->firewood_helping_text}}</sub>
                        </th>
                        <td align="right">
                            {!! $setting_data['payment_currency'] !!}{{number_format(($c->amount ?? $c->value ?? 0),2) }}
                        </td>
                    </tr>
                    @php $ij++;@endphp
                    @endif
                @endforeach
            @endif  			    
  
        	@if($data->pet_fee_type=="taxable")
    			@if($data->total_pets)
    			    @if($data->total_pets>0)
    			        @if($data->pet_fee)
    			            @if($data->pet_fee>0)
    			            <tr>
    							<td align="left" colspan="4" style="padding: 10px;" valign="top"><strong>Pet Fee :</strong></td>
    							<td align="right" style="padding: 10px; " valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($data->pet_fee,2) }}</td>
    						</tr>
    			            @endif
    			        @endif
    			    @endif
    			@endif
			@endif

        	@if($data->heating_pool_fee_type=="taxable")
		        @if($data->heating_pool_fee)
		            @if($data->heating_pool_fee>0)
		            <tr>
						<td align="left" colspan="4" style="padding: 10px;" valign="top"><strong>Heating the Swimming Pool fee  :</strong></td>
						<td align="right" style="padding: 10px; " valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($data->heating_pool_fee,2) }}</td>
					</tr>
		            @endif
		        @endif
			@endif

            @foreach(json_decode($data->before_total_fees) ?: [] as $c)
                <tr>
                    <td align="left" colspan="4" style="padding: 10px;" valign="top"><strong>{{$c->name}} :</strong></td>
                    <td align="right" style="padding: 10px;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($c->amount ?? $c->value ?? 0,2) }}</td>
                </tr>
            @endforeach
          
            @if($data->tax)
                <tr>
                    <td align="left" colspan="4" style="padding: 10px;" valign="top"><strong>Tax ({{ $data->define_tax ?? '-'}}%) :</strong></td>
                    <td align="right" style="padding: 10px;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($data->tax,2) }}</td>
                </tr>
            @endif
                   
            <tr>
                <td align="left" colspan="4" style="padding: 10px;" valign="top"><strong>Sub Total :</strong></td>
                <td align="right" style="padding: 10px;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($data->sub_amount,2) }}</td>
            </tr>

        	@if($data->pet_fee_type!="taxable")
    			@if($data->total_pets)
    			    @if($data->total_pets>0)
    			        @if($data->pet_fee)
    			            @if($data->pet_fee>0)
    			            <tr>
    							<td align="left" colspan="4" style="padding: 10px;" valign="top"><strong>Pet Fee :</strong></td>
    							<td align="right" style="padding: 10px; " valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($data->pet_fee,2) }}</td>
    						</tr>
    			            @endif
    			        @endif
    			    @endif
    			@endif
			@endif
           
           				    
  
        	@if($data->heating_pool_fee_type!="taxable")
		        @if($data->heating_pool_fee)
		            @if($data->heating_pool_fee>0)
		            <tr>
						<td align="left" colspan="4" style="padding: 10px;" valign="top"><strong>Heating the Swimming Pool fee  :</strong></td>
						<td align="right" style="padding: 10px; " valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($data->heating_pool_fee,2) }}</td>
					</tr>
		            @endif
		        @endif
			@endif
           
            @foreach(json_decode($data->after_total_fees) ?: [] as $c)
                <tr>
                    <td align="left" colspan="4" style="padding: 10px;" valign="top"><strong>{{$c->name}} :</strong></td>
                    <td align="right" style="padding: 10px;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($c->amount ?? $c->value ?? 0,2) }}</td>
                </tr>
            @endforeach
            
            <tr>
                <td align="left" colspan="4" style="padding: 10px;" valign="top"><strong>Total :</strong></td>
                <td align="right" style="padding: 10px;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($data->total_amount,2) }}</td>
            </tr>
                                    
            @php $gaurav_discount=0;@endphp
		    @if($data->discount)
                @if($data->discount!="")
                    @if($data->discount!=0)
                           @php $gaurav_discount=1;@endphp 
                    <tr>
                        <td align="left" colspan="4" style="padding: 10px; " valign="top"><strong>Discount ({{ $data->discount_coupon }}):</strong></td>
                        <td align="right" style="padding: 10px; " valign="top">- {!! $setting_data['payment_currency']  !!}{{number_format($data->discount,2) }}</td>
                    </tr>
                  
                    @endif
                @endif
            @endif
            
		    @if($data->extra_discount)
                @if($data->extra_discount!="")
                    @if($data->extra_discount>0)
                           @php $gaurav_discount=1;@endphp 
                    <tr>
                        <td align="left" colspan="4" style="padding: 10px; " valign="top"><strong>Extra Discount :</strong></td>
                        <td align="right" style="padding: 10px; " valign="top">- {!! $setting_data['payment_currency']  !!}{{number_format($data->extra_discount,2) }}</td>
                    </tr>
                  
                    @endif
                @endif
            @endif
            
			@if($gaurav_discount==1)
			    <tr>
                    <td align="left" colspan="4" style="padding: 10px; " valign="top"><strong>Total Amount after Discount:</strong></td>
                    <td align="right"  style="padding: 10px; " valign="top">{!! $setting_data['payment_currency']  !!}{{number_format($data->after_discount_total,2) }}</td>
                </tr>
			@endif
                                    
            @if($data->amount_data)
                @php
                    // amount_data carries two different shapes depending on which flow created the row:
                    //   • Legacy admin/invoice flow → flat list of [{message,amount,status?}] line-items.
                    //   • New QuoteCalculator front-end flow → associative quote object with a nested
                    //     "breakdown" array of [{label,amount,...}] rows.
                    // We normalize to a single $rows = [['message'=>..,'amount'=>..,'status'=>?]] list so
                    // the blade is shape-agnostic and the page never blows up on records of either era.
                    $amount_data = json_decode($data->amount_data, true);
                    $rows = [];
                    if (is_array($amount_data)) {
                        if (isset($amount_data['breakdown']) && is_array($amount_data['breakdown'])) {
                            foreach ($amount_data['breakdown'] as $b) {
                                if (!is_array($b)) continue;
                                $rows[] = [
                                    'message' => $b['label'] ?? $b['message'] ?? '',
                                    'amount'  => (float) ($b['amount'] ?? 0),
                                ];
                            }
                        } else {
                            foreach ($amount_data as $c) {
                                if (!is_array($c)) continue;
                                $rows[] = [
                                    'message' => $c['message'] ?? $c['label'] ?? '',
                                    'amount'  => (float) ($c['amount'] ?? 0),
                                    'status'  => $c['status'] ?? null,
                                ];
                            }
                        }
                    }
                @endphp
                @foreach($rows as $c)
                    <tr>
                        <td align="left" colspan="4" style="padding: 10px;" valign="top"><strong>{{ $c['message'] }} @isset($c['status'])<span style="color:green;">(Paid)</span>@endisset:</strong></td>
                        <td align="right" style="padding: 10px;" valign="top">{!! $setting_data['payment_currency'] ?? '$' !!}{{ number_format($c['amount'], 2) }}</td>
                    </tr>
                @endforeach
            @endif

       </table>
            </div>

       
        <input type="hidden" name="discount" value="{{ $data->discount }}" id="coupon_discount"  />
        <input type="hidden" name="discount_coupon" value="{{ $data->discount_coupon }}" id="coupon_discount_code" />
        <input type="hidden" name="after_discount_total" value="{{ $data->remaining_total_amount }}" />
      

 
        <input type="hidden" name="pet_fee" value="{{ $data->pet_fee }}">
        
        <input type="hidden" name="guest_fee" value="{{ $data->guest_fee }}">
        <input type="hidden" name="rest_guests" value="{{ $data->rest_guests }}">
        <input type="hidden" name="single_guest_fee" value="{{ $data->single_guest_fee }}">
        
        <input type="hidden" name="total_payment" value="{{ $data->total_payment }}">
        <input type="hidden" name="amount_data" value="{{ json_encode($data->amount_data) }}">
        
        <input type="hidden" name="property_id" value="{{ $data->property_id }}">
       
        

        
        {{-- Phase 13K — total_night still hidden (computed from dates), but the
             dollar amounts are now editable above via #be-amount-editor.
             Hidden fields for fees JSON have been removed — Phase 13J editors
             submit the structured arrays which the controller re-encodes. --}}
        <input type="hidden" name="total_night" value="{{ $data->total_night }}" >
        <input type="hidden" name="sub_amount" value="{{ $data->sub_amount }}" >

        <input type="hidden" name="request_id" value="{{ $data->request_id }}" >
        
        <input type="hidden" name="tax" value="{{ $data->tax }}" >
        <input type="hidden" name="define_tax" value="{{ $data->define_tax }}" >
        <input type="hidden" name="pet_fee_type" value="{{ $data->pet_fee_type }}" />
        <input type="hidden" name="heating_pool_fee_type" value="{{ $data->heating_pool_fee_type }}" />
<input type="hidden" name="heating_pool_fee" value="{{ $data->heating_pool_fee }}" />

<input type="hidden" name="extra_amount" value="{{ $data->extra_amount }}" />
        
        @endif
</div>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label("name") !!}
            {!! Form::text("name",null,["class"=>"form-control","required"]) !!}
            <span class="text-danger">{{ $errors->first("name")}}</span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label("mobile") !!}
            {!! Form::text("mobile",null,["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("mobile")}}</span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label("email") !!}
            {!! Form::email("email",null,["class"=>"form-control","required"]) !!}
            <span class="text-danger">{{ $errors->first("email")}}</span>
        </div>
    </div>
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("message") !!}
            {!! Form::textarea("message",null,["class"=>"form-control","rows"=>"2"]) !!}
            <span class="text-danger">{{ $errors->first("message")}}</span>
        </div>
    </div>
</div>

{{-- Phase 13J — structured editors for the booking's before_total_fees + after_total_fees JSON columns.
     Lets admin add / edit / delete each fee line that the customer email's invoice table will iterate. --}}
<style>
.fee-editor { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; margin-top:14px; }
.fee-editor h5 { margin:0 0 4px 0; font-size:13px; font-weight:600; color:#0f172a; }
.fee-editor .fee-hint { color:#5b6473; font-size:12px; margin-bottom:10px; }
.fee-editor .fee-row { display:flex; gap:8px; align-items:center; margin-bottom:6px; }
.fee-editor .fee-row input { flex:1; }
.fee-editor .fee-row input.fee-amount { max-width:140px; }
.fee-editor .fee-add-btn { background:#1e293b; color:#fff; border:0; border-radius:6px; padding:5px 12px; font-size:12px; font-weight:600; cursor:pointer; }
.fee-editor .fee-del-btn { background:#fee2e2; color:#b42318; border:1px solid #fca5a5; border-radius:6px; padding:5px 8px; font-size:11px; cursor:pointer; }
</style>

@php
    // Phase 13J — both decode calls must use the same fallback. When the column is
    // NULL the second json_decode(null) returns null and breaks @foreach below.
    $beforeFeesRaw = $data->before_total_fees ?? '[]';
    $afterFeesRaw  = $data->after_total_fees  ?? '[]';
    $beforeFees = is_array($d = json_decode($beforeFeesRaw, true)) ? $d : [];
    $afterFees  = is_array($d = json_decode($afterFeesRaw, true))  ? $d : [];
@endphp

<div class="fee-editor" id="be-before-fees">
    <h5>📋 Pre-tax fees (cleaning, addons, etc.)</h5>
    <p class="fee-hint">Lines listed here render in the customer email invoice <strong>before</strong> tax. Edit, delete, or add. Empty rows are dropped on save.</p>
    <div class="fee-rows" id="before-fee-rows">
        @foreach($beforeFees as $f)
            @php $name = $f['name'] ?? ($f['product_name'] ?? ''); $amt = $f['amount'] ?? ($f['product_amount'] ?? 0); @endphp
            <div class="fee-row">
                <input type="text" name="before_fees_name[]"   value="{{ $name }}" placeholder="Fee name (e.g. Cleaning fee)" class="form-control">
                <input type="number" step="0.01" name="before_fees_amount[]" value="{{ $amt }}" placeholder="0.00" class="form-control fee-amount">
                <button type="button" class="fee-del-btn" onclick="this.closest('.fee-row').remove()"><i class="fa fa-trash"></i></button>
            </div>
        @endforeach
    </div>
    <button type="button" class="fee-add-btn" id="add-before-fee">+ Add pre-tax line</button>
</div>

<div class="fee-editor" id="be-after-fees">
    <h5>💳 Post-tax fees (credit-card processing, etc.)</h5>
    <p class="fee-hint">Lines listed here render <strong>after</strong> tax. Use for processing fees or anything you don't want taxed.</p>
    <div class="fee-rows" id="after-fee-rows">
        @foreach($afterFees as $f)
            @php $name = $f['name'] ?? ($f['product_name'] ?? ''); $amt = $f['amount'] ?? ($f['product_amount'] ?? 0); @endphp
            <div class="fee-row">
                <input type="text" name="after_fees_name[]"   value="{{ $name }}" placeholder="Fee name (e.g. Credit card processing)" class="form-control">
                <input type="number" step="0.01" name="after_fees_amount[]" value="{{ $amt }}" placeholder="0.00" class="form-control fee-amount">
                <button type="button" class="fee-del-btn" onclick="this.closest('.fee-row').remove()"><i class="fa fa-trash"></i></button>
            </div>
        @endforeach
    </div>
    <button type="button" class="fee-add-btn" id="add-after-fee">+ Add post-tax line</button>
</div>

<script>
(function () {
    function addRow(targetId, namePrefix) {
        var wrap = document.getElementById(targetId);
        if (!wrap) return;
        var row = document.createElement('div');
        row.className = 'fee-row';
        row.innerHTML = '<input type="text" name="' + namePrefix + '_name[]" placeholder="Fee name" class="form-control">'
                      + '<input type="number" step="0.01" name="' + namePrefix + '_amount[]" placeholder="0.00" class="form-control fee-amount">'
                      + '<button type="button" class="fee-del-btn" onclick="this.closest(\'.fee-row\').remove()"><i class="fa fa-trash"></i></button>';
        wrap.appendChild(row);
    }
    var $b = document.getElementById('add-before-fee'); if ($b) $b.addEventListener('click', function () { addRow('before-fee-rows', 'before_fees'); });
    var $a = document.getElementById('add-after-fee');  if ($a) $a.addEventListener('click', function () { addRow('after-fee-rows',  'after_fees'); });
})();
</script>

{{-- Phase 13K — Editable total + gross amounts with a "Recalculate from lines" helper. --}}
@php
    $isPaid = in_array(($data->payment_status ?? null), ['paid','partially'], true);
@endphp
@if($isPaid)
<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:12px 14px;margin-top:14px;color:#78350f;">
    <strong>⚠️ This booking is already <span style="text-transform:uppercase;">{{ $data->payment_status }}</span>.</strong>
    Edits here update what the customer sees on the next email re-send (invoice table, totals) but do <strong>not</strong> trigger refunds or additional charges through your payment processor. Process any delta manually in {{ ucfirst(\ModelHelper::getDataFromSetting('which_payment_gateway') ?: 'your payment gateway') }}.
</div>
@endif

<div class="fee-editor" id="be-amount-editor">
    <h5>💰 Booking amounts</h5>
    <p class="fee-hint">
        @if($isPaid)
            Original captured total stays unless you change it below. <strong>Recalculate from lines</strong> sums every fee/discount/tax line above and updates the total field — useful as a sanity check.
        @else
            <strong>Recalculate from lines</strong> sums every fee/discount/tax line above and fills the total. The controller also auto-recomputes on save when the booking is unpaid.
        @endif
    </p>
    <div class="row">
        <div class="col-md-4">
            <label for="be-gross">Rental subtotal (gross_amount)</label>
            <input type="number" step="0.01" id="be-gross" name="gross_amount" value="{{ $data->gross_amount }}" class="form-control">
            <small class="text-muted">Nights × nightly rate. Independent of fees / tax / discount.</small>
        </div>
        <div class="col-md-4">
            <label for="be-total">Total charged (total_amount)</label>
            <input type="number" step="0.01" id="be-total" name="total_amount" value="{{ $data->total_amount }}" class="form-control">
            <small class="text-muted">What the guest's card was actually charged. Edit only if you have a reason to override.</small>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button type="button" id="be-recalc-btn" class="btn btn-info btn-block">
                <i class="fa fa-calculator"></i> Recalculate from lines
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    document.getElementById('be-recalc-btn').addEventListener('click', function () {
        var sum = 0;
        // Gross (rental)
        var gross = parseFloat(document.getElementById('be-gross').value || 0);
        sum += gross;
        // Custom admin-added pre-tax (product_name[] / product_amount[])
        document.querySelectorAll('input[name="product_amount[]"]').forEach(function (el) { sum += parseFloat(el.value || 0); });
        // Pre-tax fees (Phase 13J editor)
        document.querySelectorAll('input[name="before_fees_amount[]"]').forEach(function (el) { sum += parseFloat(el.value || 0); });
        // Post-tax fees (Phase 13J editor)
        document.querySelectorAll('input[name="after_fees_amount[]"]').forEach(function (el) { sum += parseFloat(el.value || 0); });
        // Pet fee + extra-guest fee + tax: read from existing hidden / read-only inputs if present.
        var pet = parseFloat((document.querySelector('[name="pet_fee"]') || {}).value || {{ (float)($data->pet_fee ?? 0) }});
        var guest = {{ (float)($data->guest_fee ?? 0) }};
        var tax = {{ (float)($data->tax ?? 0) }};
        var discount = {{ (float)($data->discount ?? 0) }};
        sum += pet + guest + tax;
        sum -= discount;
        document.getElementById('be-total').value = sum.toFixed(2);
    });
})();
</script>
