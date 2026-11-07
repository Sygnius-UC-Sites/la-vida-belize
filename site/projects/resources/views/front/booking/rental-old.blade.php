@extends("front.layouts.shared-master")
@section("title",$data->meta_title)
@section("keywords",$data->meta_keywords)
@section("description",$data->meta_description)

@section("container")

    @php
        $name=$data->name;
        $bannerImage=asset('front/images/internal-banner.webp');
          $payment_currency= $setting_data['payment_currency'];
    @endphp
	<!-- start banner sec -->
    
@include("front.layouts.banner")

	<!-- start about section -->
        
      <!-- About Section -->
 
      <section class="About-sec">

        <div class="container">

            <div class="row" style="margin-left: 0px;
    margin-right: 0px;">

                
              
                            <div class="table-box" style='margin-bottom:20px; padding-left:0px; padding-right:0px;'>
                            <table align="left" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tbody>
                                    <tr>
                                        <th colspan="2" align="center" style="padding: 10px;" valign="top"><strong>Property Detail </strong></th>
                                    </tr>

                                    <tr>
                                        <td align="left" style="padding: 10px;" valign="top"><strong>Property Name :</strong></td>
                                        <td align="left" style="padding: 10px;" valign="top">{{$property->name }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                             <div class="table-box" style='padding-left:0px; padding-right:0px;'>
                            <table align="left" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tbody>
                                    <tr>
                                        <th colspan="5" align="center" style="padding: 10px;" valign="top"><strong>Booking Detail </strong></th>
                                    </tr>

                                    <tr>
                                        <th align="left" style="padding: 10px;" valign="top"><strong>Checkin :</strong></th>
                                        <th align="left" style="padding: 10px;" valign="top"><strong>Checkout :</strong></th>
                                        <th align="left" style="padding: 10px;" valign="top"><strong>Total Guest :</strong></th>
                                        <th align="left" style="padding: 10px;" valign="top"><strong>Total Night :</strong></th>
                                        <th align="center" style="padding: 10px;" valign="top"><strong>Gross Amount :</strong></th>
                                        
                                    </tr>
                                    <tr>
                                        <td align="left" style="padding: 10px;" valign="top">{{$booking['checkin'] }}</td>
                                        <td align="left" style="padding: 10px;" valign="top">{{$booking['checkout'] }}</td>
                                        <td align="left" style="padding: 10px;" valign="top">{{$booking['total_guests'] }} ({{$booking['adults']}} Adults, {{$booking['child']}} Child)</td>
                                        <td align="left" style="padding: 10px;" valign="top">{{$booking['total_night'] }}</td>
                                        <td align="center" style="padding: 10px;" valign="top">{!! $payment_currency !!}{{number_format($booking['gross_amount'],2) }}</td>
                                    </tr>
                                    
                                    
                                    	@if($booking['rest_guests'])
								    @if($booking['rest_guests']>0)
								        @if($booking['guest_fee'])
								            @if($booking['guest_fee']>0)
								            <tr>
            									<td align="left" colspan="4" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #6c3e79; border-right:0px solid #6c3e79; border-bottom:0px solid #6c3e79;" valign="top"><strong> Additional Guest Fee <br> <span style="font-size:13px;">({{$booking['total_night']}} nights * {!! $setting_data['payment_currency'] !!}{{$booking['single_guest_fee']}} * {{$booking['rest_guests']}} Guests)</span></strong></td>
            									<td align="center" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #6c3e79; border-bottom:0px solid #6c3e79;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($booking['guest_fee'],2) }}</td>
            								</tr>
								            @endif
								        @endif
								    @endif
								@endif
								
											
								@if($booking['pet_fee_type']=="taxable")
    								@if($booking['total_pets'])
    								    @if($booking['total_pets']>0)
    								        @if($booking['pet_fee'])
    								            @if($booking['pet_fee']>0)
    								            <tr>
                									<td align="left" colspan="4" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #6c3e79; border-right:0px solid #6c3e79; border-bottom:0px solid #6c3e79;" valign="top"><strong>Pet Fee :</strong></td>
                									<td align="center" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #6c3e79; border-bottom:0px solid #6c3e79;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($booking['pet_fee'],2) }}</td>
                								</tr>
    								            @endif
    								        @endif
    								    @endif
    								@endif
								@endif
								
								@if($booking['heating_pool_fee_type']=="taxable")
    								@if($booking['heating_pool_fee'])
    								    @if($booking['heating_pool_fee']>0)
    								         <tr>
                									<td align="left" colspan="4" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #6c3e79; border-right:0px solid #6c3e79; border-bottom:0px solid #6c3e79;" valign="top"><strong>Heating the Swimming Pool fee :</strong></td>
                									<td align="center" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #6c3e79; border-bottom:0px solid #6c3e79;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($booking['heating_pool_fee'],2) }}</td>
                								</tr>
    								    @endif
    								@endif
								@endif
                                    
                                    
                                    @foreach(json_decode($booking['before_total_fees']) ?: [] as $c)
                                    <tr>
                                        <td align="left" colspan="4" style="padding: 10px;" valign="top"><strong>{{$c->name}} :</strong></td>
                                        <td align="center" style="padding: 10px;" valign="top">{!! $payment_currency !!}{{number_format($c->amount,2) }}</td>
                                    </tr>
                                    @endforeach
                                   
                                    
                                    @if($booking['tax'])
                             
                                        <tr>
                                            <td align="left" colspan="4" style="padding: 10px;" valign="top"><strong>Tax ({{ $booking['define_tax'] ?? '' }}%): :</strong></td>
                                            <td align="center" style="padding: 10px;" valign="top">{!! $payment_currency !!}{{number_format($booking['tax'],2) }}</td>
                                        </tr>
                                  
                                    @endif
                                    
                                    
                                     @if($booking['sub_amount']!=$booking['gross_amount'])
                                        @if(count(json_decode($booking['after_total_fees']))>0)
                                        <tr>
                                            <td align="left" colspan="4" style="padding: 10px;" valign="top"><strong>Sub Total :</strong></td>
                                            <td align="center" style="padding: 10px;" valign="top">{!! $payment_currency !!}{{number_format($booking['sub_amount'],2) }}</td>
                                        </tr>
                                        @endif
                                    @endif
                                    
                                    
                                    
                                    	
								@if($booking['pet_fee_type']!="taxable")
    								@if($booking['total_pets'])
    								    @if($booking['total_pets']>0)
    								        @if($booking['pet_fee'])
    								            @if($booking['pet_fee']>0)
    								            <tr>
                									<td align="left" colspan="4" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #6c3e79; border-right:0px solid #6c3e79; border-bottom:0px solid #6c3e79;" valign="top"><strong>Pet Fee :</strong></td>
                									<td align="center" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #6c3e79; border-bottom:0px solid #6c3e79;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($booking['pet_fee'],2) }}</td>
                								</tr>
    								            @endif
    								        @endif
    								    @endif
    								@endif
								@endif
								
								@if($booking['heating_pool_fee_type']!="taxable")
    								@if($booking['heating_pool_fee'])
    								    @if($booking['heating_pool_fee']>0)
    								         <tr>
                									<td align="left" colspan="4" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #6c3e79; border-right:0px solid #6c3e79; border-bottom:0px solid #6c3e79;" valign="top"><strong>Heating the Swimming Pool fee :</strong></td>
                									<td align="center" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #6c3e79; border-bottom:0px solid #6c3e79;" valign="top">{!! $setting_data['payment_currency'] !!}{{number_format($booking['heating_pool_fee'],2) }}</td>
                								</tr>
    								    @endif
    								@endif
								@endif
                                    
                                    @foreach(json_decode($booking['after_total_fees']) ?: [] as $c)
                                    <tr>
                                        <td align="left" colspan="4" style="padding: 10px;" valign="top"><strong>{{$c->name}} :</strong></td>
                                        <td align="center" style="padding: 10px;" valign="top">{!! $payment_currency !!}{{number_format($c->amount,2) }}</td>
                                    </tr>
                                    @endforeach
                                    
                                    <tr>
                                        <td align="left" colspan="4" style="padding: 10px;" valign="top"><strong>Total :</strong></td>
                                        <td align="center" style="padding: 10px;" valign="top">{!! $payment_currency !!}{{number_format($booking['total_amount'],2) }}</td>
                                    </tr>
                        
                                    
                                    	@php $gaurav_discount=0;@endphp
							    @if($booking['discount'])
                                    @if($booking['discount']!="")
                                        @if($booking['discount']!=0)
                                               @php $gaurav_discount=1;@endphp 
                                        <tr>
                                            <td align="left" colspan="4" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #6c3e79; border-right:0px solid #6c3e79 border-bottom:0px solid #6c3e79;;" valign="top"><strong>Discount ({{ $booking['discount_coupon'] }}):</strong></td>
                                            <td align="center" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #6c3e79; border-bottom:0px solid #6c3e79;" valign="top">- {!! $setting_data['payment_currency']  !!}{{number_format($booking['discount'],2) }}</td>
                                        </tr>
                                      
                                        @endif
                                    @endif
                                @endif
							    @if($booking['extra_discount'])
                                    @if($booking['extra_discount']!="")
                                        @if($booking['extra_discount']>0)
                                               @php $gaurav_discount=1;@endphp 
                                        <tr>
                                            <td align="left" colspan="4" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #6c3e79; border-right:0px solid #6c3e79 border-bottom:0px solid #6c3e79;;" valign="top"><strong>Extra Discount :</strong></td>
                                            <td align="center" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #6c3e79; border-bottom:0px solid #6c3e79;" valign="top">- {!! $setting_data['payment_currency']  !!}{{number_format($booking['extra_discount'],2) }}</td>
                                        </tr>
                                      
                                        @endif
                                    @endif
                                @endif
								@if($gaurav_discount==1)
								    <tr>
                                        <td align="left" colspan="4" style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #6c3e79; border-right:0px solid #6c3e79 border-bottom:0px solid #6c3e79;;" valign="top"><strong>Total Amount after Discount:</strong></td>
                                        <td align="center"  style="padding: 10px; font-weight: bold; font-size: 15px; color:#000; border: 1px solid #6c3e79; border-bottom:0px solid #6c3e79;" valign="top">{!! $setting_data['payment_currency']  !!}{{number_format($booking['after_discount_total'],2) }}</td>
                                    </tr>
								@endif
                                    
                                    @if($booking['amount_data'])
                                        @php
                                            $amount_data=json_decode($booking['amount_data'],true);
                                        @endphp
                                        @if(is_array($amount_data))
                                            @foreach($amount_data as $c)
                                                @php $status='';@endphp
                                                @if(isset($c['status']))
                                                    @php $status='(<span style="color:green;">Paid</span>)'; @endphp
                                                @endif
                                            <tr>
                                                <td align="left" colspan="4" style="padding: 10px;" valign="top"><strong>{{$c['message']}} {!! $status !!}:</strong></td>
                                                <td align="center" style="padding: 10px;" valign="top">{!! $payment_currency !!}{{number_format($c['amount'],2) }}</td>
                                            </tr>
                                            @endforeach
                                        @endif
                                    @endif



                                </tbody>
                            </table>
                            </div>


{!! Form::open(["route"=>"rental-aggrement-data-save","files"=>true,"onsubmit"=>"return checkSignature()"]) !!}
<input type="hidden" name="booking_id" value="{{ $booking['id'] }}">
                <p class="card-title text-center mb-5 rounded" style='margin-top:30px; background: var(--secondary-color);
    color: #fff;
    text-align: center;
    font-size: 15px;
    font-weight:bold;
    padding: 10px;'>Read Rental Agreement</p>
                <fieldset>
                  <div class="col-md-8-offset-2">
                    @if($property->rental_aggrement_attachment)
                       <iframe id="iframepdf" src="{{asset($property->rental_aggrement_attachment)}}" frameborder="1" scrolling="auto" height="500" width="100%" style="border:1px solid #666CCC"></iframe>
                    @endif
                  </div>
                  <div class="col-md-8-offset-2">
                    <div class="even-1 boxSpacingSet8" colspan="2" style="position: relative;margin-left: auto;padding: 0;padding-top: 15px;">
                      <input type="checkbox" id="agree" name="agree" required >
                       <strong>I have read and agree to the Terms & Conditions.</strong> &emsp;</div>
                  </div>
                </fieldset>
                <hr>
                
  
      
                    <!-- Content -->
                    <div class="container" style="padding: 0px;">
                        <div class="row">
                            <div class="col-md-12">
                                <h1>E-Signature</h1>
                                <p>save your signature as an image!</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <canvas id="sig-canvas" width="450px" height="160">
                                    Get a better browser, bro.
                                </canvas>
                            </div>
                            <div class="col-md-6">
                                <h1>Please upload valid identity proof</h1>
                                <input type="file" name="image" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <button class="main-btn" id="sig-submitBtn">Pay Now</button>
                                <button class="main-btn" id="sig-clearBtn">Clear Signature</button>
                            </div>
                        </div>
                        <br/>
                        <div class="row d-none">
                            <div class="col-md-12">
                                <textarea id="sig-dataUrl" name="signature" class="form-control" rows="5">Data URL for your signature will go here!</textarea>
                            </div>
                        </div>
                        <br/>
                        <div class="row d-none">
                            <div class="col-md-12">
                                <img id="sig-image" src="" alt="Your signature will go here!"/>
                            </div>
                        </div>
                    </div>

              {!! Form::close() !!}
                

            </div>

        </div>

    </section>

    

@stop
@section("js")
<script>

    function checkSignature(){
        var canvas = document.getElementById('sig-canvas');
            if (isCanvasEmpty(canvas)){
                toastr.error('Please enter signature!');
                return false;
            } else {
                
                return true;
            }
    }


    function isCanvasEmpty(canvas) {
        const blankCanvas = document.createElement('canvas');
        blankCanvas.width = canvas.width;
        blankCanvas.height = canvas.height;
        return canvas.toDataURL() === blankCanvas.toDataURL();
    }
(function() {
  window.requestAnimFrame = (function(callback) {
    return window.requestAnimationFrame ||
      window.webkitRequestAnimationFrame ||
      window.mozRequestAnimationFrame ||
      window.oRequestAnimationFrame ||
      window.msRequestAnimaitonFrame ||
      function(callback) {
        window.setTimeout(callback, 1000 / 60);
      };
  })();

  var canvas = document.getElementById("sig-canvas");
  var ctx = canvas.getContext("2d");
  ctx.strokeStyle = "#222222";
  ctx.lineWidth = 4;

  var drawing = false;
  var mousePos = {
    x: 0,
    y: 0
  };
  var lastPos = mousePos;

  canvas.addEventListener("mousedown", function(e) {
    drawing = true;
    lastPos = getMousePos(canvas, e);
  }, false);

  canvas.addEventListener("mouseup", function(e) {
    drawing = false;
  }, false);

  canvas.addEventListener("mousemove", function(e) {
    mousePos = getMousePos(canvas, e);
  }, false);

  // Add touch event support for mobile
  canvas.addEventListener("touchstart", function(e) {

  }, false);

  canvas.addEventListener("touchmove", function(e) {
    var touch = e.touches[0];
    var me = new MouseEvent("mousemove", {
      clientX: touch.clientX,
      clientY: touch.clientY
    });
    canvas.dispatchEvent(me);
  }, false);

  canvas.addEventListener("touchstart", function(e) {
    mousePos = getTouchPos(canvas, e);
    var touch = e.touches[0];
    var me = new MouseEvent("mousedown", {
      clientX: touch.clientX,
      clientY: touch.clientY
    });
    canvas.dispatchEvent(me);
  }, false);

  canvas.addEventListener("touchend", function(e) {
    var me = new MouseEvent("mouseup", {});
    canvas.dispatchEvent(me);
  }, false);

  function getMousePos(canvasDom, mouseEvent) {
    var rect = canvasDom.getBoundingClientRect();
    return {
      x: mouseEvent.clientX - rect.left,
      y: mouseEvent.clientY - rect.top
    }
  }

  function getTouchPos(canvasDom, touchEvent) {
    var rect = canvasDom.getBoundingClientRect();
    return {
      x: touchEvent.touches[0].clientX - rect.left,
      y: touchEvent.touches[0].clientY - rect.top
    }
  }

  function renderCanvas() {
    if (drawing) {
      ctx.moveTo(lastPos.x, lastPos.y);
      ctx.lineTo(mousePos.x, mousePos.y);
      ctx.stroke();
      lastPos = mousePos;
    }
  }

  // Prevent scrolling when touching the canvas
  document.body.addEventListener("touchstart", function(e) {
    if (e.target == canvas) {
      e.preventDefault();
    }
  }, false);
  document.body.addEventListener("touchend", function(e) {
    if (e.target == canvas) {
      e.preventDefault();
    }
  }, false);
  document.body.addEventListener("touchmove", function(e) {
    if (e.target == canvas) {
      e.preventDefault();
    }
  }, false);

  (function drawLoop() {
    requestAnimFrame(drawLoop);
    renderCanvas();
  })();

  function clearCanvas() {
    canvas.width = canvas.width;
  }

  // Set up the UI
  var sigText = document.getElementById("sig-dataUrl");
  var sigImage = document.getElementById("sig-image");
  var clearBtn = document.getElementById("sig-clearBtn");
  var submitBtn = document.getElementById("sig-submitBtn");
  clearBtn.addEventListener("click", function(e) {
    clearCanvas();
    sigText.innerHTML = "Data URL for your signature will go here!";
    sigImage.setAttribute("src", "");
  }, false);
  submitBtn.addEventListener("click", function(e) {
    var dataUrl = canvas.toDataURL();
    sigText.innerHTML = dataUrl;
    sigImage.setAttribute("src", dataUrl);
  }, false);

})();
</script>
@stop
@section("css")
<style>
#sig-canvas {
  border: 2px dotted #CCCCCC;
  border-radius: 15px;
  cursor: crosshair;
}
h1{
    color:#000;
    font-size:20px;
    font-weight:bold;
    font-family:'Poppins', sans-serif;
    line-height: 1.25rem;
    margin-bottom: 10px;
    margin-top: 30px;
}
</style>
@stop