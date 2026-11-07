<div class="col-md-12">
    @isset($addbar['back-anchor'])
    <a class="btn btn-outline-info m-2"
        href="{{ $addbar['back-anchor'] }}"><span class="fa fa-arrow-left"></span> Back</a>
    @endisset

    @if($addbar['add-data'])
    <a class="btn btn-outline-info m-2"
        href="{{ $addbar['add-anchor'] }}"><span class="fa fa-plus"></span> {{ $addbar['add-name'] }}</a>
    @endif
  
   @if(isset($addbar['add-export-data']) && $addbar['add-export-data'])
                    <a class=" btn btn-outline-info m-2 " 
                       href="{{ $addbar['add-export-anchor'] }}"><span class="fa fa-plus"></span> {{ $addbar['add-export-name'] }}</a>

                  @endif

    @isset($addbar['custom-data'])
     <a class="btn btn-outline-info m-2 "
          href="{{ $addbar['custom-anchor'] ?? '#' }}"><span class="fa fa-retweet"></span> {{ $addbar['custom-name'] }}</a>
     @endisset

    @isset($addbar['custom-data1'])
    <a class="btn btn-outline-info m-2 "
        href="{{ $addbar['custom-anchor1'] ?? '#' }}"><span class="fa fa-list"></span> {{ $addbar['custom-name1'] }}</a>
    @endisset

    @isset($addbar['download-ical'])
    <a class="btn btn-outline-info m-2 "
        href="{{ $addbar['download-ical-link'] ?? '#' }}" download><span class="fa fa-download"></span> Download Ical</a>
    @endisset

    @isset($addbar['copy-data'])
     <a class="btn btn-outline-info m-2 "
          data-href="{{ $addbar['copy-ical-link'] }}" id="copy-data" onclick="myFunction()"><span class="fa fa-copy"></span> Export Ical Link</a>

     @endif

     <a class="btn btn-outline-info m-2"
          href="javascript::void(0)" onclick="location.href=location.href"><span class="fa fa-retweet"></span> Refresh</a>
    
</div>