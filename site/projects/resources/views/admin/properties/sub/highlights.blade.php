<h4>Property Highlights</h4>
<small class="text-muted d-block mb-2">Small icon + title + description rows shown in the property's "highlights" strip on the detail page. Drag rows by the grip handle to reorder.</small>

@isset($data)
<div class="row">
    <div class="col-md-12">
        <a href="javascript:;" class="add-highlight-data btn btn-outline-info"><i class="fa fa-plus"></i> Add Highlight</a>
        <hr />
    </div>
</div>
@endisset

<div class="row">
    <div class="col-md-1"></div>
    <div class="col-md-2"><strong>Icon</strong></div>
    <div class="col-md-3"><strong>Title</strong></div>
    <div class="col-md-5"><strong>Description</strong></div>
    <div class="col-md-1"><strong>Action</strong></div>
    <div class="col-md-12"><br></div>
</div>

<div id="highlights-area-section" data-property-id="{{ $data->id ?? '' }}" data-reorder-url="{{ isset($data) ? url('admin/properties/'.$data->id.'/highlights/reorder') : '' }}">
    @isset($data)
        @foreach(App\Models\PropertyHighlight::where("property_id",$data->id)->orderBy("sorting")->get() as $c)
            <div class="row gaurav-delete-highlight" data-id="{{ $c->id }}">
                <input type="hidden" name="highlight_id[]" value="{{ $c->id }}">
                <div class="col-md-1 d-flex align-items-center">
                    <span class="drag-handle text-muted" style="cursor:grab;font-size:18px;padding:6px 8px"><i class="fa fa-grip-vertical"></i></span>
                </div>
                <div class="col-md-2">
                    {!! Form::select("highlight_icon[]", ["layers"=>"layers","lock"=>"lock","mappin"=>"mappin"], $c->icon, ["class"=>"form-control"]) !!}
                </div>
                <div class="col-md-3">
                    {!! Form::text("highlight_title[]", $c->title, ["class"=>"form-control","placeholder"=>"Title"]) !!}
                </div>
                <div class="col-md-5">
                    {!! Form::text("highlight_description[]", $c->description, ["class"=>"form-control","placeholder"=>"Description"]) !!}
                </div>
                <div class="col-md-1">
                    <a href="javascript:;" class="delete-highlight-db btn btn-danger" data-id="{{ $c->id }}"><i class="fa fa-trash"></i></a>
                </div>
                <div class="col-md-12"><br></div>
            </div>
        @endforeach
    @endisset
</div>

@isset($data)
<script>
(function () {
    // FIX (Jun 18 2026): SortableJS loads at the bottom of the layout, but this ran inline
    // (before it loaded) and bailed on `!window.Sortable`, so drag-reorder never initialized.
    // Defer + wait for the lib, run even if DOMContentLoaded already fired, guard double-init.
    function init() {
        var el = document.getElementById('highlights-area-section');
        if (!el) return;
        if (typeof Sortable === 'undefined') return setTimeout(init, 200);
        if (el.dataset.sortableReady) return;
        el.dataset.sortableReady = '1';
        Sortable.create(el, {
            animation: 150, handle: '.drag-handle', ghostClass: 'drag-ghost',
            onEnd: function () {
                var ids = Array.from(el.querySelectorAll('.gaurav-delete-highlight[data-id]')).map(function (r) { return parseInt(r.dataset.id, 10); });
                if (!ids.length || !el.dataset.reorderUrl) return;
                fetch(el.dataset.reorderUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: ids })
                });
            }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>
@endisset
