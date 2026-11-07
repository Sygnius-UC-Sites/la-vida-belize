@extends('admin.layouts')
@section('title', 'Admin')
@php 
    $name="Properties";$route="pms_properties";
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
            $addbar=["name"=>$name,"add-data"=>false,"add-name"=>"Add ". Str::singular($name),"add-anchor"=>route('admin.'.$route.'.create'),"back-anchor"=>route('admin.'.$route.'.index')];
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
              <h3 class="card-title">Edit {{ Str::singular($name) }}    --  {{ $data->name }}</h3>
               <div class="card-tools">
                  <button type="button" class="btn btn-tool" data-card-widget="maximize"><i class="fas fa-expand"></i>
                  </button>
              </div>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
               {!! Form::model($data,['route' => ['admin.'.$route.'.update',$data->id],"files"=>"true","method"=>"put"]) !!}
     
                    @include("admin.".$route.".form")
               
                    <button class="btn btn-success"><span class="fa fa-save"></span> Update</button>
                
                {!! Form::close() !!}
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

@section("css")
@parent
<link rel="stylesheet" type="text/css" href="{{ asset('drag-drop-image-uploader/src/image-uploader.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css" integrity="sha512-ELV+xyi8IhEApPS/pSj66+Jiw+sOT1Mqkzlh8ExXihe4zfqbWkxPRi8wptXIO9g73FSlhmquFlUOuMSoXz5IRw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
  .gaurav-class{
    border:1px solid black;
    margin:10px;
    padding: 10px;
  }
</style>
@stop
@section("js")
@parent
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js" integrity="sha512-57oZ/vW8ANMjR/KQ6Be9v/+/h6bq9/l3f0Oc7vn6qMqyhvPd1cvKBRWWpzu0QoneImqr2SkmO4MSqU+RpHom3Q==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="{{ asset('drag-drop-image-uploader/src/image-uploader.js') }}"></script>


<script>
  $(document).on("click",".nav-item",function(){
      var target_gaurav=$(this).find(".nav-link").attr("id");
      document.cookie = "target_jhon_data="+target_gaurav;
  });


  @if(isset($_COOKIE['target_jhon_data']))
    $(function(){
      $("#{{$_COOKIE['target_jhon_data']}}").click();
    })
  @endif
  
  </script>
@stop