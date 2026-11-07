

<link rel="stylesheet" href="https://fullcalendar.io/js/fullcalendar-3.4.0/fullcalendar.min.css">
<style>
#calendar0 {
    width: 48%;
    margin: 0 auto;display: inline-block;
}
#calendar1 {
    width: 48%;
    margin: 0 auto;display: inline-block;
}
#calendar1 .fc-view-container{
    margin-top: 5px;    
}
.booked-date-full-calendar {
  background-color: red;
}
.available-date-full-calendar {
  background-color: green;
}
.available-date-full-calendar {
  background-color: green;
}
.beckground-white{
  background-color: white !important;
  border: unset !important;
}
@media screen and (max-width: 720px) {
    #calendar0 {
        width: 96% !important;
    }
    #calendar1 {
      display: none !important;
    }
}
</style>

<div style="">
  <div id='calendar0'></div>
  <div id='calendar1'></div>
</div>


<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.9.0/moment.min.js"></script>
<script src="{{ asset('toastr/fullcalendar.js') }}"></script>

<script src="https://fullcalendar.io/releases/core/4.2.0/main.min.js"></script>
<script src="https://fullcalendar.io/releases/daygrid/4.2.0/main.min.js"></script>
<script src="https://fullcalendar.io/releases/timegrid/4.2.0/main.min.js"></script>
<script src="https://fullcalendar.io/releases/list/4.2.0/main.min.js"></script>
<script src="https://fullcalendar.io/releases/list/4.2.0/main.min.js"></script>
<script>







    var todayDate = moment().startOf('day');
    var YM = todayDate.format('YYYY-MM');
    var YESTERDAY = todayDate.clone().subtract(1, 'day').format('YYYY-MM-DD');
    var TODAY = todayDate.format('YYYY-MM-DD');
    var TOMORROW = todayDate.clone().add(1, 'day').format('YYYY-MM-DD');










$(document).ready(function() {
   var date = new Date();
   var d = date.getDate();
   var m = date.getMonth();
   var y = date.getFullYear();
   var x = new Date();
   x.setDate(1);
   x.setMonth(x.getMonth() - 1);

   var cal0 = $('#calendar0');
   var cal1 = $('#calendar1');

   cal0.fullCalendar({
    header: {
      left: 'title',
      center: '',
      right: 'prev,next ',
        daysOfWeek: [0, 6], //Sundays and saturdays
    rendering: "background",
    color: "#eee",
    overLap: false,
     initialView: 'dayGridMonth',
    allDay: true
    },
    defaultDate: x
    ,events: {!! Helper::getPropertyRates(Request::segment(2)) !!},
    viewRender: function(view, element) {
      cur = view.intervalStart;
      d = moment(cur).add('months', 1);
      cal1.fullCalendar('gotoDate', d);
    }
  });

  cal1.fullCalendar({
    header: {
      left: '',
      center: '',
      right: 'title',
       initialView: 'dayGridMonth',
        daysOfWeek: [0, 6], //Sundays and saturdays
    rendering: "background",
    color: "#eee",
    overLap: false,
    allDay: true
    }
    ,events:{!! Helper::getPropertyRates(Request::segment(2)) !!}
  });`enter code here`
});



</script>