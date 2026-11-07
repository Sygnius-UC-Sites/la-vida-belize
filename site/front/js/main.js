const header = document.querySelector(".page-header");
const toggleClass = "is-sticky";

window.addEventListener("scroll", () => {
  const currentScroll = window.pageYOffset;
  if (currentScroll > 150) {
    header.classList.add(toggleClass);
  } else {
    header.classList.remove(toggleClass);
  }
});



$(document).ready(function(){
  $(".gst").click(function(){
    $("#guestsss").css("display", "block");
  });
   $(".close1").click(function(){
    $("#guestsss").css("display", "none");
  });
   $(".close111").click(function(){
    $("#guestsss").css("display", "none");
  });
  
   $(".close1112").click(function(){
    $("#guestsss1").css("display", "none");
  });
});