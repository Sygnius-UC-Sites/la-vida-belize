@extends("front.layouts.master")
@section("title",$data->meta_title)
@section("keywords",$data->meta_keywords)
@section("description",$data->meta_description)
@section("header-section")
{!! $data->header_section !!}
@stop
@section("footer-section")
{!! $data->footer_section !!}
@stop
@section("css")

@stop


@section("container")

      @php
        $name=$data->name;
        $bannerImage=asset('front/images/breadcrumb.webp');
        if($data->bannerImage){
            $bannerImage=asset($data->bannerImage);
        }
    @endphp
    @include("front.layouts.banner")


    <!-- start about section -->
        
      <!-- About Section -->
      <section class="about_wrapper">
         <div class="container">
            <div class="row m-0">

                    {!! $data->mediumDescription !!}
                    {!! $data->longDescription !!}

              
            </div>
         </div>
      </section>

{!! $data->seo_section !!}
@stop