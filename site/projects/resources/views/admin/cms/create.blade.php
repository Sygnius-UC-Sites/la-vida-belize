@extends('admin.layouts')
@section('title', 'Create Page')

@section('css')
@parent
@include('admin.cms.partials.cms-shell')
@stop

@section('content')
@parent
<div class="cms-page">
    <div class="cms-page-header">
        <div class="cms-header-text">
            <h1>Create page</h1>
            <span class="cms-page-sub">Add a CMS page with a stable URL, template, and SEO fields.</span>
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

        {!! Form::open(['route' => 'cms.store', 'files' => true, 'id' => 'cms-page-form']) !!}
            <div class="cms-editor-layout">
                <main class="cms-editor-card">
                    @include('admin.cms.form')
                </main>

                <aside class="cms-help-panel">
                    <h2 class="cms-help-title">Before saving</h2>
                    <ul class="cms-help-list">
                        <li>
                            <strong>URL slug</strong>
                            Keep it short and stable. Changing it later can break public links and SEO.
                        </li>
                        <li>
                            <strong>Template</strong>
                            The default template fields are hidden here. Edit advanced template routing only from code.
                        </li>
                        <li>
                            <strong>Content</strong>
                            After the page is created, the editor shows page-specific sections like banners, labels, and structured blocks.
                        </li>
                    </ul>
                    <button class="cms-primary-btn" type="submit">
                        <i class="fa fa-save"></i> Save page
                    </button>
                </aside>
            </div>

            <div class="cms-save-bar">
                <span class="cms-save-note">Create the page, then reopen it to manage page-specific content sections.</span>
                <a href="{{ route('cms.index') }}" class="cms-secondary-btn">Cancel</a>
                <button class="cms-primary-btn" type="submit">
                    <i class="fa fa-save"></i> Save page
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
