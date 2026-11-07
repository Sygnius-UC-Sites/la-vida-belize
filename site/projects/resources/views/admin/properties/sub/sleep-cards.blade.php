<h4>Sleep Cards</h4>
<small class="text-muted d-block mb-2">Bedroom / bed cards rendered under the Sleep section heading above on the detail page. Drag rows by the grip handle to reorder.</small>

@isset($data)
<div class="row">
    <div class="col-md-12">
        <a href="javascript:;" class="add-sleep-card-data btn btn-outline-info"><i class="fa fa-plus"></i> Add Sleep Card</a>
        <hr />
    </div>
</div>
@endisset

<div class="row">
    <div class="col-md-1"></div>
    <div class="col-md-4"><strong>Title</strong></div>
    <div class="col-md-6"><strong>Text</strong></div>
    <div class="col-md-1"><strong>Action</strong></div>
    <div class="col-md-12"><br></div>
</div>

<div id="sleep-cards-area-section" data-reorder-url="{{ isset($data) ? url('admin/properties/'.$data->id.'/sleep-cards/reorder') : '' }}">
    @isset($data)
        @foreach(App\Models\PropertySleepCard::where("property_id",$data->id)->orderBy("sorting")->get() as $c)
            <div class="row gaurav-delete-sleep-card" data-id="{{ $c->id }}">
                <input type="hidden" name="sleep_card_id[]" value="{{ $c->id }}">
                <div class="col-md-1 d-flex align-items-center">
                    <span class="drag-handle text-muted" style="cursor:grab;font-size:18px;padding:6px 8px"><i class="fa fa-grip-vertical"></i></span>
                </div>
                <div class="col-md-4">
                    {!! Form::text("sleep_card_title[]", $c->title, ["class"=>"form-control","placeholder"=>"Title"]) !!}
                </div>
                <div class="col-md-6">
                    {!! Form::text("sleep_card_text[]", $c->text, ["class"=>"form-control","placeholder"=>"Text"]) !!}
                </div>
                <div class="col-md-1">
                    <a href="javascript:;" class="delete-sleep-card-db btn btn-danger" data-id="{{ $c->id }}"><i class="fa fa-trash"></i></a>
                </div>
                <div class="col-md-12"><br></div>
            </div>
        @endforeach
    @endisset
</div>

@isset($data)
<script>
(function () {
    // FIX (Jun 18 2026): see highlights — deferred/resilient init so drag-reorder works even
    // though SortableJS loads after this script in the page.
    function init() {
        var el = document.getElementById('sleep-cards-area-section');
        if (!el) return;
        if (typeof Sortable === 'undefined') return setTimeout(init, 200);
        if (el.dataset.sortableReady) return;
        el.dataset.sortableReady = '1';
        Sortable.create(el, {
            animation: 150, handle: '.drag-handle', ghostClass: 'drag-ghost',
            onEnd: function () {
                var ids = Array.from(el.querySelectorAll('.gaurav-delete-sleep-card[data-id]')).map(function (r) { return parseInt(r.dataset.id, 10); });
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
