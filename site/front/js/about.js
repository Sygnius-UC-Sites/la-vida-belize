$(".show-data-0").click(function(){
    $(".para-0").css("height", "auto");
    $(".show-data-0").css("display", "none");
    $(".showless-data-0").css("display", "flex");
  });
  
   $(".showless-data-0").click(function(){
    $(".para-0").css("height", "270px");
    $(".show-data-0").css("display", "flex");
    $(".showless-data-0").css("display", "none");
  });
  
   var b = $(".para-0").height();
   if(b < 265){
$(".show-data-0").css("display", "none");
     $(".showless-data-0").css("display", "none");
}
else{
    $(".show-data-0").css("display", "flex");
    $(".para-0").css("height", "270px");
  $(".showless-data-0").css("display", "none");
}
$(".show-data-1").click(function(){
    $(".para-1").css("height", "auto");
    $(".show-data-1").css("display", "none");
    $(".showless-data-1").css("display", "flex");
  });
  
   $(".showless-data-1").click(function(){
    $(".para-1").css("height", "670px");
    $(".show-data-1").css("display", "flex");
    $(".showless-data-1").css("display", "none");
  });
  
   var a = $(".para-1").height();
   if(a < 265){
$(".show-data-1").css("display", "none");
     $(".showless-data-1").css("display", "none");
}
else{
    $(".show-data-1").css("display", "flex");
    $(".para-1").css("height", "670px");
  $(".showless-data-1").css("display", "none");
}
$(".show-data-2").click(function(){
    $(".para-2").css("height", "auto");
    $(".show-data-2").css("display", "none");
    $(".showless-data-2").css("display", "flex");
  });
  
   $(".showless-data-2").click(function(){
    $(".para-2").css("height", "270px");
    $(".show-data-2").css("display", "flex");
    $(".showless-data-2").css("display", "none");
  });
  
   var c = $(".para-2").height();
   if(c < 265){
$(".show-data-2").css("display", "none");
     $(".showless-data-2").css("display", "none");
}
else{
    $(".show-data-2").css("display", "flex");
    $(".para-2").css("height", "270px");
  $(".showless-data-2").css("display", "none");
}