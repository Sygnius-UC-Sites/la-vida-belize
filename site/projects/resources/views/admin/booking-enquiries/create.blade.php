@extends('admin.layouts')
@section('title', 'Admin')
@php 
    $name="Booking Enquiries";$route="booking-enquiries";
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
            $addbar=["name"=>$name,"add-data"=>false,"add-name"=>"Add ". Str::singular($name),"add-anchor"=>route($route.'.create'),"back-anchor"=>route($route.'.index')];
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
               {!! Form::open(['route' => $route.'.store',"files"=>"true","id"=>"web-form-data"]) !!}
     
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

@section("js")
@parent
 <script>
    $(document).on("change", "#txtFrom, #txtTo", function() {
        if ($("#txtFrom").val() && $("#txtTo").val()) {
            ajaxCallingData();
        }
    });


    function ajaxCallingData(){
        if($("#booking-selector").val()=="invoice"){
            pet_fee_data_guarav=$("#pet_data").val();
            adults=$("#adult_data").val();
            childs=$("#child_data").val();
            if($("#property-selector").val()!=""){
                 if($("#txtFrom").val()!=""){
                     if($("#txtTo").val()!=""){
                        if($("#adult_data").val()>0){
                              data=$("#web-form-data").serialize();
                             $.post("{{route('admin-checkajax-get-quote')}}",data,function(data){
                                if(data.status==400){
                                    $("#submit-button-gaurav-data").hide();
                                    toastr.error(data.message);
                                }else{
                                   $("#pricedata-gaurav").html(data.data_view);
                                }
                            });
                        }
                     }
                 }  
            }
        }
    }
    $(document).on("change","#adult_data,#child_data,#pet_data,#heating_pool_fee_data_guarav",function(){
        ajaxCallingData();
    });
    
     
    $(document).on("keyup","#extra-discount,.product_name,.product_amount",function(){
        ajaxCallingData();
    });

    
    $(document).on("change","#booking-selector",function(){
        if($("#booking-selector").val()=="invoice"){
            $("#gaurav-data-new-logic").removeClass("d-none");
        }else{
            $("#gaurav-data-new-logic").addClass("d-none");
            $("#pricedata-gaurav").html(null);
        }
    });
     
   
    $(document).on("click",".delete-space-data",function(){
        $(this).parents(".gaurav-delete-property-space").remove();
    });
    $(document).on("click",".add-space-data",function(){
        html=`
            <div class="row gaurav-delete-property-space">
                
                <div class="col-md-1">
                    <a href="javascript:;" class="delete-space-data btn btn-danger btn-xs" ><i class="fa fa-times"></i> </a>
                </div>
                <div class="col-md-8">
                    {!! Form::text("product_name[]",null,["required","class"=>"form-control product_name","placeholder"=>"Name"]) !!}
                </div>
                
                <div class="col-md-3">
                    {!! Form::text("product_amount[]",null,["class"=>"form-control product_amount","required","placeholder"=>"Amount"]) !!}
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