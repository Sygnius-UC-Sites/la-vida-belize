@extends('admin.layouts')
@section('title', 'Admin')
@php
$name="Testimonials";$route="testimonials";
@endphp
@section('content_header')
<div class="row">
  <div class="col-sm-6">
    <h1 class="m-0 text-dark">{{$name}} Management</h1>
  </div>
  @php
  $addbar=["name"=>$name,"add-data"=>false,"add-name"=>"Add ".Str::singular($name),"add-anchor"=>route($route.'.create'),"back-anchor"=>route($route.'.index')];
  @endphp
  @include("admin.common.add-bar")
</div>
@stop

@section('content')
@parent
<!-- Content Header (Page header) -->
<!-- <section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-12">
        @php
        $addbar=["name"=>$name,"add-data"=>false,"add-name"=>"Add ". Str::singular($name),"add-anchor"=>route($route.'.create'),"back-anchor"=>route($route.'.index')];
        @endphp
        @include("admin.common.add-bar")
      </div>
    </div>
  </div>
</section> -->

<!-- Main content -->
<section class="content">

  <div class="row">
    <div class="col-12">

      <div class="card  ">
        <div class="card-header">
          <h3 class="card-title">Create {{ Str::singular($name) }}</h3>
          <div class="card-tools">
            <!-- <button type="button" class="btn btn-tool" data-card-widget="maximize"><i class="fas fa-expand"></i>
            </button> -->
          </div>
        </div>
        <!-- /.card-header -->
        <div class="card-body">
          {!! Form::model($data,['route' => [$route.'.update',$data->id],"files"=>"true","method"=>"put"]) !!}

          @include("admin.".$route.".form")

          <button class="btn btn-success"><span class="fa fa-save"></span> Update</button>

          {!! Form::close() !!}
        </div>
        <!-- /.card-body -->
      </div>
      <!-- /.card -->
    </div>
    <!-- /.col -->
  </div>
  <!-- /.row -->
</section>
<!-- /.content -->
@stop