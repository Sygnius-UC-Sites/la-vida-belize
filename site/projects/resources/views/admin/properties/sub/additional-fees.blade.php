@php
    $fees = isset($data) && $data->id
        ? \App\Models\PropertyFee::where('property_id', $data->id)->orderBy('sort_order')->orderBy('id')->get()
        : collect();
@endphp

<h4>Add-ons &amp; extra fees</h4>
<p class="text-muted">
    This is where you define per-property <strong>add-ons</strong> — Pet fee, Late checkout, Hot tub, Firewood, etc.
    Each add-on has a <strong>name</strong>, a <strong>price</strong>, a billing type (one-time / per day),
    a <strong>taxable</strong> toggle, and a <strong>mandatory</strong> toggle.
    The flat per-stay <em>Cleaning fee</em> has its own dedicated field on the
    <a href="#pricing">Pricing &amp; taxes</a> tab — don't double up on it here.
</p>
<ul class="text-muted small mb-3">
    <li><strong>Optional add-ons</strong> appear on the booking widget as a checkbox guests opt in to.</li>
    <li><strong>Mandatory add-ons</strong> (e.g. Damage Insurance, Resort Fee) are always charged — guests see the line item but can't unselect it.</li>
    <li>The <strong>taxable</strong> flag controls whether the property's tax % is added on top of this add-on.</li>
</ul>

@if (!isset($data) || !$data->id)
    <div class="alert alert-info">Save the property first to add additional fees.</div>
@elseif ($fees->isEmpty())
    <div class="card card-light">
        <div class="card-body">
            <p class="mb-2"><strong>No optional fees configured for this property yet.</strong></p>
            <p class="text-muted small mb-2">Examples of what owners typically add:</p>
            <ul class="text-muted small mb-3">
                <li>Beach gear rental — $20 / night</li>
                <li>Pool heating — $75 / night</li>
                <li>Late checkout (3pm) — $50 one-time</li>
                <li>Firewood bundle — $30 one-time</li>
                <li>Hot tub heating — $50 one-time</li>
            </ul>
            <button type="button" class="btn btn-primary" data-fee-add-toggle>
                <i class="fa fa-plus"></i> Add your first fee
            </button>
        </div>
    </div>
