@extends('admin.layouts')
@section('title', 'Admin')
@php 
    $name="Booking Enquiries";$route="booking-enquiries";
@endphp             
@section('content_header')
    <h1 class="m-0 text-dark">{{$name}} Management</h1>
@stop
@section('content')
    @parent
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-12">
          @php 
            $addbar=["name"=>$name,"add-data"=>true,"add-name"=>"Add ". Str::singular($name),"add-anchor"=>route($route.'.create')
            ,"review-email"=>true
            ,"reminder-email"=>true
            ,"welcome-email"=>true,"set-pricelab"=>true
            
            ];
          @endphp
          @include("admin.common.add-bar")
          </div>
        
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">

      <div class="row">
        <div class="col-12">
         
          <div class="card  ">
            <div class="card-header">
              <h3 class="card-title">{{ $name }} Listing</h3>
               <div class="card-tools">
                  <button type="button" class="btn btn-tool" data-card-widget="maximize"><i class="fas fa-expand"></i>
                  </button>
              </div>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <table id="data-table-gaurav" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th> #</th>
                        <th>Checkin</th>
                        <th>Checkout</th>
                        <th>Booking-id</th>
                        <th>Property</th>
                        <th>Customer</th>
                        <th>Guests</th>
                        <th>Nights</th>
                        <th>Amount</th>

                        <th>Request of Date</th>
                   
                        <th>Booking Status</th>
                        <th>Status</th>
                        
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                        @php 
                            $sno=1; 
                            $payment_currency=ModelHelper::getDataFromSetting('payment_currency');
                        @endphp
                    @foreach($data as $client)
                        <tr>
                           
                            <td>{{ $sno++ }}</td>
                            <td>{{ date("F jS, Y",strtotime($client->checkin)) }}</td>
                            <td>{{ date("F jS, Y",strtotime($client->checkout)) }}</td>
                            <td>{{ $client->id }}</td>
                            <td>{{ App\Models\Property::find($client->property_id)->name ?? $client->property_id }}</td>
                           
                           
                           
                            <td>
                                {{ $client->name }}
                                <br>
                                {{ $client->email }}
                           
                            </td>
                            <td>{{ $client->total_guests }}</td>
                            <td>{{ $client->total_night }}</td>
                            <td>@if($client->booking_type_admin=="invoice") {!! $payment_currency !!}{{ $client->total_amount }} @endif</td>
                            <td>{{ date("F jS, Y",strtotime($client->created_at)) }}</td>
                         
                            <td>{!! Helper::getBookingStatus($client->booking_status,$client->id) !!}</td>
                            <td>    
                                @if($client->booking_type_admin=="invoice")
                                <div class="btn-group btn-group-sm"><a href="#" class="btn btn-info">Status</a>
                                  <button type="button" class="btn btn-info dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <span class="sr-only">Toggle Dropdown</span>
                                  </button>
                                  <div class="dropdown-menu dropdown-menu-right">
                                    <a class="dropdown-item {{ $client->rental_aggrement_status=='true'?'text-success':'text-danger' }}" href="javascript:;" >Rental  {!! Helper::checkStatus($client->rental_aggrement_status) !!}</a>
                                    <a class="dropdown-item " href="javascript:;" >Payment  ({!! $client->payment_status !!})</a>
                                    <a class="dropdown-item {{ $client->welcome_email=='true'?'text-success':'text-danger' }}" href="javascript:;" >Welcome  {!! Helper::checkStatus($client->welcome_email) !!}</a>
                                    <a class="dropdown-item {{ $client->review_email=='true'?'text-success':'text-danger' }}" href="javascript:;" >Review  {!! Helper::checkStatus($client->review_email) !!}</a>
                                    <a class="dropdown-item {{ $client->reminder_email=='true'?'text-success':'text-danger' }}" href="javascript:;" >Reminder  {!! Helper::checkStatus($client->reminder_email) !!}</a>
                                    <a class="dropdown-item {{ $client->checkin_email=='true'?'text-success':'text-danger' }}" href="javascript:;" >Checkin  {!! Helper::checkStatus($client->checkin_email) !!}</a>
                                    <a class="dropdown-item {{ $client->checkout_email=='true'?'text-success':'text-danger' }}" href="javascript:;" >Checkout  {!! Helper::checkStatus($client->checkout_email) !!}</a>
                                   
                                  </div>
                                </div>
                                
                                @else
                                
                                <a href="#" class="btn btn-info">Manual Booking</a>
                                @endif
                            </td>
                           
                       
                      
                           
                         
                            <td>
                                <a href="javascript:;" class="btn btn-outline-primary btn-sm raw-margin-right-8" data-toggle="modal" data-target="#myModal{{ $client->id }}">
                                    <i class="fa fa-eye"></i>
                                </a>
                                
                                   @if($client->booking_status!='cancelled')
                                @if($client->booking_status!='booked' )
                                <a href="{!! route($route.'.edit', [$client->id]) !!}" class="btn btn-outline-success btn-sm raw-margin-right-8 "><i
                                            class="fa fa-pencil-alt"></i> </a>
                                @elseif($client->booking_type_admin!="invoice")
                                  <a href="{!! route($route.'.edit', [$client->id]) !!}" class="btn btn-outline-success btn-sm raw-margin-right-8 "><i
                                            class="fa fa-pencil-alt"></i> </a>
                                            @endif
                                            
                                            @if($client->booking_status=='booked')
                                            <form method="post" action="{!! route($route.'.destroy', [$client->id]) !!}"
                                                  style="display: inline-block;">
                                                {!! csrf_field() !!}
                                                {!! method_field('DELETE') !!}
                                                <button type="submit" class="btn btn-outline-danger btn-sm raw-margin-right-8"
                                                        onclick="return confirm('Are you sure you want to cancel this {{ $name }}?')"> 
                                                        
                                                        Cancel Booking
                                                </button>
                                            </form>
                                            @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
           
              </table>
            </div>
            <!-- /.card-body -->
          </div>
          <!-- /.card -->
        </div>
        <!-- /.col -->
      </div>
      <!-- /.row -->
    </section>
    <!-- /.content -->
