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
              <h3 class="card-title">Edit {{ Str::singular($name) }}</h3>
               <div class="card-tools">
                  <button type="button" class="btn btn-tool" data-card-widget="maximize"><i class="fas fa-expand"></i>
                  </button>
              </div>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
               {!! Form::model($data,['route' => [$route.'.update',$data->id],"files"=>"true","method"=>"put","id"=>"web-form-data"]) !!}

                    @include("admin.".$route.".edit-form")

                    {{-- Phase 13H — optional re-send of lifecycle emails after edit save --}}
                    <div class="card mt-4" style="border:1px solid #e6e8ee;background:#f7f8fb;">
                        <div class="card-header" style="background:#fff;font-weight:600;">📬 Re-send emails after saving (optional)</div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">If your edits affect details that the guest already received (dates, room, etc.), tick the email types you want to re-send. They'll fire after the save completes, using the latest values.</p>
                            <div class="form-row">
                                <div class="col-md-3"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="re_welcome" name="resend_emails[]" value="welcome"><label class="custom-control-label" for="re_welcome">🎁 Welcome package</label></div></div>
                                <div class="col-md-3"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="re_checkin" name="resend_emails[]" value="checkin"><label class="custom-control-label" for="re_checkin">🔑 Check-in info</label></div></div>
                                <div class="col-md-3"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="re_checkout" name="resend_emails[]" value="checkout"><label class="custom-control-label" for="re_checkout">🚪 Check-out info</label></div></div>
                                <div class="col-md-3"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="re_review" name="resend_emails[]" value="review"><label class="custom-control-label" for="re_review">⭐ Review request</label></div></div>
                            </div>
                            <div class="form-row mt-2">
                                @if(\App\Helper\FeatureFlags::isDeveloper() || \App\Helper\FeatureFlags::enabled('magic_link_payments'))
                                <div class="col-md-6"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="re_awaiting" name="resend_emails[]" value="awaiting-payment"><label class="custom-control-label" for="re_awaiting">💸 Awaiting-payment magic link</label></div></div>
                                @endif
                                <div class="col-md-6"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="re_cancel" name="resend_emails[]" value="cancellation"><label class="custom-control-label" for="re_cancel">🚫 Cancellation notice</label></div></div>
                            </div>
                        </div>
                    </div>

                    <button class="btn btn-success mt-3"><span class="fa fa-save"></span> Update</button>

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
                                 url='{{route("admin-checkajax-get-quote-edit")}}?property_id:'+$("#property-selector").val();
                                data={
                                    start_date:$("#txtFrom").val(),
                                    end_date:$("#txtTo").val(),
                                    pet_fee_data_guarav:pet_fee_data_guarav,
                                    adults:adults,
                                    childs:childs,
                                    book_sub:true,
                                    property_id:$("#property-selector").val(),
                                    extra_discount:$("#extra-discount").val(),
                                    coupon_discount:$("#coupon_discount").val(),
                                    coupon_discount_code:$("#coupon_discount_code").val()
                                };
                                data=$("#web-form-data").serialize();
                                 $.post(url,data,function(data){
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