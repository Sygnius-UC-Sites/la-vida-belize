@extends('admin.layouts')
@section('title', 'Admin')
@php
$name="Attractions";$route="attractions";
@endphp
@section('content_header')
<div class="row">
  <div class="col-sm-6">
    <h1 class="m-0 text-dark">{{$name}} Management</h1>
    <a href="{{ url('admin/attraction-categories') }}" class="btn btn-outline-primary btn-sm mt-2"><i class="fa fa-tags"></i> Manage Categories</a>
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
  <div class="ca-page-toolbar">
    <div class="ca-page-toolbar-title">
      <h1>Attractions</h1>
      <span class="ca-page-toolbar-count">{{ $data->count() }}</span>
    </div>
    <div class="ca-page-toolbar-filters">
      <a href="{{ url('admin/attraction-categories') }}" class="btn btn-outline-primary btn-sm">
        <i class="fa fa-tags"></i> Manage categories
      </a>
      <a href="{{ route($route.'.create') }}" class="ca-page-toolbar-action">
        <i class="fa fa-plus"></i> Add attraction
      </a>
    </div>
  </div>

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
          <table id="data-table-gaurav" class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Filters / Linkage</th>
                <th>URL</th>
                <th>Type</th>
                <th>Ordering</th>
                <th>Image</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @php $sno=1;@endphp
              @foreach($data as $client)
              <tr>

                <td>{{ $sno++ }}</td>
                <td><strong>{{ $client->name }}</strong></td>
                <td>
                  @php
                    $loc = $client->location_id ? App\Models\Location::find($client->location_id) : null;
                    $cat = $client->category_id ? App\Models\AttractionCategory::find($client->category_id) : null;
                    $prop = $client->property_id ? App\Models\Property::find($client->property_id) : null;
                  @endphp
                  @if($loc)<span class="badge badge-light"><i class="fa fa-map-marker-alt"></i> {{ $loc->name }}</span>@endif
                  @if($cat)<span class="badge badge-light"><i class="fa fa-tag"></i> {{ $cat->name }}</span>@endif
                  @if($prop)<span class="badge badge-info"><i class="fa fa-link"></i> {{ $prop->name }}</span>@endif
                </td>
                <td>
                  {{ $client->seo_url }}
                </td>
                <td>
                  {{ $client->type }}
                </td>
                <td>
                  {{ $client->ordering }}
                </td>



                <td>
                  @if($client->image)
                  <img src="{{ asset($client->image) }}" class="owner-form-preview" alt="">
                  @endif
                </td>

                <td>
                  <a href="{!! route($route.'.edit', [$client->id]) !!}" class="btn btn-outline-primary btn-sm raw-margin-right-8"><i
                      class="fa fa-pencil-alt"></i> Edit</a>
                  <form method="POST" action="{{ route($route.'.destroy', [$client->id]) }}"
                    style="display: inline-block;" id="delete-form-{{ $client->id }}">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-outline-danger btn-sm raw-margin-right-8 delete-btn"
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
  $("#data-table-gaurav").DataTable({
    "lengthMenu": [
      [50, -1],
      [50, "All"]
    ],
    dom: "<'row'<'col-sm-3'l><'col-sm-3'f><'col-sm-6'p>>" +
      "<'row'<'col-sm-12'tr>>" +
      "<'row'<'col-sm-5'i><'col-sm-7'p>>",
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
