
<h4 class="text-warning">Seo Section</h4>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("meta_title", "Page title (<title> tag)") !!}
            {!! Form::textarea("meta_title",null,["class"=>"form-control","rows"=>"2"]) !!}
            <small class="text-muted">60-70 chars. Shown in browser tab + Google search results.</small>
            <span class="text-danger">{{ $errors->first("meta_title")}}</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("meta_keywords", "Meta keywords") !!}
            {!! Form::textarea("meta_keywords",null,["class"=>"form-control","rows"=>"2"]) !!}
            <small class="text-muted">Comma-separated. Largely ignored by search engines but harmless to populate.</small>
            <span class="text-danger">{{ $errors->first("meta_keywords")}}</span>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("meta_description", "Meta description") !!}
            {!! Form::textarea("meta_description",null,["class"=>"form-control","rows"=>"2"]) !!}
            <small class="text-muted">120-160 chars. Shown in Google search-result snippets.</small>
            <span class="text-danger">{{ $errors->first("meta_description")}}</span>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("header_section", "Custom HTML for <head>") !!}
            {!! Form::textarea("header_section",null,["class"=>"form-control","rows"=>"2"]) !!}
            <small class="text-muted">Analytics scripts, font preloads, custom meta tags. Injected before &lt;/head&gt;.</small>
            <span class="text-danger">{{ $errors->first("header_section")}}</span>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("footer_section", "Custom HTML before </body>") !!}
            {!! Form::textarea("footer_section",null,["class"=>"form-control","rows"=>"2"]) !!}
            <small class="text-muted">Chat widgets, third-party scripts. Injected at the bottom of the page.</small>
            <span class="text-danger">{{ $errors->first("footer_section")}}</span>
        </div>
    </div>
</div>
