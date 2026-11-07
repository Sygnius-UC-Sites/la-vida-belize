@extends('admin.layouts')
@section('title', 'Admin')
@php
$name="Properties";$route="properties";
@endphp
@section('content_header')
<div class="row">
    <div class="col-sm-6">
        <h1 class="m-0 text-dark">{{$name}} Management</h1>
    </div>
    @php
    $addbar=["name"=>$name,"add-data"=>false,"add-name"=>"Add ". Str::singular($name),"add-anchor"=>route($route.'.create'),"back-anchor"=>route($route.'.index')];
    @endphp
    @include("admin.common.add-bar")
</div>
@stop

@section('content')
@parent
<section class="content">
    <div class="listing-start">
        <div>
            <span class="listing-start-eyebrow">Step 1 of 9</span>
            <h2>Start a new listing</h2>
            <p>Create the listing shell with the essentials. After this save, you will continue through photos, amenities, rates, calendar, fees, booking rules, guest messages, and SEO in the setup studio.</p>
        </div>
        <ol class="listing-start-steps">
            <li class="is-active">Basics</li>
            <li>Photos</li>
            <li>Amenities</li>
            <li>Rates</li>
            <li>Calendar</li>
            <li>Fees</li>
            <li>Rules</li>
            <li>Stay info</li>
            <li>Publish</li>
        </ol>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Listing basics</h3>
                </div>
                <div class="card-body">
                    {!! Form::open(['route' => $route.'.store',"files"=>"true"]) !!}

                    @include("admin.".$route.".form")

                    <button class="btn btn-success"><span class="fa fa-save"></span> Create listing and continue setup</button>

                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    </div>
</section>
@stop
@section("css")
@parent
<link rel="stylesheet" type="text/css" href="{{ asset('drag-drop-image-uploader/src/image-uploader.css') }}">
<style>
    .listing-start {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 24px;
        align-items: start;
        background: #fff;
        border: 1px solid #e6e8ee;
        border-radius: 12px;
        padding: 22px 24px;
        margin-bottom: 18px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .04);
    }
    .listing-start-eyebrow {
        display: inline-block;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 6px;
    }
    .listing-start h2 {
        margin: 0 0 6px;
        color: #0f172a;
        font-size: 24px;
        font-weight: 700;
    }
    .listing-start p {
        margin: 0;
        max-width: 760px;
        color: #64748b;
        line-height: 1.5;
    }
    .listing-start-steps {
        display: grid;
        grid-template-columns: repeat(3, minmax(84px, 1fr));
        gap: 8px;
        list-style: none;
        padding: 0;
        margin: 0;
        min-width: 310px;
    }
    .listing-start-steps li {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 7px 10px;
        color: #64748b;
        font-size: 12px;
        font-weight: 600;
        text-align: center;
        background: #f8fafc;
    }
    .listing-start-steps li.is-active {
        background: #15253f;
        color: #fff;
        border-color: #15253f;
    }
    @media (max-width: 992px) {
        .listing-start { grid-template-columns: 1fr; }
        .listing-start-steps { min-width: 0; width: 100%; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
</style>
@stop
@section("js")
@parent
<script src="{{ asset('drag-drop-image-uploader/src/image-uploader.js') }}"></script>
<script>
    $(function() {
        var ckCfg = {
            allowedContent: true,
            filebrowserUploadUrl: "{{route('ckeditor.upload', ['_token' => csrf_token() ])}}",
            filebrowserUploadMethod: 'form'
        };
        ['get_quote_description', 'long_description', 'cancellation_policy', 'welcome_package_description'].forEach(function (name) {
            if (!window.CKEDITOR || !document.getElementById(name) || CKEDITOR.instances[name]) return;
            CKEDITOR.replace(name, ckCfg);
        });



        $('.input-images-2').imageUploader({
            imagesInputName: 'images'
        });
    });
</script>
@stop
