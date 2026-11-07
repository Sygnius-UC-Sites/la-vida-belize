@extends('admin.layouts')
@section('title', 'Admin')
@php 
    $name="Cars";$route="properties";
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
              <h3 class="card-title">Create {{ Str::singular($name) }}</h3>
               <div class="card-tools">
                  <button type="button" class="btn btn-tool" data-card-widget="maximize"><i class="fas fa-expand"></i>
                  </button>
              </div>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
               {!! Form::open(['route' => 'admin.'.$route.'.store',"files"=>"true"]) !!}
     
                    @include("admin.".$route.".form")
               
                    <button class="btn btn-success"><span class="fa fa-save"></span> Save</button>
                
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
@stop
@section("js")
@parent
<script src="{{ asset('drag-drop-image-uploader/src/image-uploader.js') }}"></script>
<script>
  $(function(){
     CKEDITOR.replace( 'short_description',{ allowedContent:true,filebrowserUploadUrl: "{{route('ckeditor.upload', ['_token' => csrf_token() ])}}",
        filebrowserUploadMethod: 'form'} );

     CKEDITOR.replace( 'long_description',{ allowedContent:true,filebrowserUploadUrl: "{{route('ckeditor.upload', ['_token' => csrf_token() ])}}",
        filebrowserUploadMethod: 'form'} );

     CKEDITOR.replace( 'cancellation_policy',{ allowedContent:true,filebrowserUploadUrl: "{{route('ckeditor.upload', ['_token' => csrf_token() ])}}",
        filebrowserUploadMethod: 'form'} );

     CKEDITOR.replace( 'booking_policy',{ allowedContent:true,filebrowserUploadUrl: "{{route('ckeditor.upload', ['_token' => csrf_token() ])}}",
        filebrowserUploadMethod: 'form'} );

     CKEDITOR.replace( 'notes',{ allowedContent:true,filebrowserUploadUrl: "{{route('ckeditor.upload', ['_token' => csrf_token() ])}}",
        filebrowserUploadMethod: 'form'} );
     CKEDITOR.replace( 'welcome_package_description',{ allowedContent:true,filebrowserUploadUrl: "{{route('ckeditor.upload', ['_token' => csrf_token() ])}}",
        filebrowserUploadMethod: 'form'} );

     

        $('.input-images-2').imageUploader({
            imagesInputName: 'images'
        });
  });
</script>
<script>
    $(document).on("click",".delete-fee-data",function(){
        $(this).parents(".gaurav-delete-property").remove();
    });

    $(document).on("click",".add-fee-data",function(){
        html=`
            <div class="row gaurav-delete-property">
                <div class="col-md-2">
                    {!! Form::text("fee_name[]",null,["required","class"=>"form-control","placeholder"=>"Fee Name"]) !!}
                </div>
                <div class="col-md-2">
                    {!! Form::number("fee_rate[]",null,["required","class"=>"form-control","placeholder"=>"Fee Rate"]) !!}
                </div>
                <div class="col-md-2">
                    {!! Form::select("fee_type[]",["Percentage"=>"Percentage","Excat"=>"Excat"],null,["required","class"=>"form-control"]) !!}
                </div>
                <div class="col-md-2">
                    {!! Form::select("fee_apply[]",["total"=>"total","gross"=>"gross"],null,["required","class"=>"form-control"]) !!}
                </div>
                <div class="col-md-2">
                    {!! Form::select("fee_status[]",["active"=>"active","deactive"=>"deactive"],null,["required","class"=>"form-control"]) !!}
                </div>
                <div class="col-md-2">
                    <a href="javascript:;" class="delete-fee-data btn btn-danger " ><i class="fa fa-trash"></i> </a>
                </div>
                
                <div class="col-md-12">
                    <br>
                </div>
            </div>
        `;

        $("#fee-area-section").append(html);
    });



    $(document).on("click",".delete-space-data",function(){
        $(this).parents(".gaurav-delete-property-space").remove();
    });

    $(document).on("click",".add-space-data",function(){
        html=`
            <div class="row gaurav-delete-property-space">
                <div class="col-md-4">
                    {!! Form::text("space_name[]",null,["required","class"=>"form-control","placeholder"=>"Space Name"]) !!}
                </div>
            
                <div class="col-md-4">
                    {!! Form::file("space_image[]",["class"=>"form-control"]) !!}
                </div>
                <div class="col-md-2">
                    {!! Form::select("space_status[]",["active"=>"active","deactive"=>"deactive"],null,["required","class"=>"form-control"]) !!}
                </div>
                <div class="col-md-2">
                    <a href="javascript:;" class="delete-space-data btn btn-danger " ><i class="fa fa-trash"></i> </a>
                </div>
                
                <div class="col-md-12">
                    <br>
                </div>
            </div>
        `;

        $("#space-area-section").append(html);
    });
</script>
@stop