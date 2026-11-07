
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
    <link rel="stylesheet" type="text/css" href="css/style.css">
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
    width: 90%;
    margin: auto !important;
}
.box-inner {
    position: relative;
    padding: 40px 0px 40px 0px;
    border-radius: 5px;
    background-color: #ffffff;
    box-shadow: 0px 0px 10px rgb(0 0 0 / 12%);
}
.default-form {
    position: relative;
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
                <form method="post" autocomplete="off" action="https://floridakeysvillas.com/property-list.php">
                    <div class="row clearfix checkin" style="width: 1224px;">
                        <!-- Form Group -->
                        <div class="form-group form-group1 col-lg-3 col-6 col-md-3 col-sm-6">
                            <input type="text" readonly="" class="form-control" id="txtFrom" name="first1" type="text" placeholder="Check In" required="">
                            <i class="fa fa-calendar" aria-hidden="true"></i>
                        </div>
                        <div class="form-group form-group1 col-lg-3 col-md-3 col-sm-6 col-6">
                            <input type="text" readonly="" class="form-control" id="txtTo" name="last1" required="" placeholder="Check Out" required="">
                            <i class="fa fa-calendar" aria-hidden="true"></i>
                        </div>
                        <!-- Form Group -->
                        <div class="form-group form-group2 col-lg-3 col-md-3 col-sm-12">
                            <div class="guest-input-out">
                                      <div class="guest-input">
                                          <input type="text" readonly="" onclick="setFocusToTextBox()" style="color: #000000a1 !important; font-size: 17px !important" class="guest1" id="result" value="<?php echo (empty($sleep1)?' ':$sleep1);?> Guests" data-toggle="modal" data-target="#myModal" readonly="" aria-label="Guests" placeholder="Guests" name="guest" data-wdio="guest-picker-input" aria-expanded="false">
                                           <input type="hidden" name="totalguest" id="result11">
                                           <span style="display: none;"tabindex="0" id="ui-id-1-button" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-owns="ui-id-1-menu" aria-haspopup="true" class="ui-selectmenu-button ui-selectmenu-button-closed ui-corner-all ui-button ui-widget">
                                           <span class="ui-selectmenu-icon ui-icon ui-icon-triangle-1-s"></span>
                                           <span class="ui-selectmenu-text">Guests</span>
                                           </span>
                                           <i class="fa fa-users" aria-hidden="true"></i>
                                      </div>
                                      
                                      </div>
                                      <div class="adult-popup">
                                          <div class="modal-bodyss" id="guestsss" style="display: none;">
                                               <p class="close1" onclick="myClose()"><i class="fa fa-times"></i></p>
                                               <!-- <p class="p1"></p> -->
                                               <div class="ac-box">
                                                  <div class="adult">
                                                     <p id="number1"> </p>
                                                     <span class="span1">Adult</span>
                                                     <p>(18+)</p>
                                                  </div>
                                                  <div class="btnssss">
                                                     <div class="button button1 btnnn" onclick="dicrementValue()" value="Increment Value">-</div>  
                                                     <div class="button11 button1" onclick="incrementValue()" value="Increment Value">+</div>
                                                  </div>
                                               </div>
                                               <!-- <p class="p2"></p> -->
                                                <div class="ac-box">
                                                  <div class="adult">
                                                     <p id="number2"> </p>
                                                     <span class="span2">Children</span>
                                                     <p>(0-17)</p>
                                                  </div>
                                                  <div class="btnssss btnsss">
                                                     <div class="button button1" onclick="dicrementValue2()" value="Increment Value">-</div> 
                                                     <div class="button11 button1" onclick="incrementValue2()" value="Increment Value">+</div>
                                                  </div>
                                               </div>
                                               <form action="">
                                                  <label class="radio-inline pa" style="">
                                                     <p style="font-size: 14px;margin-top: 7px;display: none;">Pets</p>
                                                  </label>
                                                  <div class="radio-btns">
                                                    <!-- <label class="radio-inline">
                                                     <input type="radio" name="petno" id="no" onclick="myFunction2(this.value)" value="" checked="">
                                                     <span>No</span>
                                                     </label>-->
                                                     <label class="radio-inline">
                                                     <input type="hidden" name="petno" id="yes" onclick="myFunction(this.value)" value="yes">
                                                    <!-- <span>Yes</span>-->
                                                     </label>
                                                     <br>
                                                     <input type="hidden" id="result2" readonly="">
                                                  </div>
                                               </form>
                                               <button type="button" class="btn btn-default" data-dismiss="modal" onclick="myClose()">Apply</button>
                                           </div>
                                      </div>
                        </div>
                       <input type="hidden" name="redirect_url" value="">
                        <div class="form-group form-group2 col-lg-3 col-md-3 col-sm-12">
                            <button type="submit" class="theme-btn btn-style-one" name="homesearch">
                           <span class="txt">Check Availability</span> 
                           <i class="map flaticon-right" style="top: 13px;"></i>
                           </button>
                        </div>
                    </div>
                </form>
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
  <script type="text/javascript">
                               function toUnicode(elmnt,content){
                                   var result=document .getElementById("result");
                                       result.onclick.apply(result);
                                   }
                                   function setFocusToTextBox(){
                                       document.getElementById("result").focus();
                                       var modal = document.getElementById("myModal");
                                   }
                                    function setFocusToTextBox() {
           document.getElementById("guestsss").style.display = "block";
         }
         function myClose() {
           document.getElementById("guestsss").style.display = "none";
         }
                // input 1
         function incrementValue(){
          var value = parseInt(document.getElementById('number1').innerHTML, 10);
          value = isNaN(value) ? 0 : value;
          value++;
          document.getElementById('number1').innerHTML = value;
             result();
         }
         function dicrementValue(){
          var value = parseInt(document.getElementById('number1').innerHTML, 10);
          value = isNaN(value) ? 0 : value;
          value--;
          if(value>-1){
              document.getElementById('number1').innerHTML = value;
          }
          else
          {
              //alert("Value cannot be less than zero!");
          }
             result();
         }
         function incrementValue2(){
          var value = parseInt(document.getElementById('number2').innerHTML, 10);
          value = isNaN(value) ? 0 : value;
          value++;
          document.getElementById('number2').innerHTML = value;
             result();
         }
         function dicrementValue2(){
          var value = parseInt(document.getElementById('number2').innerHTML, 10);
          value = isNaN(value) ? 0 : value;
          value--;
              if(value>-1){
          document.getElementById('number2').innerHTML = value;
              }else{
                   //alert("Value cannot be less than zero!");
              }
          result();
         }
         function result(){
         var value1 = parseInt(document.getElementById('number1').innerHTML, 10);
          var value2 = parseInt(document.getElementById('number2').innerHTML, 10);
           var result=0;
            console.log(value1+"--"+value2);
          if(value1>0 && value2>0){
            result = value1+value2;
            //alert(result);
          }else if(value1>0 && (isNaN(value2) || value2==0)){
            result = value1;
          }else if((isNaN(value1) || value1==0) && value2>0){
               result = value2;
               //alert(result);
          }
           //var result = value1+value2;
           if(document.getElementById('yes').checked)
         {
         var resultString = result+" Guests, Pets";
         document.getElementById('result').value = resultString;
         }
         else
         {
           var resultString = value1+","+value2;
           //console.log(resultString);
             document.getElementById('result').value = result+" Guests";
             document.getElementById('result11').value = resultString;
            /*if(value1>0 && value2>0){
           var resultString = value1+","+value2;   
          }else if(value1>0 && value2==0){
           var resultString = value1;
          }else if(value1==0 && value2>0){
              var resultString = value2;
          }else{
              var resultString=0;
          }*/
         }
         }
                            </script>
    <script>
    // date range picker
    var picker = new Lightpick({
        field: document.getElementById('datepicker'),
        secondField: document.getElementById('datepicker1'),
        numberOfMonths: 2,
        autoclose:true,
    
         // onSelect: function(start, end){
         //     var str = '';
         //     str += start ? start.format('Do MMMM YYYY') + ' to ' : '';
         //     str += end ? end.format('Do MMMM YYYY') : '...';
         //     document.getElementById('result-3').innerHTML = str;
         // }
    });
    // pets open
    $(function () {
              $(".pets-m").hide();
            $("#check").click(function () {
                if ($(this).is(":checked")) {
                    $(".pets-m").show();
                }  
            });
            $("#uncheck").click(function () {
                if ($(this).is(":checked")) {
                    $(".pets-m").hide();
                }  
            });
    });
    // guests count js
    </script>
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
.ui-datepicker .ui-datepicker-next span, .ui-datepicker .ui-datepicker-prev span {
    
    margin-top: 0px;
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
    </body>
    </html>
  