@else
    @foreach ($fees as $fee)
        <div class="card mb-2 fee-row" data-fee-id="{{ $fee->id }}" data-mode="view">
            {{-- View mode --}}
            <div class="card-body py-2" data-view-block>
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <strong>{{ $fee->name }}</strong>
                        @if ($fee->status === 'inactive')
                            <span class="badge badge-secondary ml-1">Disabled</span>
                        @else
                            <span class="badge badge-success ml-1">Active</span>
                        @endif
                        <div class="text-muted small">
                            ${{ number_format($fee->rate, 2) }}
                            &middot; {{ $fee->billing_type === 'per_day' ? 'Per day' : 'One-time' }}
                            &middot; {{ $fee->taxable ? 'Taxable' : 'Not taxable' }}
                            &middot; LOS discount: {{ $fee->applies_discount ? 'yes' : 'no' }}
                        </div>
                    </div>
                    <div class="col-md-6 text-right">
                        {{-- Sort up --}}
                        <form method="POST" action="{{ url('admin/properties/'.$data->id.'/fees/'.$fee->id.'/sort') }}" style="display:inline;">
                            @csrf
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Move up" {{ $loop->first ? 'disabled' : '' }}>↑</button>
                        </form>
                        {{-- Sort down --}}
                        <form method="POST" action="{{ url('admin/properties/'.$data->id.'/fees/'.$fee->id.'/sort') }}" style="display:inline;">
                            @csrf
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Move down" {{ $loop->last ? 'disabled' : '' }}>↓</button>
                        </form>
                        {{-- Edit toggle --}}
                        <button type="button" class="btn btn-sm btn-outline-primary" data-fee-edit-toggle>
                            <i class="fa fa-pencil-alt"></i> Edit
                        </button>
                        {{-- Disable / Enable --}}
                        <form method="POST" action="{{ url('admin/properties/'.$data->id.'/fees/'.$fee->id) }}" style="display:inline;">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="name"             value="{{ $fee->name }}">
                            <input type="hidden" name="rate"             value="{{ $fee->rate }}">
                            <input type="hidden" name="billing_type"     value="{{ $fee->billing_type }}">
                            <input type="hidden" name="sort_order"       value="{{ $fee->sort_order }}">
                            @if ($fee->taxable)         <input type="hidden" name="taxable" value="1"> @endif
                            @if ($fee->applies_discount) <input type="hidden" name="applies_discount" value="1"> @endif
                            @if ($fee->is_mandatory)    <input type="hidden" name="is_mandatory" value="1"> @endif
                            <input type="hidden" name="status" value="{{ $fee->status === 'active' ? 'inactive' : 'active' }}">
                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                {{ $fee->status === 'active' ? 'Disable' : 'Enable' }}
                            </button>
                        </form>
                        {{-- Delete --}}
                        <form method="POST" action="{{ url('admin/properties/'.$data->id.'/fees/'.$fee->id) }}" style="display:inline;" onsubmit="return confirm('Delete this fee?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i> Delete</button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Edit mode --}}
            <div class="card-body" data-edit-block style="display:none;">
                <form method="POST" action="{{ url('admin/properties/'.$data->id.'/fees/'.$fee->id) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="sort_order" value="{{ $fee->sort_order }}">
                    <div class="row">
                        <div class="col-md-5 form-group">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control" required maxlength="191" value="{{ $fee->name }}">
                            <small class="text-muted">What the guest sees on the booking widget checkbox (e.g. 'Beach gear rental').</small>
                        </div>
                        <div class="col-md-2 form-group">
                            <label>Rate ($)</label>
                            <input type="number" step="0.01" min="0" name="rate" class="form-control" required value="{{ $fee->rate }}">
                            <small class="text-muted">Dollar amount charged when the guest opts in.</small>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Billing</label>
                            <select name="billing_type" class="form-control" required>
                                <option value="one_time" @selected($fee->billing_type === 'one_time')>One-time</option>
                                <option value="per_day"  @selected($fee->billing_type === 'per_day')>Per day</option>
                            </select>
                            <small class="text-muted">Per day = rate × nights. One-time = flat fee, charged once for the whole stay.</small>
                        </div>
                        <div class="col-md-2 form-group">
                            <label>Status</label>
                            <select name="status" class="form-control" required>
                                <option value="active"   @selected($fee->status === 'active')>Active</option>
                                <option value="inactive" @selected($fee->status === 'inactive')>Disabled</option>
                            </select>
                            <small class="text-muted">Disabled fees don't appear on the booking widget. Use this to hide a fee without deleting it.</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="d-block"><input type="checkbox" name="taxable" value="1" @checked($fee->taxable)> Taxable</label>
                            <small class="text-muted">If checked, the property's tax % applies to this fee.</small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="d-block"><input type="checkbox" name="applies_discount" value="1" @checked($fee->applies_discount)> Apply length-of-stay discount?</label>
                            <small class="text-muted">If checked, the length-of-stay discount applies to this fee.</small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="d-block"><input type="checkbox" name="is_mandatory" value="1" @checked($fee->is_mandatory)> Mandatory (always charged)</label>
                            <small class="text-muted">If checked, this fee is always added to the price (not optional). Use for things like Damage Insurance or Resort Fee. Mandatory fees show on the property widget; optional fees only appear at checkout.</small>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-secondary" data-fee-edit-cancel>Cancel</button>
                </form>
            </div>
        </div>
    @endforeach

    <button type="button" class="btn btn-primary mt-2" data-fee-add-toggle>
        <i class="fa fa-plus"></i> Add fee
    </button>
@endif