@stop
@section("js")
@parent
@php $data123=$data; @endphp
 @foreach($data123 as $client)
<!-- The Modal -->
<div class="modal" id="myModal{{ $client->id }}">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <!-- Modal Header -->
      <div class="modal-header">
        <h4 class="modal-title">Booking Detail</h4>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>

      <!-- Modal body -->
      <div class="modal-body">
        @php
            $data=$client->toArray();
            $property=App\Models\Property::find($client->property_id);
        @endphp
                <table class="table table-bordered" >
                            <tbody>
                                <tr>
                                    <th colspan="4" ><strong>Property Detail </strong></th>
                                </tr>
                                <tr>
                                    <th>Request ID</th>
                                    <td>{{ $data['request_id'] }}</td>
                               
                                    <th>Booking Date</th>
                                    <td>{{ date("F jS, Y",strtotime($data['created_at'])) }}</td>
                                </tr>
                                <tr>
                                    <th>Booking Status</th>
                                    <td>
                                        {!! Helper::getBookingStatus($client->booking_status,$client->id) !!}
                                    </td>
                                
                                    <td ><strong>Property Name :</strong></td>

                                    <td >{{$property->name ?? $client->property_id }}</td>
                                </tr>
@if($data['booking_type_admin']=="invoice")
                                <tr>
                                    <th colspan="3">Rental Aggrement</th>
                                    <th>{{ $data['rental_aggrement_status'] }}</th>
                                </tr>
                                @if($data['rental_aggrement_status']=="true")

                                <tr>
                                    <th>Sign</th>
                                    <td><img src="{{ asset($data['rental_aggrement_signature']) }}" style="width: 100px;" /></td>
                               
                                    <th>Image</th>
                                    <td><img src="{{ asset($data['rental_aggrement_images']) }}" style="width: 100px;" /></td>
                                </tr>
                                @endif

@endif

                             


                                <tr>
                                    <th colspan="4" ><strong>User Detail </strong></th>
                                </tr>
                                <tr>
                                    <td ><strong>Name :</strong></td>
                                    <td >{{$data['name']}}</td>
                               
                                    <td ><strong>Email :</strong></td>
                                    <td >{{$data['email']}}</td>
                                </tr>
                                <tr>
                                    <td ><strong>Mobile:</strong></td>
                                    <td >{{$data['mobile']}}</td>
                              
                                    <td ><strong>Message :</strong></td>
                                    <td >
                                        <pre>{{$data['message']}}</pre>
                                    </td>
                                </tr>
                                
                            </tbody>
                        </table>
                        <table class="table table-bordered" >
                            <tbody>
                                <tr>
                                    <th colspan="5" ><strong>Booking Detail </strong></th>
                                </tr>

                               

                                <tr>
                                    <th ><strong>Checkin :</strong></th>
                                    <th ><strong>Checkout :</strong></th>
                                    <th ><strong>Total Guest :</strong></th>
                                    <th ><strong>Total Night :</strong></th>
                                    <td ><strong>Gross Amount :</strong></td>
                                    
                                </tr>
                                <tr>
                                    <td >{{$data['checkin'] }}</td>
                                    <td >{{$data['checkout'] }}</td>
                                    <td >{{$data['total_guests'] }} ({{$data['adults']}} Adults, {{$data['child']}} Child)</td>
                                    <td >{{$data['total_night'] }}</td>
                                    <td >@if($data['booking_type_admin']=="invoice") {!! $payment_currency !!}{{number_format($data['gross_amount'],2) }} @endif</td>
                                </tr>
