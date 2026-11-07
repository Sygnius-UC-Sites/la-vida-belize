<style>
.fq-form-card { background: #fff; border: 1px solid #e6e8ee; border-radius: 10px; padding: 18px 20px; margin-bottom: 16px; }
.fq-form-card h5 { font-size: 13px; font-weight: 600; color: #5b6473; text-transform: uppercase; letter-spacing: .04em; margin: 0 0 6px 0; }
.fq-form-card .fq-hint { color: #5b6473; font-size: 12.5px; margin: 0 0 12px 0; line-height: 1.5; }
.fq-form-card .form-group label { font-weight: 600; color: #1e293b; font-size: 14px; }
.fq-form-card .form-text { color: #475569; font-size: 12.5px; margin-top: 4px; }
</style>

<div class="fq-form-card">
    <h5>📍 Where this FAQ appears</h5>
    <p class="fq-hint">Pick a property to scope the FAQ to that property's detail page only. Leave blank to make it a <strong>global FAQ</strong> visible on the public FAQs page and on every property's detail page.</p>
    <div class="form-group">
        {!! Form::label("property_id", "Scope") !!}
        {!! Form::select("property_id", ['' => '🌐 Global — show everywhere'] + ModelHelper::getProperptySelectList(), null, ["class" => "form-control"]) !!}
        <small class="form-text">Most general questions (check-in time, cancellation policy) should stay global. Use property scoping for specific things like "How to access the rooftop terrace at Casita Del Eden".</small>
        <span class="text-danger">{{ $errors->first("property_id")}}</span>
    </div>
</div>

<div class="fq-form-card">
    <h5>❓ Question</h5>
    <p class="fq-hint">Phrase it the way a guest would actually ask. Short and natural reads better than formal.</p>
    <div class="form-group">
        {!! Form::label("question", "What's the question?") !!}
        {!! Form::textarea("question", null, ["class" => "form-control", "rows" => 2, "required", "placeholder" => "What time can we check in?"]) !!}
        <small class="form-text">One sentence. Keep it under ~120 characters so it reads cleanly on the FAQ accordion.</small>
        <span class="text-danger">{{ $errors->first("question")}}</span>
    </div>
</div>

<div class="fq-form-card">
    <h5>💬 Answer</h5>
    <p class="fq-hint">You can use formatting (bold, lists, links) to make complex answers scannable. Plain text also works.</p>
    <div class="form-group">
        {!! Form::label("answer", "The answer") !!}
        {!! Form::textarea("answer", null, ["class" => "form-control ckeditor", "rows" => 8, "id" => "fq-answer-editor", "placeholder" => "Standard check-in is at 3pm. If you arrive earlier, drop your bags…"]) !!}
        <small class="form-text">Aim for 1–4 short paragraphs. Bullet lists are great for things like "what to bring" or step-by-step instructions.</small>
        <span class="text-danger">{{ $errors->first("answer")}}</span>
    </div>
</div>

<div class="fq-form-card">
    <h5>🏷️ Category</h5>
    <p class="fq-hint">Controls which filter tab this FAQ appears under on the public FAQs page. Manage the list under <a href="{{ route('faq-categories.index') }}" target="_blank">FAQ Categories</a>.</p>
    <div class="form-group">
        {!! Form::label("type", "Category") !!}
        @php
            $faqCats = \Schema::hasTable('faq_categories')
                ? \App\Models\FaqCategory::where('publish','published')->orderBy('ordering')->orderBy('id')->pluck('name','seo_url')->toArray()
                : [];
        @endphp
        {!! Form::select("type", ['' => '— Uncategorised —'] + $faqCats, null, ["class" => "form-control"]) !!}
        <small class="form-text">Leave blank to show the FAQ only under the "All Questions" tab.</small>
        <span class="text-danger">{{ $errors->first("type")}}</span>
    </div>
</div>

<script>
(function () {
    if (window.CKEDITOR) {
        var ed = document.getElementById('fq-answer-editor');
        if (ed && !CKEDITOR.instances[ed.name]) CKEDITOR.replace(ed.name);
    }
})();
</script>
