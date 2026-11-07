
<!DOCTYPE html>
<html>
<head>
    <title><?= $data->meta_title;?></title>
    <meta name="description" content="<?= $data->meta_description;?>" />
    <meta name="keywords" content="<?= $data->meta_keywords;?>" />
    <meta name="robots" content="index, follow"/>
    <meta name="coverage" content="Worldwide" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{asset('css/style.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('css/responsive.css')}}">
    <!--<link rel="stylesheet" type="text/css" href="https://floridakeysvillas.com/css1/jquery1-ui.css"> -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    {!! $data->header_section !!}
</head>
<body>

<main>
<?php 
$bannerimage='';
if($data->bannerImage!=""){
    $bannerimage='style="background:url('.asset($data->bannerImage).');    background-size: cover;
    background-position: center;"';
}
?>
<section class="hero-section"  <?= $bannerimage?>>
    <div class="content-area">
    <div class="banner-content">
        <h1 class="font-size"><?= $data->banner_heading;?></h1>
        <p><?= $data->banner_sub_heading;?></p>
        
    </div>

    
</div>

</section>

<style>
.inner-container {
    position: relative;
    margin-top: -132px;
    z-index:2;
    /* padding-left: 6%; */
}
.destination-form-box {
    position: relative;
    width: 80%;
    margin: auto !important;
}
.box-inner {
    position: relative;
    padding: 40px 25px;
    border-radius: 5px;
    background-color: #ffffff;
    box-shadow: 0px 0px 10px rgb(0 0 0 / 12%);
}
.default-form {
    position: relative;
}

.default-form .row{
    display:flex;
    gap:20px;
}


.default-form .form-group.form-group2 .fa-users{
    top: 20px;
    color: #62ade4;
}
.checkin {
    width: 100% !important;
    /* margin-right: -15px!important; */
    margin-left: 0px !important;
    display: flex;
    padding:0px 20px;
    justify-content: center !important;
    gap:20px;
}
.default-form .form-group {
    position: relative;
    display: block;
    margin: 0px 0px;
    min-width: 22%;
    color: #34b9f3;
}
.default-form .form-group.form-group1 input {
    /*height: 32px;*/
    width: 100%;
}
.default-form .form-group i {
    position: absolute;
    top: 18px;
    left: 25px;
    font-size: 18px;
}
.default-form .ui-selectmenu-button.ui-button, .default-form .form-group input, .default-form .form-group select, .default-form .form-group textarea {
    position: relative;
    display: block;
    width: 100%;
    height: 56px;
    font-size: 14px;
    color: #777777;
    line-height: 34px;
    font-weight: 400;
    border-radius: 0px;
    padding: 12px 22px 11px 45px;
    background: #f2f2f2;
    border: 1px solid #e0e0e0;
    -webkit-transition: all 300ms ease;
    -ms-transition: all 300ms ease;
    -o-transition: all 300ms ease;
    -moz-transition: all 300ms ease;
    transition: all 300ms ease;
}
.default-form .form-group button {
    width: 100%;
    cursor: pointer;
}

.default-form .form-group button {
    width: 100%;
    cursor: pointer;
}
button.theme-btn.btn-style-one {
    height: 55px;
    margin-top:0;
}

.default-form input{
    letter-spacing: 0rem;
    text-transform: capitalize;
    height: 56px;
    font-size: 14px;
    color: #777777;
    line-height: 34px;
    font-weight: 400;
    border-radius: 0px;
    padding: 12px 22px 11px 45px;
    background: #f2f2f2;
    border: 1px solid #e0e0e0;
    width:100%;
}
.btn-style-one {
    position: relative;
    display: inline-block;
    font-size: 16px;
    /*line-height: 30px;*/
    color: #ffffff;
    padding: 12px 30px;
    font-weight: 400;
    overflow: hidden;
    overflow: hidden;
    border-radius: 0px;
    background-color: #34b9f3;
    text-transform: capitalize;
}
.btn-style-one:before {
    position: absolute;
    content: '';
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
    opacity: 0;
    background-color: #c29d59a9;
    -webkit-transition: all 0.4s;
    -moz-transition: all 0.4s;
    -o-transition: all 0.4s;
    transition: all 0.4s;
    -webkit-transform: scale(0.2, 1);
    transform: scale(0.2, 1);
}
.btn-style-one:hover:before {
    opacity: 1;
    -webkit-transform: scale(1, 1);
    transform: scale(1, 1);
}
</style>
  
<!-- hero banner section End -->
<div class="inner-container">
    <div class="destination-form-box">
        <div class="box-inner">
            <div class="default-form">
                 <form method="get" action="{{ url('properties') }}"> <div class="row">
               
                <div class="col-lg md-3 mb-lg-0 loct icns position-relative d-none" style="display:none;">

                   
                    {!! Form::select("location_id",ModelHelper::getLocationSelectList(),null,["class"=>"form-select","placeholder"=>"Choose Location"]) !!}

                    <i class="fa fa-map-marker-alt"></i>

                </div> 
                <div class="col-lg md-4 icns mb-lg-0 position-relative">

                    {!! Form::text("start_date",null,["required","autocomplete"=>"off","inputmode"=>"none","id"=>"txtFrom","placeholder"=>"Check in","class"=>"form-control"]) !!}
                    <i class="fa-solid fa-calendar-days"></i>

                </div>

                <div class="col-lg md-4 icns mb-lg-0 position-relative">

                    
                    
                     {!! Form::text("end_date",null,["required","autocomplete"=>"off","inputmode"=>"none","id"=>"txtTo","placeholder"=>"Check Out","class"=>"form-control lst" ]) !!}

                    <i class="fa-solid fa-calendar-days"></i>

                </div> 
                <div class="col-lg md-3 mb-lg-0 loct icns position-relative">
                    
                    <input type="text" name="Guests" readonly class="form-control gst" id="show-target-data" placeholder="Guests">
                    
                    <i class="fa-solid fa-users "></i>
                    <input type="hidden" value="0" name="adults" id="adults-data" />
                    <input type="hidden" value="0" name="child" id="child-data" />
                    <div class="adult-popup">
	                          <div class="modal-bodyss" id="guestsss">
	                          		<p class="close1" onclick=""><i class="fa fa-times"></i></p>
	                               <div class="ac-box">
	                                  <div class="adult">
	                                     <span id="adults-data-show">Adult</span>
	                                     <p>(18+)</p>
	                                  </div>
	                                  <div class="btnssss">
	                                     <div class="button button1 btnnn" onclick="functiondec('#adults-data','#show-target-data','#child-data')" value="Increment Value">-</div>  
	                                     <div class="button11 button1" onclick="functioninc('#adults-data','#show-target-data','#child-data')" value="Increment Value">+</div>
	                                  </div>
	                               </div>
	                                <div class="ac-box">
	                                  <div class="adult">
	                                     <span id="child-data-show">Children</span>
	                                     <p>(0-17)</p>
	                                  </div>
	                                  <div class="btnssss btnsss">
	                                     <div class="button button1" onclick="functiondec('#child-data','#show-target-data','#adults-data')" value="Increment Value">-</div> 
	                                     <div class="button11 button1" onclick="functioninc('#child-data','#show-target-data','#adults-data')" value="Increment Value">+</div>
	                                  </div>
	                               </div>
	                               <button type="button" class="btn main-btn close1" data-dismiss="modal" onclick="">Apply</button>
	                           </div>
	                      </div>

                </div>

                <div class="col-lg md-4 md-lg-0 srch-btn">

                    <button type="submit" class="main-btn ">Check Availability</button>

                </div>
             
            </div>   </form>
            </div>
        </div>
    </div>
    <!-- End Destination Form Box -->
</div>

<section class="property-section">

    <h2><?= $data->vacation_heading;?></h2>
    <p><?= $data->vacation_sub_heading;?></p>

    <div class="property">
        <div class="p1 pro">
            <div class="main-container">
                <div class="image-container">
                    <a href="<?= $data->vacation_one_link;?>" target="_blank">
                        
                    <?php if($data->vacation_one_image!=""):?>
                        <img src="<?= asset($data->vacation_one_image);?>" alt="<?= $data->vacation_one_alt?>" title="<?= $data->vacation_one_alt?>" />     
                    <?php endif;?>
                    </a>
                </div>
    
                <div class="property-content">
                    <h3><a href="<?= $data->vacation_one_link;?>" target="_blank"><?= $data->vacation_one_title;?></a></h3>
                </div>
            </div>
        </div>


        <div class="p2 pro">
            <div class="main-container">
                <div class="image-container">
                    <a href="<?= $data->vacation_two_link;?>" target="_blank">
                        
                    <?php if($data->vacation_two_image!=""):?>
                        <img src="<?= asset($data->vacation_two_image);?>" alt="<?= $data->vacation_two_alt?>" title="<?= $data->vacation_two_alt?>" />     
                    <?php endif;?>
                    </a>
                </div>
    
                <div class="property-content">
                    <h3><a href="<?= $data->vacation_two_link;?>" target="_blank"><?= $data->vacation_two_title;?></a></h3>
                </div>
            </div>
        </div>


        <div class="p3 pro">
            <div class="main-container">
                <div class="image-container">
                    <a href="<?= $data->vacation_three_link;?>" target="_blank">
                        
                    <?php if($data->vacation_three_image!=""):?>
                        <img src="<?= asset($data->vacation_three_image);?>" alt="<?= $data->vacation_three_alt?>" title="<?= $data->vacation_three_alt?>" />     
                    <?php endif;?>
                    </a>
                </div>
    
                <div class="property-content">
                    <h3><a href="<?= $data->vacation_three_link;?>" target="_blank"><?= $data->vacation_three_title;?></a></h3>
                </div>
            </div>
        </div>


        <div class="p4 pro">
            <div class="main-container">
                <div class="image-container">
                    <a href="<?= $data->vacation_four_link;?>" target="_blank">
                        
                    <?php if($data->vacation_four_image!=""):?>
                        <img src="<?= asset($data->vacation_four_image);?>" alt="<?= $data->vacation_four_alt?>" title="<?= $data->vacation_four_alt?>" />     
                    <?php endif;?>
                    </a>
                </div>
    
                <div class="property-content">
                    <h3><a href="<?= $data->vacation_four_link;?>" target="_blank"><?= $data->vacation_four_title;?></a></h3>
                </div>
            </div>
        </div>




    </div>

</section>


<section class="about-section">
 @if($data->attraction_secion!="")
                @php $c=100;
                $attractions=json_decode($data->attraction_secion,true);
                @endphp
          
            <?php $sno=1; foreach($attractions as $key=>$cat):?>
            <?php if($sno%2==1):?>
                <div class="about-main main1">
            
                    <div class="about-content">
                        <?php if($cat['attraction_image']){
                                if($cat['attraction_image']!=""){?>
                            <img src="<?= asset($cat['attraction_image'])?>" class="about" alt="{{ $cat['attraction_title'] ?? $data->name }}" title="{{ $cat['attraction_title'] ?? $data->name }}" />        
                        <?php }
                        }?> 
                
                        <h2><?= $cat['attraction_heading']?></h2>
                        <p><?= $cat['attraction_content']?></p>
                    </div>
                
             
                    </div>
            
                </div>
            <?php else:?>
                <div class="about-main main2">
            
                    <div class="about-content">
                         <?php if($cat['attraction_image']){
                                if($cat['attraction_image']!=""){?>
                            <img src="<?= asset($cat['attraction_image'])?>" class="about"  alt="{{ $cat['attraction_title'] ?? $data->name }}" title="{{ $cat['attraction_title'] ?? $data->name }}" />        
                        <?php }
                        }?> 
                
                        <h2><?= $cat['attraction_heading']?></h2>
                        <p><?= $cat['attraction_content']?></p>
                    </div>
                </div>
            <?php endif;?>
            <?php $sno++; endforeach;?>
@endif
</section>





<section class="about-section">
@if($data->video_section!="")
                @php $c=100;
                $attractions=json_decode($data->video_section,true);
                @endphp
            <?php $sno=1; foreach($attractions as $key=>$cat):?>
            <?php if($sno%2==1):?>
                <div class="about-main main1">
            
                    <div class="about-content vd">
                        <?php if($cat['video_link']){
                                if($cat['video_link']!=""){
                                $ar_you=str_replace("https://youtu.be/","",$cat['video_link']);
                                ?>
                            <iframe width="100%" height="400px" src="https://www.youtube.com/embed/<?= $ar_you?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                        <?php }
                        }?> 
                
                        <h2><?= $cat['video_heading']?></h2>
                        <?= $cat['video_content']?>
                    </div>
                
                    <div class="about-image">
                    
                       
                    </div>
            
                </div>
            <?php else:?>
                <div class="about-main main2">
            
                    <div class="about-content vd">
                        <?php if($cat['video_link']){
                                if($cat['video_link']!=""){
                                $ar_you=str_replace("https://youtu.be/","",$cat['video_link']);
                                ?>
                            <iframe width="100%" height="300" src="https://www.youtube.com/embed/<?= $ar_you?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                        <?php }
                        }?> 
                
                        <h2><?= $cat['video_heading']?></h2>
                        <?= $cat['video_content']?>
                    </div>
                
                    <div class="about-image">
                        
                    </div>
            
                </div>
            <?php endif;?>
            <?php $sno++; endforeach;?>

            @endif

</section>
<section class="bottom-section">

    <div class="bottom">
        <div class="head">
        <h3><?= $data->description?></h3>
        </div>

        <div class="content">
            <?= $data->longDescription?>
        </div>
    </div>
    
</section>

</main>
  <script type="text/javascript" src="https://floridakeysvillas.com/js/jquery.min.js"></script>
  <script>
     $(function() {

        var $quote = $(".font-size");
        
        var $numWords = $quote.text().split(" ").length;
        
        if (($numWords >= 1) && ($numWords < 10)) {
            $quote.css("font-size", "60px");
        }
        else if (($numWords >= 10) && ($numWords < 20)) {
            $quote.css("font-size", "50px");
        }
        else if (($numWords >= 20) && ($numWords < 30)) {
            $quote.css("font-size", "40px");
        }
        else if (($numWords >= 30) && ($numWords < 40)) {
            $quote.css("font-size", "35px");
        }
        else {
            $quote.css("font-size", "30px");
        }    
        
    });
  </script>
    <script type="text/javascript" src="https://floridakeysvillas.com/js/jquery.fancybox.min.js"></script>
    <script type="text/javascript" src="https://floridakeysvillas.com/js/main.js"></script>

    <script src="https://floridakeysvillas.com/js/jquery-ui.js"></script>
<!--<link rel="stylesheet" href="https://floridakeysvillas.com/updateical.php"/>-->
<link rel="stylesheet" type="text/css" href="https://floridakeysvillas.com/css1/jquery1-ui.css"> 

<style>
.ui-datepicker-unselectable.ui-state-disabled.undefined .ui-state-default {
    position: relative;
    opacity: 1;
}
.ui-state-default, .ui-widget-content .ui-state-default, .ui-widget-header .ui-state-default {
    
    color: #212529;
}
.ui-state-hover, .ui-widget-content .ui-state-hover, .ui-widget-header .ui-state-hover, .ui-state-focus, .ui-widget-content .ui-state-focus, .ui-widget-header .ui-state-focus {
    border: 1px solid rgb(235, 235, 235);
    background: #00bfff url(images/ui-bg_glass_100_fdf5ce_1x400.png) 50% 50% repeat-x;
    font-weight: bold;
    color: #212529;
}
.ui-datepicker-next.ui-corner-all.ui-state-hover.ui-datepicker-next-hover, .ui-datepicker-prev.ui-corner-all.ui-state-hover.ui-datepicker-prev-hover {
    border: 0px solid rgb(235, 235, 235);
    background: none;
    font-weight: bold;
    color: transparent;
}

</style>
<style>
.ui-datepicker {
    width: 28em;
    padding: 0;
    display: none;
}
</style>

<style>.card.experiences {
    border-radius: 7px 7px 10px 10px;
    border-bottom: 2px solid #34b9f3;
    overflow: hidden;
}
 

.card.experiences1 img {
    transform: scale(.8);
    transition: .5s;
}

.card.experiences1:hover img {
    transform: scale(1);
}</style>

{!! $data->footer_section !!}

   <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" ></script>

<!-- main js -->
<script type="text/javascript" src="https://www.floridakeysvillas.com/front/js/main.js"></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js'></script>
<script src='https://cdn.jsdelivr.net/jquery.slick/1.6.0/slick.min.js'></script>
      <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>

    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js"></script>
<script src="https://www.floridakeysvillas.com/front/js/functions.js"></script>


<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<script src="https://www.floridakeysvillas.com/toastr/toastr.js"></script>

<script>
    function functiondec($getter_setter,$show,$cal){
        val=parseInt($($getter_setter).val());
        if(val>0){
            val=val-1;
        }
        $($getter_setter).val(val);
        person1=val;
        person2=parseInt($($cal).val());
        $show_data=person1+person2;
        $show_actual_data=$show_data+" Guests";
        if($getter_setter=="#adults-data"){
            $($getter_setter+'-show').html(val +" Adults");
            if(val<=1){
               $($getter_setter+'-show').html(val +" Adult"); 
            }
        }else{
             $($getter_setter+'-show').html(val +" Children");
            if(val<=1){
               $($getter_setter+'-show').html(val +" Child"); 
            }
        }
        $($show).val($show_actual_data);
    }
    function functioninc($getter_setter,$show,$cal){
        val=parseInt($($getter_setter).val());
        
            val=val+1;
      
        $($getter_setter).val(val);
        person1=val;
        person2=parseInt($($cal).val());
        $show_data=person1+person2;
        $show_actual_data=$show_data+" Guests";
        $($show).val($show_actual_data);
        if($getter_setter=="#adults-data"){
            $($getter_setter+'-show').html(val +" Adults");
            if(val<=1){
               $($getter_setter+'-show').html(val +" Adult"); 
            }
        }else{
             $($getter_setter+'-show').html(val +" Children");
            if(val<=1){
               $($getter_setter+'-show').html(val +" Child"); 
            }
        }
    }
</script>

@php
$new_data_blocked=LiveCart::iCalDataCheckInCheckOut(0);
    $checkin=$new_data_blocked['checkin'];
    
    $checkout=$new_data_blocked['checkout'];

@endphp
<script type="text/javascript">
    var checkin = <?php echo json_encode($checkin);  ?>;
    var checkout = <?php echo json_encode($checkout);  ?>;
    $(function() {
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
                $.post("{{route('checkajax-get-quote')}}",{start_date:$("#txtFrom").val(),end_date:$("#txtTo").val(),book_sub:true,property_id:1},function(data){
                    if(data.status==400){
                       // $("#submit-button-gaurav-data").hide();
                      //  toastr.error(data.message);
                    }else{
                        // $("#submit-button-gaurav-data").show();
                        // $("#gaurav-new-modal-days-area").html(data.modal_day_view);
                        // $("#gaurav-new-modal-service-area").html(data.modal_service_view);
                        // $("#gaurav-new-data-area").html(data.data_view);
                    }
                })

            },
            onClose: function() {
                $('.popover-1').addClass('opened');
            }
        });
    });
    
    $(document).ready(function(){
  $(".gst").click(function(){
    $("#guestsss").css("display", "block");
  });
   $(".close1").click(function(){
    $("#guestsss").css("display", "none");
  });
});
</script>




    </body>
    </html>
  