{{-- Add-form (hidden until [+ Add fee] is clicked) --}}
@if (isset($data) && $data->id)
    <div class="card card-info mt-3" id="fee-add-form" style="display:none;">
        <div class="card-body">
            <h5>Add a new fee</h5>
            <form method="POST" action="{{ url('admin/properties/'.$data->id.'/fees') }}">
                @csrf
                <div class="row">
                    <div class="col-md-5 form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" required maxlength="191" placeholder="e.g. Beach gear rental">
                        <small class="text-muted">What the guest sees on the booking widget checkbox (e.g. 'Beach gear rental').</small>
                    </div>
                    <div class="col-md-2 form-group">
                        <label>Rate ($)</label>
                        <input type="number" step="0.01" min="0" name="rate" class="form-control" required>
                        <small class="text-muted">Dollar amount charged when the guest opts in.</small>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Billing</label>
                        <select name="billing_type" class="form-control" required>
                            <option value="one_time">One-time</option>
                            <option value="per_day">Per day</option>
                        </select>
                        <small class="text-muted">Per day = rate × nights. One-time = flat fee, charged once for the whole stay.</small>
                    </div>
                    <div class="col-md-2 form-group">
                        <label>Status</label>
                        <select name="status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Disabled</option>
                        </select>
                        <small class="text-muted">Disabled fees don't appear on the booking widget. Use this to hide a fee without deleting it.</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label class="d-block"><input type="checkbox" name="taxable" value="1" checked> Taxable</label>
                        <small class="text-muted">If checked, the property's tax % applies to this fee.</small>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="d-block"><input type="checkbox" name="applies_discount" value="1"> Apply length-of-stay discount?</label>
                        <small class="text-muted">If checked, the length-of-stay discount applies to this fee.</small>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="d-block"><input type="checkbox" name="is_mandatory" value="1"> Mandatory (always charged)</label>
                        <small class="text-muted">If checked, this fee is always added to the price (not optional). Use for things like Damage Insurance or Resort Fee. Mandatory fees show on the property widget; optional fees only appear at checkout.</small>
                    </div>
                </div>
                <input type="hidden" name="sort_order" value="{{ $fees->count() }}">
                <button type="submit" class="btn btn-primary">Save fee</button>
                <button type="button" class="btn btn-secondary" data-fee-add-cancel>Cancel</button>
            </form>
        </div>
    </div>
@endif

<script>
(function () {
    function closeAllEditModes() {
        document.querySelectorAll('.fee-row[data-mode="edit"]').forEach(function (row) {
            row.setAttribute('data-mode', 'view');
            row.querySelector('[data-view-block]').style.display = '';
            row.querySelector('[data-edit-block]').style.display = 'none';
        });
    }

    document.addEventListener('click', function (e) {
        // Edit toggle on a row
        if (e.target.closest('[data-fee-edit-toggle]')) {
            var row = e.target.closest('.fee-row');
            if (!row) return;
            closeAllEditModes();
            row.setAttribute('data-mode', 'edit');
            row.querySelector('[data-view-block]').style.display = 'none';
            row.querySelector('[data-edit-block]').style.display = '';
            return;
        }

        // Cancel edit on a row
        if (e.target.closest('[data-fee-edit-cancel]')) {
            var row = e.target.closest('.fee-row');
            if (!row) return;
            row.setAttribute('data-mode', 'view');
            row.querySelector('[data-view-block]').style.display = '';
            row.querySelector('[data-edit-block]').style.display = 'none';
            return;
        }

        // Toggle add-form
        if (e.target.closest('[data-fee-add-toggle]')) {
            var addForm = document.getElementById('fee-add-form');
            if (addForm) addForm.style.display = (addForm.style.display === 'none' ? '' : 'none');
            return;
        }

        // Cancel add-form
        if (e.target.closest('[data-fee-add-cancel]')) {
            var addForm = document.getElementById('fee-add-form');
            if (addForm) addForm.style.display = 'none';
            return;
        }
    });
})();
</script>
