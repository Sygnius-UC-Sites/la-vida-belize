<div class="col-md-6 d-flex justify-content-end">
     @isset($addbar['add-delete'])
     <a class="btn btn-outline-info mx-2  delete-checkbox"
          href="javaScript:void(0)"><span class="fa fa-times"></span> Delete</a>
     @else

     @endisset

     @isset($addbar['back-anchor'])
     <a class="btn btn-outline-info mx-2"
          href="{{ $addbar['back-anchor'] }}"><span class="fa fa-arrow-left"></span> Back</a>
     @endisset
     @if($addbar['add-data'])
     <a class="btn btn-outline-secondary mx-2"
          href="{{ $addbar['add-anchor'] }}"><span class="fa fa-plus"></span> {{ $addbar['add-name'] }}</a>
     @endif
  
    @if(isset($addbar['add-export-data']) && $addbar['add-export-data'])
                    <a class="btn btn-app  " 
                       href="{{ $addbar['add-export-anchor'] }}"><span class="fa fa-plus"></span> {{ $addbar['add-export-name'] }}</a>

                  @endif
  
     @isset($addbar['custom-data'])
     <a class="btn btn-outline-info mx-2 "
          href="{{ $addbar['custom-anchor'] ?? '#' }}"><span class="fa fa-retweet"></span> {{ $addbar['custom-name'] }}</a>
     @endisset
     @isset($addbar['review-email'])
     <a class="btn btn-outline-info mx-2 "
          href="{{ url('send-review-email') }}"><span class="fa fa-retweet"></span> Today Review Email </a>

     @endisset
     @isset($addbar['welcome-email'])
     <a class="btn btn-outline-info mx-2 "
          href="{{ url('send-welcome-packages') }}"><span class="fa fa-retweet"></span> Today Welcome Email </a>

     @endisset
     @isset($addbar['reminder-email'])
     <a class="btn btn-outline-info mx-2 "
          href="{{ url('send-reminder-email') }}"><span class="fa fa-retweet"></span> Today Reminder Email </a>

     @endisset
     @isset($addbar['set-pricelab'])
     <a class="btn btn-outline-secondary mx-2"
          href="{{ url('set-pricelab') }}"><span class="fa fa-retweet"></span> Run Pricelab </a>

     @endisset




     @isset($addbar['custom-data1'])
     <a class="btn btn-outline-info mx-2 "
          href="{{ $addbar['custom-anchor1'] ?? '#' }}"><span class="fa fa-list"></span> {{ $addbar['custom-name1'] }}</a>

     @endisset
     @isset($addbar['download-ical'])
     <a class="btn btn-outline-info mx-2 "
          href="{{ $addbar['download-ical-link'] ?? '#' }}" download><span class="fa fa-download"></span> Download Ical</a>
     @endisset


     @isset($addbar['expot-data'])
     <a class="btn btn-outline-warning mx-2"
          href="{{ $addbar['expot-data'] }}"><span class="fa fa-download"></span> Export</a>

     @endif
     @isset($addbar['copy-data'])
     <a class="btn btn-outline-info mx-2 "
          data-href="{{ $addbar['copy-ical-link'] }}" id="copy-data" onclick="myFunction()"><span class="fa fa-copy"></span> Export Ical Link</a>

     @endif

     @isset($addbar['model-media'])
     <a class="btn btn-outline-info mx-2"
          href="javascript::void(0)" data-toggle="modal" data-target="#myModal"><span class="fa fa-download"></span> upload</a>
     @endisset
     @isset($addbar['model-print'])
     <a class="btn btn-outline-info mx-2"
          href="javascript::void(0)" onclick="printDiv('printableArea')"><span class="fa fa-print"></span> Print T3his Order</a>
     @endisset
     @isset($addbar['model-download'])
     <a class="btn btn-outline-info mx-2"
          href="{{ $addbar['download-data'] }}"><span class="fa fa-download"></span> Download This Order</a>
     @endisset
     @isset($addbar['refresh-calander-data'])
     <a class="btn btn-outline-warning"
          href="{{ route('refresshCalendar') }}"><span class="fa fa-retweet"></span> Refresh Calendar</a>
     @endisset
     <a class="btn btn-outline-success mx-2"
          href="javascript::void(0)" onclick="location.href=location.href"><span class="fa fa-retweet"></span> Refresh</a>
</div>