@if($data['booking_type_admin']=="invoice")                                	
								
								@if($data['rest_guests'])
								    @if($data['rest_guests']>0)
								        @if($data['guest_fee'])
								            @if($data['guest_fee']>0)
								            <tr>
            									<td colspan="4" ><strong> Additional Guest Fee <br> <span style="font-size:13px;">({{$data['total_night']}} nights * {!! $payment_currency !!}{{$data['single_guest_fee']}} * {{$data['rest_guests']}} Guests)</span></strong></td>
            									<td>{!! $payment_currency !!}{{number_format($data['guest_fee'],2) }}</td>
            								</tr>
								            @endif
								        @endif
								    @endif
								@endif
								
								
								@if($data['pet_fee_type']=="taxable")
    								@if($data['total_pets'])
    								    @if($data['total_pets']>0)
    								        @if($data['pet_fee'])
    								            @if($data['pet_fee']>0)
    								            <tr>
                									<td colspan="4"><strong>Pet Fee :</strong></td>
                									<td >{!! $payment_currency !!}{{number_format($data['pet_fee'],2) }}</td>
                								</tr>
    								            @endif
    								        @endif
    								    @endif
    								@endif
								@endif
								
								
								@if($data['heating_pool_fee_type']=="taxable")
    								@if($data['heating_pool_fee'])
    								    @if($data['heating_pool_fee']>0)
    								         <tr>
                									<td colspan="4" ><strong>Heating the Swimming Pool fee :</strong></td>
                									<td>{!! $payment_currency !!}{{number_format($data['heating_pool_fee'],2) }}</td>
                								</tr>
    								    @endif
    								@endif
								@endif
								
                                @foreach(json_decode($data['before_total_fees']) ?: [] as $c)
                                <tr>
                                    <td colspan="4" ><strong>{{$c->name}} :</strong></td>
                                    <td >{!! $payment_currency !!}{{number_format($c->amount ?? $c->value ?? 0,2) }}</td>
                                </tr>
                                @endforeach
                                
                              
                                
                                @if($data['tax'])
                                <tr>
                                    <td colspan="4" ><strong>Tax ({{ $data['define_tax'] ?? '-' }}%) :</strong></td>
                                    <td >{!! $payment_currency !!}{{number_format($data['tax'],2) }}</td>
                                </tr>
                                @endif
                                
                                  <tr>
                                    <td colspan="4" ><strong>Sub Total :</strong></td>
                                    <td >{!! $payment_currency !!}{{number_format($data['sub_amount'],2) }}</td>
                                </tr>
                                
								@if($data['pet_fee_type']!="taxable")
    								@if($data['total_pets'])
    								    @if($data['total_pets']>0)
    								        @if($data['pet_fee'])
    								            @if($data['pet_fee']>0)
    								            <tr>
                									<td colspan="4"><strong>Pet Fee :</strong></td>
                									<td >{!! $payment_currency !!}{{number_format($data['pet_fee'],2) }}</td>
                								</tr>
    								            @endif
    								        @endif
    								    @endif
    								@endif
								@endif
								
								
								@if($data['heating_pool_fee_type']!="taxable")
    								@if($data['heating_pool_fee'])
    								    @if($data['heating_pool_fee']>0)
    								         <tr>
                									<td colspan="4" ><strong>Heating the Swimming Pool fee :</strong></td>
                									<td>{!! $payment_currency !!}{{number_format($data['heating_pool_fee'],2) }}</td>
                								</tr>
    								    @endif
    								@endif
								@endif
								
                                @foreach(json_decode($data['after_total_fees']) ?: [] as $c)
                                <tr>
                                    <td colspan="4" ><strong>{{$c->name}} :</strong></td>
                                    <td >{!! $payment_currency !!}{{number_format($c->amount ?? $c->value ?? 0,2) }}</td>
                                </tr>
                                @endforeach
                                
                                <tr>
                                    <td colspan="4" ><strong>Total :</strong></td>
                                    <td >{!! $payment_currency !!}{{number_format($data['total_amount'],2) }}</td>
                                </tr>
                                
                                
                                
                                    @if($data['discount'])
                                        @if($data['discount']!="")
                                            @if($data['discount']!=0)
                                                
                                            <tr>
                                                <td  colspan="4"  ><strong>Discount ({{ $data['discount_coupon'] }}):</strong></td>
                                                <td>- {!! $payment_currency  !!}{{number_format($data['discount'],2) }}</td>
                                            </tr>
                                            <tr>
                                                <td  colspan="4"  ><strong>Total Amount after Discount:</strong></td>
                                                <td>{!! $payment_currency  !!}{{number_format($data['after_discount_total'],2) }}</td>
                                            </tr>
                                            @endif
                                        @endif
                                    @endif
                                @if($data['amount_data'])
                                        @php
                                            $amount_data=json_decode($data['amount_data'],true);
                                        @endphp
                                        @if(is_array($amount_data))
                                            @foreach($amount_data as $c)
                                                @php $status='';@endphp
                                                @if(isset($c['status']))
                                                    @php $status='(<span style="color:green;">Paid</span>)'; @endphp
                                                @endif
                                            <tr>
                                                <td colspan="4"><strong>{{$c['message']}} {!! $status !!}:</strong></td>
                                                <td>{!! $payment_currency !!}{{number_format($c['amount'],2) }}</td>
                                            </tr>
                                            @endforeach
                                        @endif
                                    @endif

