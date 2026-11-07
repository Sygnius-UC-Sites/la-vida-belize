@extends('admin.layouts')
@section('title', 'Admin')
@php
$name="Our Teams";$route="our-teams";
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
          <table id="data-table-gaurav" class="table table-bordered table-striped dt-responsive nowrap" style="width:100%">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Profile</th>
                <th> Image</th>
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
                  {{ $client->profile }}
                </td>


                <td>
                  <img src="{{ asset($client->image) }}" width="100" height="50">
                </td>
                <td>
                  <a href="{!! route($route.'.edit', [$client->id]) !!}" class="btn btn-outline-success btn-xs raw-margin-right-8"><i
                      class="fa fa-pencil-alt"></i> Edit</a>
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
  $("#data-table-gaurav").DataTable();
  $(document).on("click", ".delete-checkbox", function() {
    ajaxCallCheckBoxDelete("{{ route('multipleDelete','our-teams') }}", "{{ route('our-teams.index') }}", "our-teams");
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