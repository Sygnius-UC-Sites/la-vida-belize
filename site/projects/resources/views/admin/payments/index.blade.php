@extends('admin.layouts')
@section('title', 'Admin')
@php
$name="Payments";$route="payments";
@endphp
@section('content_header')
<div class="row">
  <div class="col-sm-6">
    <h1 class="m-0 text-dark">{{$name}} Management</h1>
  </div>
  @php
  $addbar=["name"=>$name,"add-data"=>false,"add-name"=>"Add ". Str::singular($name),"add-anchor"=>route($route.'.create')];
  @endphp
  @include("admin.common.add-bar")
</div>
@stop

@section('content')
@parent

<!-- Main content -->
<section class="content">

  <div class="row">
    <div class="col-12">

      <div class="card">
        <div class="card-body">
          {{-- QA pass: bulk "Delete all" button so admins can clear dummy/test
               payment history without removing rows one at a time. --}}
          @if (count($data) > 0)
            <div class="mb-3 d-flex justify-content-end">
              <form method="POST" action="{{ route('payments.deleteAll') }}" onsubmit="return confirm('This will permanently delete ALL {{ count($data) }} payment records. This cannot be undone. Continue?');" class="m-0">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm">
                  <i class="fa fa-trash"></i> Delete all payment records
                </button>
              </form>
            </div>
          @endif

          <table id="data-table-gaurav" class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>#</th>
                <th>Booking ID</th>
                <th>Property Name</th>
                <th>Customer Name</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Tran ID</th>
                <th>PMS Sync</th>
                <th>Request of Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @php $sno=1;@endphp
              @foreach($data as $client)
              @php
              $booking=App\Models\BookingRequest::find($client->booking_id);
              $property = $booking ? App\Models\Property::find($booking->property_id) : null;
              @endphp
              <tr>
                <td>{{ $sno++ }}</td>
                <td>{{ $booking->id ?? 'Booking Not Available' }}</td>
                <td>{{ $property->name ?? 'Property Not Available' }}</td>
                <td>{{ $booking->name ?? 'Booking Not Available' }}</td>
                <td>{{ $client->type }}</td>
                <td>{{ $client->amount }}</td>
                <td>{{ $client->tran_id }}</td>
                <td style="max-width:220px;font-size:12px;">
                  @php
                    $syncStatus = $client->pms_sync_status ?: 'n/a';
                    $badge = match($syncStatus) {
                      'synced' => 'success',
                      'failed' => 'danger',
                      'syncing', 'pending' => 'warning',
                      'not_supported', 'refund_pending_manual' => 'info',
                      default => 'secondary',
                    };
                  @endphp
                  <span class="badge badge-{{ $badge }}">{{ $syncStatus }}</span>
                  @if($client->pms_sync_error)
                    <div class="text-danger mt-1">{{ \Str::limit($client->pms_sync_error, 80) }}</div>
                  @endif
                </td>
                <td>{{ $client->created_at }}</td>
                <td>
                  <form method="POST" action="{{ route('payments.destroy', $client->id) }}" onsubmit="return confirm('Delete this payment record (Tran ID: {{ $client->tran_id }})? This cannot be undone.');" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm" title="Delete this payment record">
                      <i class="fa fa-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</section>
@stop
@section("js")
@parent
<script>
  $("#data-table-gaurav").DataTable({
    "lengthMenu": [
      [50, -1],
      [50, "All"]
    ]
  });
</script>
@stop
