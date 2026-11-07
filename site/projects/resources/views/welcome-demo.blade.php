<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="{{ asset('live4calender/style.css') }}">


<div class="col-md-12">
	<div class="calendar-section" id="calender-gaurav-section">
		
	</div>
</div>


<script src="https://code.jquery.com/jquery-3.6.1.min.js" integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ=" crossorigin="anonymous"></script>
<script>
    $(document).on("click",".switch-left",function(){
        data={_token:"{{ csrf_token() }}",id:{{ Request::segment(2) }},date:$(this).data("date"),operation:"minus"};
        ajaxCall(data);
    });
    
    $(document).on("click",".switch-right",function(){
        data={_token:"{{ csrf_token() }}",id:{{ Request::segment(2) }},date:$(this).data("date"),operation:"plus"};
        ajaxCall(data);
    });
    
    function ajaxCall(data){
        url="{{ url('fullcalendar-demo-post') }}";
        
        $.post(url,data,function(data){
            $("#calender-gaurav-section").html(data);
        })
    }
    
    $(function(){
        data={_token:"{{ csrf_token() }}",id:{{ Request::segment(2) }},date:"{{ date('01-m-Y') }}",operation:"current"};
        ajaxCall(data);
    })
    
    
</script>

