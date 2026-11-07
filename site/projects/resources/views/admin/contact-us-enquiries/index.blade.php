@extends('admin.layouts')
@section('title', 'Admin')
@php
$name="Contact us Enquiries";$route="contact-us-enquiries";
@endphp
@section('content_header')
<div class="row">
  <div class="col-sm-6">
    <h1 class="m-0 text-dark">{{$name}} Management</h1>
  </div>
  @php
  $addbar=["name"=>$name,"add-data"=>true,"add-name"=>"Add ". Str::singular($name),"add-anchor"=>route($route.'.create'),'expot-data'=>route('exportData',$route)];
  @endphp
  @include("admin.common.add-bar")
</div>
@stop

@section('content')
@parent
<!-- Content Header (Page header) -->
<!-- <section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-12">
        @php
        $addbar=["name"=>$name,"add-data"=>true,"add-name"=>"Add ". Str::singular($name),"add-anchor"=>route($route.'.create'),'expot-data'=>route('exportData',$route)];
        @endphp
        @include("admin.common.add-bar")
      </div>
    </div>
  </div>
</section> -->

<!-- Main content -->
<section class="content">

  <div class="row">
    <div class="col-12">

      <div class="card  ">
        <!-- <div class="card-header">
          <h3 class="card-title">{{ $name }} Listing</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="maximize"><i class="fas fa-expand"></i>
            </button>
          </div>
        </div> -->
        <!-- /.card-header -->
        <div class="card-body">
          <style>
              #data-table-gaurav { width: 100%; table-layout: auto; }
              #data-table-gaurav th, #data-table-gaurav td {
                  font-size: 13px;
                  padding: 12px 14px;
                  vertical-align: middle;
              }
              #data-table-gaurav th {
                  font-size: 11px;
                  text-transform: uppercase;
                  letter-spacing: .10em;
                  color: var(--ca-ink-mute);
                  font-weight: 600;
              }
              #data-table-gaurav .col-contact { min-width: 200px; }
              #data-table-gaurav .col-inquiry { min-width: 180px; }
              #data-table-gaurav .col-message { max-width: 280px; }
              #data-table-gaurav .col-submitted { white-space: nowrap; min-width: 100px; }
              #data-table-gaurav .col-actions { width: 100px; white-space: nowrap; text-align: right; }
              #data-table-gaurav .cell-name {
                  font-weight: 600; color: var(--ca-ink);
                  font-size: 13.5px;
                  letter-spacing: -.005em;
              }
              #data-table-gaurav .cell-meta {
                  font-size: 12px; color: var(--ca-ink-mute);
                  font-family: var(--admin-font-mono);
                  margin-top: 2px;
                  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
                  max-width: 240px;
              }
              #data-table-gaurav .cell-prop {
                  font-size: 12.5px; color: var(--ca-ink); font-weight: 500;
              }
              #data-table-gaurav .cell-dates {
                  font-size: 12px; color: var(--ca-ink-mute);
                  margin-top: 2px;
                  font-family: var(--admin-font-mono);
              }
              #data-table-gaurav .cell-msg {
                  font-size: 12.5px;
                  color: var(--ca-ink-soft);
                  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
                  max-width: 280px; display: block;
              }
          </style>
          <table id="data-table-gaurav" class="table table-hover">
            <thead>
              <tr>
                <th class="col-contact">Contact</th>
                <th class="col-inquiry">Inquiry</th>
                <th class="col-message">Message</th>
                <th class="col-submitted">Submitted</th>
                <th class="col-actions">Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($data as $client)
              <tr>
                <td>
                  <div class="cell-name">{{ $client->name ?: '—' }}</div>
                  @if($client->email)
                    <div class="cell-meta">{{ $client->email }}</div>
                  @endif
                  @if($client->mobile)
                    <div class="cell-meta">{{ $client->mobile }}</div>
                  @endif
                </td>
                <td>
                  <div class="cell-prop">
                    @if($client->property_slug)
                      <code>{{ $client->property_slug }}</code>
                    @elseif($client->property)
                      {{ ucfirst($client->property) }}
                    @else
                      <span class="text-muted">No property linked</span>
                    @endif
                  </div>
                  @if($client->check_in)
                    <div class="cell-dates">{{ $client->check_in }} → {{ $client->check_out ?? '?' }}{{ $client->guests ? ' · '.$client->guests.' guests' : '' }}</div>
                  @endif
                </td>
                <td>
                  @if($client->message)
                    <span class="cell-msg" title="{{ $client->message }}">
                      {{ \Illuminate\Support\Str::limit($client->message, 90) }}
                    </span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td class="col-submitted">
                  <span title="{{ $client->created_at }}" style="font-size:12px;color:var(--ca-ink-mute);font-family:var(--admin-font-mono)">
                    {{ \Carbon\Carbon::parse($client->created_at)->format('M j, Y') }}
                  </span>
                </td>
                <td class="col-actions">
                  <a href="{!! route($route.'.edit', [$client->id]) !!}" class="btn btn-outline-secondary btn-sm" title="Edit"><i class="fa fa-pencil-alt"></i></a>
                  <form method="POST" action="{{ route($route.'.destroy', [$client->id]) }}"
                    style="display: inline-block;" id="delete-form-{{ $client->id }}">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-outline-danger btn-sm delete-btn"
                      data-id="{{ $client->id }}" data-name="{{ $client->name }}" title="Delete">
                      <i class="fa fa-trash"></i>
                    </button>
                  </form>
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
<script>
  $("#data-table-gaurav").DataTable({
    "lengthMenu": [
      [50, -1],
      [50, "All"]
    ]
  });
</script>
<script>
  document.querySelectorAll('.delete-btn').forEach(button => {
    button.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const name = this.getAttribute('data-name');

      Swal.fire({
        title: 'Are you sure?',
        html: `Do you want to delete ${name}.<br>This action cannot be undone!`,
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (result.value) {
          document.getElementById(`delete-form-${id}`).submit();
        }
      });
    });
  });
</script>
@stop