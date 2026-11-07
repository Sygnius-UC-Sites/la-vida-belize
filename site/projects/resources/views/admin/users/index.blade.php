@extends('admin.layouts')
@section('title', 'Admin')
@php
$name="Users";$route="users";
$canManageDevelopers = $canManageDevelopers ?? false;
@endphp
@section('content_header')
<div class="row">
  <div class="col-sm-6">
    <h1 class="m-0 text-dark">{{$name}} Management</h1>
  </div>
  @php
  $addbar=["name"=>$name,"add-data"=>true,"add-name"=>"Add ". Str::singular($name),"add-anchor"=>route($route.'.create')];
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
        $addbar=["name"=>$name,"add-data"=>true,"add-name"=>"Add ". Str::singular($name),"add-anchor"=>route($route.'.create')];
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
          <div class="alert alert-info">
            <strong>User access:</strong> Create admin logins here and use Edit to reset a user's password.
            @if($canManageDevelopers)
              Developer accounts can also be created or toggled from this screen.
            @else
              Developer accounts are managed by developer users only.
            @endif
          </div>
          <table id="data-table-gaurav" class="table  ">
            <thead>
              <tr>
                <th>#</th>
                <th>name</th>
                <th> Email</th>
                <th>Access</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @php $sno=1;@endphp
              @foreach($data as $client)
              <tr>

                <td>{{ $sno++ }}</td>
                <td>
                  {{ $client->name }}
                </td>
                <td>
                  {{ $client->email}}
                </td>
                <td>
                  @if(\App\Helper\FeatureFlags::isDeveloper($client))
                    <span class="badge badge-dark">Developer</span>
                  @else
                    <span class="badge badge-secondary">Admin user</span>
                  @endif
                </td>
                <td>
                  <a href="{!! route($route.'.edit', [$client->id]) !!}" class="btn  btn-outline-success btn-xs raw-margin-right-8"><i
                      class="fa fa-pencil-alt"></i> Edit</a>
                  @if((int) auth()->id() !== (int) $client->id)
                    <form method="post" action="{!! route($route.'.destroy', [$client->id]) !!}"
                      style="display: inline-block;" id="delete-form-{{ $client->id }}">
                      @csrf
                      @method('DELETE')
                      <button type="button" class="btn btn-outline-danger btn-xs raw-margin-right-8 delete-btn"
                        data-id="{{ $client->id }}"
                        data-name="{{ $client->name }}">
                        <i class="fa fa-trash"></i> Delete
                      </button>
                    </form>
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
<script>
  $(function() {
    $("#data-table-gaurav").DataTable({
      "lengthMenu": [
        [50, -1],
        [50, "All"]
      ],
      dom: "<'row'<'col-sm-3'l><'col-sm-3'f><'col-sm-6'p>>" +
        "<'row'<'col-sm-12'tr>>" +
        "<'row'<'col-sm-5'i><'col-sm-7'p>>",
    });
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
