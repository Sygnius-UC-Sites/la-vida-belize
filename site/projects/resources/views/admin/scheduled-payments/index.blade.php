@extends('admin.layouts')
@section('title', 'Scheduled Payments')

@section('content_header')
    <h1 class="m-0 text-dark">Scheduled Payments</h1>
@stop

@section('content')
@parent

<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-12">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('danger'))<div class="alert alert-danger">{{ session('danger') }}</div>@endif
      </div>
    </div>
  </div>
</section>

<section class="content">
<div class="container-fluid">

  {{-- Status summary chips --}}
  <div class="mb-3 d-flex gap-2 flex-wrap">
    @php
      $statuses = ['all' => 'All', 'pending' => 'Pending', 'charged' => 'Charged', 'failed' => 'Failed', 'cancelled' => 'Cancelled'];
      $colours  = ['all' => 'secondary', 'pending' => 'warning', 'charged' => 'success', 'failed' => 'danger', 'cancelled' => 'secondary'];
    @endphp
    @foreach($statuses as $key => $label)
      <a href="{{ route('admin.scheduled-payments.index', ['status' => $key]) }}"
         class="btn btn-{{ $status === $key ? $colours[$key] : 'outline-'.$colours[$key] }} btn-sm">
        {{ $label }}
        @if($key !== 'all' && isset($counts[$key]))
          <span class="badge badge-light ml-1">{{ $counts[$key] }}</span>
        @endif
      </a>
    @endforeach
  </div>

  <div class="card">
    <div class="card-body p-0">
      <table class="table table-hover table-sm mb-0" id="sp-table">
        <thead class="thead-light">
          <tr>
            <th>Due Date</th>
            <th>Booking #</th>
            <th>Guest</th>
            <th>Property</th>
            <th>PMS</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Attempts</th>
            <th>Charged At</th>
            <th>Charge / PMS Error</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($payments as $sp)
          @php
            $booking  = $sp->bookingRequest;
            $overdue  = $sp->status === 'pending' && $sp->due_date->isPast();
          @endphp
          <tr class="{{ $overdue ? 'table-danger' : ($sp->status === 'charged' ? 'table-success' : ($sp->status === 'failed' ? 'table-warning' : '')) }}">
            <td>
              {{ $sp->due_date->format('M j, Y') }}
              @if($overdue)<span class="badge badge-danger ml-1">Overdue</span>@endif
            </td>
            <td>
              @if($booking)
                <a href="{{ route('booking-enquiries.show', $booking->id) }}" target="_blank">#{{ $booking->id }}</a>
              @else
                #{{ $sp->booking_request_id }}
              @endif
            </td>
            <td>{{ $booking->name ?? '—' }}</td>
            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              {{ optional(\App\Models\Pms\PmsRoom::find($booking->property_id ?? null))->name ?? '—' }}
            </td>
            <td><span class="badge badge-info">{{ strtoupper($sp->pms_source) }}</span></td>
            <td><strong>{{ strtoupper($sp->currency) }} {{ number_format($sp->amount, 2) }}</strong></td>
            <td>
              @if($sp->status === 'charged')
                <span class="badge badge-success">Charged</span>
              @elseif($sp->status === 'failed')
                <span class="badge badge-danger">Failed</span>
              @else
                <span class="badge badge-warning">Pending</span>
              @endif
            </td>
            <td class="text-center">{{ $sp->attempts }}</td>
            <td>{{ $sp->charged_at ? $sp->charged_at->format('M j, Y g:ia') : '—' }}</td>
            <td style="max-width:220px;font-size:11px;color:#c0392b;">
              @php
                $errors = array_filter([
                  $sp->error_message ? 'Charge: ' . $sp->error_message : null,
                  $sp->pms_notify_error ? 'PMS: ' . $sp->pms_notify_error : null,
                ]);
              @endphp
              {{ $errors ? \Str::limit(implode(' | ', $errors), 90) : '—' }}
            </td>
            <td style="white-space:nowrap;">
              @if($sp->status === 'pending')
                <form method="post" action="{{ route('admin.scheduled-payments.mark-charged', $sp->id) }}" style="display:inline">
                  @csrf
                  <button type="submit" class="btn btn-xs btn-success"
                          onclick="return confirm('Mark this payment as manually charged?')"
                          title="Mark as charged (e.g. collected offline)">✓ Mark Charged</button>
                </form>
              @elseif($sp->status === 'failed')
                <form method="post" action="{{ route('admin.scheduled-payments.reset', $sp->id) }}" style="display:inline">
                  @csrf
                  <button type="submit" class="btn btn-xs btn-warning"
                          onclick="return confirm('Reset to pending so the cron retries this payment?')"
                          title="Reset to pending — cron will retry">↺ Retry</button>
                </form>
              @endif
            </td>
          </tr>
          @empty
          <tr><td colspan="11" class="text-center text-muted py-4">No scheduled payments found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
</section>
@stop

@section('js')
<script>
$(document).ready(function() {
    if ($('#sp-table tbody td[colspan]').length) return;
    $('#sp-table').DataTable({
        order: [[0, 'asc']],
        pageLength: 50,
        columnDefs: [{ orderable: false, targets: [10] }]
    });
});
</script>
@stop
