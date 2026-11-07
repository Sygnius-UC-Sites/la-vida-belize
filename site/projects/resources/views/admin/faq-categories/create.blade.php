@extends('admin.layouts')
@section('title', 'Admin')
@php
$name="FAQ Categories";$route="faq-categories";
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
  <div class="ca-page-toolbar">
    <div class="ca-page-toolbar-title"><h1>Add FAQ category</h1></div>
    <a href="{{ route($route.'.index') }}" class="ca-page-toolbar-action"><i class="fa fa-arrow-left"></i> Back</a>
  </div>
  <div class="row">
    <div class="col-12">
      <div class="card owner-narrow-card">
        <div class="card-header">
          <h3 class="card-title">Create {{ Str::singular($name) }}</h3>
        </div>
        <div class="card-body">
          {!! Form::open(['route' => $route.'.store']) !!}
          @include("admin.".$route.".form")
          <button class="btn btn-success"><span class="fa fa-save"></span> Save</button>
          {!! Form::close() !!}
        </div>
      </div>
    </div>
  </div>
</section>
@stop
