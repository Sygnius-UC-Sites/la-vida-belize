@extends('admin.layouts')
@section('title', 'Admin')
@php 
    $name="PMS Bookings";$route="pms-bookings";
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
            $addbar=["name"=>$name,"add-data"=>false,"add-name"=>"Add ". Str::singular($name),"add-anchor"=>route('admin.'.$route.'.create')
            ,"booking-cron-job"=>true
            ,"property-cron-job"=>true
           
            
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
                        <th>PMS Source</th>
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
                            <td>
                                @if(str_contains($client->booking_type_admin, '-sync'))
                                    <span class="badge badge-info">{{ strtoupper(str_replace('-sync', '', $client->booking_type_admin)) }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ $client->booking_type_admin ?? 'direct' }}</span>
                                @endif
                            </td>
                            <td>{{ date("F jS, Y",strtotime($client->checkin)) }}</td>
                            <td>{{ date("F jS, Y",strtotime($client->checkout)) }}</td>
                            <td>{{ $client->id }}</td>
                            <td>{{ App\Models\Pms\PmsProperty::find($client->property_id)->name ?? $client->property_id }}</td>
                           
                           
                           
                            <td>
                                {{ $client->name }}
                                <br>
                                {{ $client->email }}
                           
                            </td>
                            <td>{{ $client->total_guests }}</td>
                            <td>{{ $client->total_night }}</td>
                            <td>@if($client->booking_type_admin=="invoice") {!! $payment_currency !!}{{ $client->total_amount }} @endif</td>
                            <td>{{ date("F jS, Y",strtotime($client->created_at)) }}</td>
                         
                            <!--<td >{!! Helper::getBookingStatus($client->booking_status,$client->id) !!}</td>  -->
                        	<td>{{$client->status}}</td>
                       
                      
                           
                         
                            <td>
                                <a href="javascript:;" class="btn btn-outline-primary btn-sm raw-margin-right-8" data-toggle="modal" data-target="#myModal{{ $client->id }}">
                                    <i class="fa fa-eye"></i>
                                </a>
                                
                                @if($client->booking_status!='booking-cancel')
                                @if($client->booking_status!='booking-confirmed' )
                                <a href="{!! route('admin.'.$route.'.edit', [$client->id]) !!}" class="d-none btn btn-outline-success btn-sm raw-margin-right-8  d-none"><i
                                            class="fa fa-pencil-alt"></i> </a>
                                @elseif($client->booking_type_admin!="invoice")
                                  <a href="{!! route('admin.'.$route.'.edit', [$client->id]) !!}" class="d-none btn btn-outline-success btn-sm raw-margin-right-8  d-none"><i
                                            class="fa fa-pencil-alt"></i> </a>
                                            @endif
                                            
                                            @if($client->status=='Booked')
                                            <form method="post" action="{!! route('admin.'.$route.'.destroy', [$client->id]) !!}"
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
            $property=App\Models\Pms\PmsProperty::find($client->property_id);
        @endphp
                <table class="table table-bordered" >
                            <tbody>
                                <tr>
                                    <th colspan="4" ><strong>Property Detail </strong></th>
                                </tr>
                                <tr>
                                    <th>Request ID</th>
                                    <td>{{ $data['request_id']  }}</td>
                               
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
                                        {{$data['message']}}
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
                                    <td ><strong> Amount :</strong></td>
                                    
                                </tr>
                                <tr>
                                    <td >{{$data['checkin'] }}</td>
                                    <td >{{$data['checkout'] }}</td>
                                    <td >{{$data['total_guests'] }} ({{$data['adults']}} Adults, {{$data['child']}} Child)</td>
                                    <td >{{$data['total_night'] }}</td>
                                    <td ></td>
                                </tr>
                                	
							
								
                                @foreach(json_decode($data['before_total_fees']) ?? [] as $b)
                                @foreach($b->prices ?? [] as $c)
                                <tr>
                                    <td colspan="4" ><strong>{{$c->description}}  :</strong></td>
                                    <td >{!! $payment_currency !!}{{number_format($c->amount,2) }}</td>
                                </tr>
                                @endforeach
                                @endforeach
                                
								
                                @foreach(json_decode($data['after_total_fees']) ?? [] as $c)
                                <tr>
                                    <td colspan="4" ><strong>{{$c->name ?? ''}}:</strong></td>
                                    <td >{!! $payment_currency !!}{{number_format($c->amount ?? 0,2) }}</td>
                                </tr>
                                @endforeach
                                
                               
                                
                                
                                @if($data['tax'])
                                <tr>
                                    <td colspan="4" ><strong>Tax :</strong></td>
                                    <td >{!! $payment_currency !!}{{number_format($data['tax'],2) }}</td>
                                </tr>
                                @endif
                                
                           
                                
                                
                                <tr>
                                    <td colspan="4" ><strong>Total :</strong></td>
                                    <td >{!! $payment_currency !!}{{number_format($data['total_amount'],2) }}</td>
                                </tr>
                                
                                <tr>
                                    <td colspan="4" ><strong>Reservation deposit :</strong></td>
                                    <td >{!! $payment_currency !!}{{number_format($data['total_payment'],2) }}</td>
                                </tr>
                                
                                
                                

                            </tbody>
                        </table>
