@extends("front.layouts.master")
@section("title",$data->meta_title)
@section("keywords",$data->meta_keywords)
@section("description",$data->meta_description)
@section("container")
@php
$name=$data->name;
$bannerImage=asset('front/images/breadcrumb.webp');
@endphp
<!-- start banner sec -->
@include("front.layouts.banner")
<!-- start about section -->

<section class="faq">
    <div class="container">
        <div class="row">
            <div class="col-12 col-md-12 col-sm-4">
                <div class="head-sec">
                <h2>Frequently Asked Questions</h2>
                <div class="sec-line">
                    <span class="sec-line1"></span>
                    <span class="sec-line2"></span>
                </div>
                </div>
                <div class="accordion accordion-flush" id="accordionFlushExample">
                    @php $i=1; @endphp
                    @forelse(App\Models\Faq::where('property_id',$property->id)->orderBy("id","desc")->get() as $c) 
                    @if($i==1)           
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="flush-headingOne{{$i}}">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne{{$i}}" aria-expanded="false" aria-controls="flush-collapseOne{{$i}}">
                            {{$i}}. {{$c->question}}
                            </button>
                        </h2>
                        <div id="flush-collapseOne{{$i}}" class="accordion-collapse collapse" aria-labelledby="flush-headingOne{{$i}}" data-bs-parent="#accordionFlushExample">
                            <div class="accordion-body">{!! $c->answer !!}</div>
                        </div>
                    </div>
                    @else
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="flush-headingFive{{$i}}">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseFive{{$i}}" aria-expanded="false" aria-controls="flush-collapseFive{{$i}}">
                            {{$i}}. {{$c->question}}
                            </button>
                        </h2>
                        <div id="flush-collapseFive{{$i}}" class="accordion-collapse collapse" aria-labelledby="flush-headingFive{{$i}}" data-bs-parent="#accordionFlushExample">
                            <div class="accordion-body">{!! $c->answer !!}</div>
                        </div>
                    </div>
                    @endif
                    @php $i++; @endphp
                    @empty
                    <p class="text-center"> Unfortunately No Faq present yet for this propety!.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</section>
@stop
@section("css")
@parent
<link rel="stylesheet" href="{{ asset('front')}}/css/faq.css" />
<link rel="stylesheet" href="{{ asset('front')}}/css/faq-responsive.css" />
@stop 
@section("js")
@parent
<script src="{{ asset('front')}}/js/faq.js" ></script>
@stop
       