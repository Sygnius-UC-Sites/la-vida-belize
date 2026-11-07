@extends('admin.layouts')
@section('title', 'Add Property Category')
@section('content')
@parent
<section class="content">
    <div class="ca-page-toolbar">
        <div class="ca-page-toolbar-title"><h1>Add property category</h1></div>
        <a href="{{ route('property-categories.index') }}" class="ca-page-toolbar-action">
            <i class="fa fa-arrow-left"></i> Back
        </a>
    </div>
    <div class="card owner-narrow-card">
        <div class="card-header"><h3 class="card-title">Add property category</h3></div>
        <form method="post" action="{{ route('property-categories.store') }}">
            @include('admin.property-categories._form')
        </form>
    </div>
</section>
@stop