@if($data['booking_type_admin']=="invoice")
                        <table class="table table-bordered d-none">
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
                       
@endif



      </div>

{{-- ── Security Deposit Panel ──────────────────────────────────────────── --}}
@if($client->security_deposit_amount > 0)
<div class="px-3 pb-2">
    <div class="card card-outline {{ $client->security_deposit_status === 'captured' ? 'card-danger' : ($client->security_deposit_status === 'released' ? 'card-success' : 'card-warning') }} mb-0">
        <div class="card-header py-2">
            <strong>Security Deposit</strong>
            <span class="badge badge-{{ $client->security_deposit_status === 'authorized' ? 'warning' : ($client->security_deposit_status === 'captured' ? 'danger' : ($client->security_deposit_status === 'released' ? 'success' : 'secondary')) }} ml-2">
                {{ ucfirst($client->security_deposit_status ?? 'none') }}
            </span>
            <span class="float-right text-muted small">
                {{ $client->security_deposit_currency }} {{ number_format($client->security_deposit_amount, 2) }}
            </span>
        </div>
        @if($client->security_deposit_status === 'authorized')
        <div class="card-body py-2 d-flex gap-2">
            <form method="post" action="{{ route('admin.booking.deposit.capture', $client->id) }}" style="display:inline;">
                {!! csrf_field() !!}
                <button type="submit" class="btn btn-danger btn-sm"
                        onclick="return confirm('Charge {{ $client->security_deposit_currency }} {{ number_format($client->security_deposit_amount, 2) }} security deposit? This cannot be undone.')">
                    Capture Deposit
                </button>
            </form>
            &nbsp;
            <form method="post" action="{{ route('admin.booking.deposit.release', $client->id) }}" style="display:inline;">
                {!! csrf_field() !!}
                <button type="submit" class="btn btn-success btn-sm"
                        onclick="return confirm('Release the security deposit hold? The guest will not be charged.')">
                    Release Hold
                </button>
            </form>
            <small class="text-muted ml-2 align-self-center">Charge ID: {{ $client->stripe_deposit_charge_id }}</small>
        </div>
        @elseif($client->security_deposit_status === 'failed')
        <div class="card-body py-2">
            <small class="text-danger">Pre-authorization failed. Collect the deposit manually if required.</small>
        </div>
        @endif
    </div>
</div>
@endif

{{-- ── Stripe Refund Panel ──────────────────────────────────────────────── --}}
@php
    $stripePayment = App\Models\Payment::where('booking_id', $client->id)
        ->where('type', 'stripe')->latest()->first();
@endphp
@if($stripePayment && !in_array($stripePayment->status, ['refunded']))
<div class="px-3 pb-2">
    <div class="card card-outline card-secondary mb-0">
        <div class="card-header py-2">
            <strong>Stripe Payment</strong>
            <span class="badge badge-{{ $stripePayment->status === 'partially-refunded' ? 'warning' : 'success' }} ml-2">
                {{ ucfirst($stripePayment->status) }}
            </span>
            <span class="float-right text-muted small">${{ number_format($stripePayment->amount, 2) }}</span>
        </div>
        <div class="card-body py-2">
            <form method="post" action="{{ route('admin.booking.refund', $client->id) }}"
                  onsubmit="return confirmRefund(this)">
                {!! csrf_field() !!}
                <div class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="max-width:160px;">
                        <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                        <input type="number" name="refund_amount" class="form-control"
                               step="0.01" min="0.01" max="{{ $stripePayment->amount }}"
                               placeholder="{{ $stripePayment->amount }}"
                               title="Leave blank for full refund">
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm">Issue Refund</button>
                    <small class="text-muted">Leave blank for full refund</small>
                </div>
                @if($stripePayment->status === 'partially-refunded')
                <small class="text-warning d-block mt-1">A partial refund has already been issued for this booking.</small>
                @endif
            </form>
        </div>
    </div>
</div>
@endif

@if($client->booking_status!='booking-cancel')
      <!-- Modal footer -->
      <div class="modal-footer">
           <a href="{!! route('admin.'.$route.'.edit', [$client->id]) !!}" class="btn btn-outline-success btn-sm raw-margin-right-8 d-none"><i
                    class="fa fa-pencil-alt"></i> </a>
        @if($client->booking_status === 'payment-captured-pms-failed')
        <form method="post" action="{{ route('admin.booking.retry-pms', $client->id) }}" style="display:inline-block;">
            {!! csrf_field() !!}
            <button type="submit" class="btn btn-warning btn-sm raw-margin-right-8"
                    onclick="return confirm('Retry PMS reservation for this booking?')"
                    title="Payment captured but PMS failed — click to retry">
                <i class="fa fa-refresh"></i> Retry PMS
            </button>
        </form>
        @endif
        @if($client->booking_status=='booking-confirmed')
        <form method="post" action="{!! route('admin.'.$route.'.destroy', [$client->id]) !!}"
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

  function confirmRefund(form) {
      const input = form.querySelector('[name="refund_amount"]');
      const amount = input.value ? '$' + parseFloat(input.value).toFixed(2) : 'the full amount';
      return confirm('Issue a refund of ' + amount + '? This cannot be undone.');
  }

 
</script>
@stop