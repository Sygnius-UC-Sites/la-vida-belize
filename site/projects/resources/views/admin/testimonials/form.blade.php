<style>
.tm-form-card { background: #fff; border: 1px solid #e6e8ee; border-radius: 10px; padding: 18px 20px; margin-bottom: 16px; }
.tm-form-card h5 { font-size: 13px; font-weight: 600; color: #5b6473; text-transform: uppercase; letter-spacing: .04em; margin: 0 0 14px 0; }
.tm-form-card .form-group label { font-weight: 600; color: #1e293b; font-size: 14px; }
.tm-form-card .form-text { color: #475569; font-size: 12.5px; margin-top: 4px; }
.tm-stars-picker { display: inline-flex; gap: 4px; font-size: 28px; cursor: pointer; user-select: none; }
.tm-stars-picker .tm-star { color: #cbd5e1; transition: color .12s, transform .08s; }
.tm-stars-picker .tm-star:hover { transform: scale(1.1); }
.tm-stars-picker .tm-star.active { color: #f59e0b; }
.tm-toggle { display: inline-flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
.tm-toggle input { display: none; }
.tm-toggle .tm-track { width: 44px; height: 24px; background: #e2e8f0; border-radius: 999px; position: relative; transition: background .15s; }
.tm-toggle .tm-track::after { content: ""; position: absolute; left: 2px; top: 2px; width: 20px; height: 20px; background: #fff; border-radius: 50%; box-shadow: 0 1px 2px rgba(0,0,0,.2); transition: left .15s; }
.tm-toggle input:checked + .tm-track { background: #1e293b; }
.tm-toggle input:checked + .tm-track::after { left: 22px; }
.tm-toggle .tm-toggle-label { font-weight: 500; color: #2c3447; font-size: 14px; }
</style>

<div class="tm-form-card">
    <h5>👤 Reviewer</h5>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label("name", "Reviewer name") !!}
                {!! Form::text("name", null, ["class" => "form-control", "required", "placeholder" => "Sarah Chen"]) !!}
                <small class="form-text">The name shown publicly on testimonial cards. Use first name + last initial for privacy if needed.</small>
                <span class="text-danger">{{ $errors->first("name")}}</span>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label("image", "Reviewer photo (optional)") !!}
                {!! Form::file("image", ["class" => "form-control", "accept" => "image/*"]) !!}
                <small class="form-text">Square portrait photo, ~300×300, JPG/PNG/WebP. Shown next to the review on the home page testimonials carousel.</small>
                @isset($data)
                    @if($data->image != "")
                        <div class="mt-2"><img src="{{ asset(($data->image)) }}" width="120" style="border-radius:8px;border:1px solid #e2e8f0;"></div>
                        <div class="custom-control custom-checkbox mt-1">
                            <input class="custom-control-input custom-control-input-danger" name="remove_image" value="yes" type="checkbox" id="remove_image">
                            <label for="remove_image" class="custom-control-label">Remove photo</label>
                        </div>
                    @endif
                @endisset
                <span class="text-danger">{{ $errors->first("image")}}</span>
            </div>
        </div>
    </div>
</div>

<div class="tm-form-card">
    <h5>⭐ Rating</h5>
    <div class="form-group">
        {!! Form::label("score", "How many stars?") !!}
        <div>
            @php $existingScore = (int) (old('score', $data->score ?? $data->rating ?? 5)); @endphp
            {!! Form::hidden("score", $existingScore, ["id" => "tm-score-input"]) !!}
            <div class="tm-stars-picker" id="tm-stars-picker" role="radiogroup" aria-label="Star rating">
                @for($i=1; $i<=5; $i++)
                    <span class="tm-star {{ $i <= $existingScore ? 'active' : '' }}" data-value="{{ $i }}" role="radio" tabindex="0" aria-checked="{{ $i == $existingScore ? 'true' : 'false' }}">★</span>
                @endfor
            </div>
            <small class="form-text">Click a star to set the rating. 1 = poor, 5 = excellent.</small>
            <span class="text-danger">{{ $errors->first("score")}}</span>
        </div>
    </div>
</div>

<div class="tm-form-card">
    <h5>🏠 Stay details</h5>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label("property_id", "Property") !!}
                {!! Form::select("property_id", ['' => '— Generic (no specific property) —'] + $properties->mapWithKeys(fn($p) => [$p->id => $p->name . ' (' . $p->type . ')'])->toArray(), null, ["class" => "form-control"]) !!}
                <small class="form-text">Attach this review to a specific condo or house. Leave empty for a generic site-wide testimonial.</small>
                <span class="text-danger">{{ $errors->first("property_id")}}</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label("stay_date", "Check-in date") !!}
                {!! Form::text("stay_date", null, ["class" => "form-control", "readonly", "data-vr-datepicker" => "range", "data-end-input" => "#testimonial-end-date"]) !!}
                <small class="form-text">When the guest arrived.</small>
                <span class="text-danger">{{ $errors->first("stay_date")}}</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label("end_date", "Check-out date") !!}
                {!! Form::text("end_date", null, ["class" => "form-control", "readonly", "id" => "testimonial-end-date"]) !!}
                <small class="form-text">Auto-fills when you pick check-in above.</small>
                <span class="text-danger">{{ $errors->first("end_date")}}</span>
            </div>
        </div>
    </div>
</div>

<div class="tm-form-card">
    <h5>💬 Review text</h5>
    <div class="form-group">
        {!! Form::label("message", "What did the guest say?") !!}
        {!! Form::textarea("message", null, ["class" => "form-control", "rows" => 5, "placeholder" => "We loved every minute of our stay. The pool was spotless, the welcome basket was a delightful surprise, and the host was incredibly responsive…"]) !!}
        <small class="form-text">The full review text. Aim for 2–4 sentences — long enough to feel real, short enough to read at a glance.</small>
        <span class="text-danger">{{ $errors->first("message")}}</span>
    </div>
</div>

<div class="tm-form-card">
    <h5>📤 Publishing</h5>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Status</label>
                <div>
                    <label class="tm-toggle">
                        {!! Form::hidden("status", "false") !!}
                        {!! Form::checkbox("status", "true", ($data->status ?? 'false') === 'true', ["id" => "tm-status-toggle"]) !!}
                        <span class="tm-track"></span>
                        <span class="tm-toggle-label">Published (visible on the site)</span>
                    </label>
                </div>
                <small class="form-text">When OFF, the testimonial is a draft — not shown to visitors anywhere.</small>
                <span class="text-danger">{{ $errors->first("status")}}</span>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>Show on home page</label>
                <div>
                    <label class="tm-toggle">
                        {!! Form::hidden("is_home", "false") !!}
                        {!! Form::checkbox("is_home", "true", ($data->is_home ?? 'false') === 'true', ["id" => "tm-home-toggle"]) !!}
                        <span class="tm-track"></span>
                        <span class="tm-toggle-label">Featured on home-page testimonials carousel</span>
                    </label>
                </div>
                <small class="form-text">Only featured reviews appear on the home page. Property-specific reviews always show on that property's detail page regardless.</small>
                <span class="text-danger">{{ $errors->first("is_home")}}</span>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var picker = document.getElementById('tm-stars-picker');
    var input  = document.getElementById('tm-score-input');
    if (!picker || !input) return;
    function setRating(v) {
        input.value = v;
        picker.querySelectorAll('.tm-star').forEach(function (s) {
            var sv = parseInt(s.getAttribute('data-value'), 10);
            s.classList.toggle('active', sv <= v);
            s.setAttribute('aria-checked', sv === v ? 'true' : 'false');
        });
    }
    picker.querySelectorAll('.tm-star').forEach(function (s) {
        s.addEventListener('click', function () { setRating(parseInt(s.getAttribute('data-value'), 10)); });
        s.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setRating(parseInt(s.getAttribute('data-value'), 10)); }
        });
    });
})();
</script>
