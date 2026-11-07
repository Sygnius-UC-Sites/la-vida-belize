  $(document).ready(function(){
  var a = $(".memory-content").height();
    if(a < 400){
 $("a.main-btn").css("display", "none");
 }
 else{
     $("a.main-btn").css("display", "block");
 }
  });