@endif

                            </tbody>
                        </table>
@if($data['booking_type_admin']=="invoice")
                        <table class="table table-bordered">
                               <tr>
                                    <th colspan="4">Status</th>
                                </tr>
                                <tr>
                                    <th>Payment </th>
                                    <td>{{ $data['payment_status'] }}</td>
                               
                                    <th>Welcome Email </th>
                                    <td>{{ $data['welcome_email'] }}</td>
                                </tr>
                                <tr>
                                    <th>Review Email </th>
                                    <td>{{ $data['review_email'] }}</td>
                               
                                    <th>Reminder Email </th>
                                    <td>{{ $data['reminder_email'] }}</td>
                                </tr>
                                <tr>
                                    <th>Checkin Email </th>
                                    <td>{{ $data['checkin_email'] }}</td>
                               
                                    <th>Checkout Email </th>
                                    <td>{{ $data['checkout_email'] }}</td>
                                </tr>
                        </table>
                        <table class="table table-bordered" >
                            <tr>
                                <th>Payment Interval</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Tran ID</th>
                                <th>Mode</th>
                            </tr>
                            @if($data['amount_data'])
                                        @php
                                            $amount_data=json_decode($data['amount_data'],true);
                                        @endphp
                                        @if(is_array($amount_data))
                                            @foreach($amount_data as $c)
                                                @php $status='';@endphp
                                                @if(isset($c['status']))
                                                    @php $status='(<span style="color:green;">Paid</span>)'; @endphp
                                                @endif
                                            <tr>
                                                <td align="left" style="padding: 10px;" valign="top"><strong>{{$c['message']}} {!! $status !!}:</strong></td>
                                                <td align="right" style="padding: 10px;" valign="top">{!! $payment_currency !!}{{number_format($c['amount'],2) }}</td>
                                                <td align="right" style="padding: 10px;" valign="top">

                                                {{ $c['status'] ?? '--' }}
                                            </td>
                                         
                                                <td align="right" style="padding: 10px;" valign="top">{{ $c['date'] ?? '--' }}</td>
                                                <td align="right" style="padding: 10px;" valign="top">{{ $c['tran_id'] ?? '--' }}</td>
                                                <td align="right" style="padding: 10px;" valign="top">{{ $c['mode'] ?? '--' }}</td>
                                            </tr>
                                            @endforeach
                                        @endif
                                    @endif
                        </table>

@endif


      </div>

@if($client->booking_status!='cancelled')
      <!-- Modal footer -->
      <div class="modal-footer">
           <a href="{!! route($route.'.edit', [$client->id]) !!}" class="btn btn-outline-success btn-sm raw-margin-right-8 d-none"><i
                    class="fa fa-pencil-alt"></i> </a>
        @if($client->booking_status=='booked')
        <form method="post" action="{!! route($route.'.destroy', [$client->id]) !!}"
              style="display: inline-block;">
            {!! csrf_field() !!}
            {!! method_field('DELETE') !!}
            <button type="submit" class="btn btn-outline-danger btn-sm raw-margin-right-8"
                    onclick="return confirm('Are you sure you want to cancel this {{ $name }}?')">
                Cancel Booking
            </button>
        </form>
        @endif
      </div>
@endif
    </div>
  </div>
</div>

@endforeach

<script>
  $("#data-table-gaurav").DataTable({"lengthMenu": [[ 50, -1], [ 50, "All"]]});

 
</script>
@stop