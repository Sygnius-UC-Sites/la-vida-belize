@extends('admin.layouts')
@section('title', 'Admin')
@php 
    $name="Properties Rooms";$route="properties-group-rooms";
@endphp            
@section('content_header')
    <h1 class="m-0 text-dark"><span class="badge badge-primary">{!! $property->name !!}</span> <i class='fa fa-arrow-right'></i>  {!! $name !!}  Management</h1>
@stop

@section('content')
    @parent
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-12">
          @php 
            $addbar=["name"=>$name,"add-data"=>true,"add-name"=>"Add ". Str::singular($name),"add-anchor"=>route($route.'.create',[$property_id]),"back-anchor"=>route('properties.index')];
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
              <h3 class="card-title"><span class="badge badge-primary">{!! $property->name !!}</span> <i class='fa fa-arrow-right'></i>  {!! $name !!}  Listing</h3>
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
                        <th>#</th>
                        
                        <th>Title</th>
                        <th>Sub Title</th>
                        
                        <th>Status</th>
                        <th>Sub Rooms </th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                        @php $sno=1;@endphp
                    @foreach($data as $client)
                        <tr>
                           
                            <td>{{ $sno++ }}</td>

                       
                            <td>{{ $client->room_title }}</td>
                            
                            
                            <td>{{ $client->room_sub_title }}</td>
                            <td>
                                @if($client->room_status == "true")
                                    <i class="fa fa-check text-success"></i>
                                @else
                                    <i class="fa fa-times text-danger"></i>
                                @endif
                            </td>
                            <td>
                              @foreach(App\Models\PropertyRoomItem::where("room_id",$client->id)->get() as $c)
                                <span class="badge badge-warning">{{ $c->sub_room_title}}</span>
                                @endforeach
                                <hr>
                                  <a href="{!! route('properties-sub-room', [$property_id,$client->id]) !!}" class="btn btn-success btn-xs raw-margin-right-8 "><i
                                            class="fa fa-plus"></i> Sub Room</a>
                            </td>
                            <td>
                                <a href="{!! route($route.'.edit', [$property_id,$client->id]) !!}" class="btn btn-outline-success btn-xs raw-margin-right-8 btn-block"><i
                                            class="fa fa-pencil-alt"></i> Edit</a>
                                @if($client->room_status == "true")
                                    <a href="{!! route($route.'.deactive', [$property_id,$client->id]) !!}" class="btn btn-warning btn-xs raw-margin-right-8 btn-block"><i
                                            class="fa fa-times"></i> Deactive</a>
                                @else
                                    <a href="{!! route($route.'.active', [$property_id,$client->id]) !!}" class="btn btn-warning btn-xs raw-margin-right-8 btn-block"><i
                                            class="fa fa-check"></i> Active</a>
                                @endif
                                <br>
                                <form method="post" action="{!! route($route.'.destroy', [$property_id,$client->id]) !!}"
                                      >
                                    {!! csrf_field() !!}
                                    {!! method_field('DELETE') !!}
                                    <button type="submit" class="btn btn-outline-danger btn-xs raw-margin-right-8"
                                            onclick="return confirm('Are you sure you want to delete this {{ $name }}, Destroy All child data?')"><i
                                                class="fa fa-trash"></i> Delete
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
  $("#data-table-gaurav").DataTable({"lengthMenu": [[ 50, -1], [ 50, "All"]]});
                   




</script>
@stop