   $(document).ready(function(){
          $('#test-slider').owlCarousel( {
    
       loop: true,
    
        items: 3,
    
        margin: 15,
    
        autoplay: true,
    
        dots:false,
    
        nav:false,
    
        autoplayTimeout: 4000,
    
        smartSpeed: 550,
    
        navText: ['<i class="fa fa-angle-left"></i>','<i class="fa fa-angle-right"></i>'],
    
        responsive: {
    
          0: {
    
            items: 1
    
          },
    
          768: {
    
            items: 2
    
          },
    
          1170: {
    
            items: 3
    
          }
    
        }
    
      });
    });

function playVideo() {
            $('#mob').trigger('play');
        }
        function pauseVideo() {
            $('#mob').trigger('pause');
        }
        
        $(document).ready(function(){
  $("#pause").click(function(){
    $("#play").css("display", "block");
     $("#pause").css("display", "none");
  });
  $("#play").click(function(){
    $("#pause").css("display", "block");
     $("#play").css("display", "none");
  });
});