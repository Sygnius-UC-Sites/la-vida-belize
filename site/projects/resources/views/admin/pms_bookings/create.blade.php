@extends('admin.layouts')
@section('title', 'Admin')
@php 
    $name="Booking Enquiries";$route="pms-bookings";
@endphp            
@section('content_header')
    <h1 class="m-0 text-dark">{{$name}} Management</h1>
@stop

@section('content')
 <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    @parent
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-12">
          @php 
            $addbar=["name"=>$name,"add-data"=>false,"add-name"=>"Add ". Str::singular($name),"add-anchor"=>route('admin.'.$route.'.create')];
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
               {!! Form::open(['route' => $route.'.store',"files"=>"true"]) !!}
     
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
 <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
 <script>
   var checkin = '';
    var checkout = '';
     $(document).on("change","#property-selector",function(){
        data={_token:"{{ csrf_token() }}",id:$(this).val()};
        url="{{ route('get-checkin-checkout-data-gaurav') }}";
        $.post(url,data,function(data){
           console.log(data); 
              checkin = data.checkin;
             checkout = data.checkout;
            
                $("#txtFrom").datepicker({
                    numberOfMonths: 1,
                    minDate: '@minDate',
                    dateFormat: 'yy-mm-dd',
                    beforeShowDay: function(date) {
                        var string = jQuery.datepicker.formatDate('yy-mm-dd', date);
                        return [checkin.indexOf(string) == -1];
        
                    },
        
                    onSelect: function(selected) {
                        $("#submit-button-gaurav-data").hide();
                        var dt = new Date(selected);
                        dt.setDate(dt.getDate() + 1);
                        $("#txtTo").datepicker("option", "minDate", dt);
                        $("#txtTo").val('');
                    },
                    onClose: function() {
                        $("#txtTo").datepicker("show");
                    }
                });
        
                $("#txtTo").datepicker({
                    numberOfMonths: 1,
                    dateFormat: 'yy-mm-dd', 
                    beforeShowDay: function(date) {
        
                        var string = jQuery.datepicker.formatDate('yy-mm-dd', date);
        
                        return [checkout.indexOf(string) == -1]
        
                    },
        
                    onSelect: function(selected) {
                        var dt = new Date(selected);
                        dt.setDate(dt.getDate() - 1);
                        $("#txtFrom").datepicker("option", "maxDate", dt);
                       ajaxCallingData();
        
                    },
                    onClose: function() {
                        $('.popover-1').addClass('opened');
                    }
                });
    
        });
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
                             $.post("{{route('admin-checkajax-get-quote')}}",{start_date:$("#txtFrom").val(),end_date:$("#txtTo").val(),pet_fee_data_guarav:pet_fee_data_guarav,heating_pool_fee_data_guarav:$("#heating_pool_fee_data_guarav").val(),adults:adults,childs:childs,book_sub:true,property_id:$("#property-selector").val(),extra_discount:$("#extra-discount").val()},function(data){
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
    
    $(document).on("keyup","#extra-discount",function(){
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
 </script>
 
@stop