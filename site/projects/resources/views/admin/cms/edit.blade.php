@extends('admin.layouts')
@section('title', 'Edit Page')

@php
    $pageName = $data->name ?: 'CMS page';
    $pageSlug = trim((string) $data->seo_url, '/');
    $pageTemplate = $data->templete ?: 'common';
    $publicUrl = url($pageSlug);
    $isTransactional = in_array($pageTemplate, [
        'booking', 'checkout', 'magic-link-payment', 'magic-link-expired',
        'payment-success', 'payment-failed', 'booking-confirmed', 'booking-request-received',
    ], true);
@endphp

@section('css')
@parent
@include('admin.cms.partials.cms-shell')
@stop

@section('content')
@parent
<div class="cms-page">
    <div class="cms-page-header">
        <div class="cms-header-text">
            <h1>Edit {{ $pageName }}</h1>
            <span class="cms-page-sub">/{{ $pageSlug }} · {{ Str::headline($pageTemplate) }} template</span>
        </div>
        <a href="{{ route('cms.index') }}" class="cms-add-btn">
            <i class="fa fa-arrow-left"></i> Back to pages
        </a>
    </div>

    <div class="cms-editor-wrap">
        @if (session('success') || session('message'))
            <div class="alert alert-success">{{ session('success') ?? session('message') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">Please review the highlighted fields and try again.</div>
        @endif

        {!! Form::model($data, ['route' => ['cms.update', $data->id], 'files' => true, 'method' => 'put', 'id' => 'cms-page-form']) !!}
            <div class="cms-editor-layout">
                <main class="cms-editor-card">
                    @include('admin.cms.form')
                </main>

                <aside class="cms-help-panel">
                    <h2 class="cms-help-title">Editing guide</h2>
                    <ul class="cms-help-list">
                        <li>
                            <strong>Public page</strong>
                            <a href="{{ $publicUrl }}" target="_blank" rel="noopener">Open /{{ $pageSlug }}</a> after saving to verify the live result.
                        </li>
                        <li>
                            <strong>Page sections</strong>
                            Fields are grouped by where they appear: banner, page labels, content blocks, and SEO.
                        </li>
                        <li>
                            <strong>Safe edits</strong>
                            Text and image changes are expected. Avoid changing the URL slug after search engines or guests have the link.
                        </li>
                        @if($isTransactional)
                            <li>
                                <strong>Booking flow</strong>
                                This is a transactional page. Keep edits to labels and copy so checkout behavior stays intact.
                            </li>
                        @endif
                    </ul>
                    <button class="cms-primary-btn" type="submit">
                        <i class="fa fa-save"></i> Update page
                    </button>
                </aside>
            </div>

            <div class="cms-save-bar">
                <span class="cms-save-note">Save, then open the public page to confirm spacing, copy, and images.</span>
                <a href="{{ route('cms.index') }}" class="cms-secondary-btn">Cancel</a>
                <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="cms-secondary-btn">
                    <i class="fa fa-external-link-alt"></i> View public
                </a>
                <button class="cms-primary-btn" type="submit">
                    <i class="fa fa-save"></i> Update page
                </button>
            </div>
        {!! Form::close() !!}
    </div>
</div>
@stop

@section('js')
@parent
<script>
    ['longDescription', 'seo_section', 'mediumDescription'].forEach(function (id) {
        if (document.getElementById(id) && window.CKEDITOR && !CKEDITOR.instances[id]) {
            CKEDITOR.replace(id, {
                allowedContent: true,
                filebrowserUploadUrl: "{{ route('ckeditor.upload', ['_token' => csrf_token()]) }}",
                filebrowserUploadMethod: 'form'
            });
        }
    });
</script>
@stop
