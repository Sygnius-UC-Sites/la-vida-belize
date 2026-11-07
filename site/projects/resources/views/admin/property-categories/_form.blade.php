@csrf
<div class="card-body">
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <div class="form-group">
        <label for="category-name">Name</label>
        <input id="category-name" class="form-control" name="name" required
               value="{{ old('name', $propertyCategory->name ?? '') }}"
               placeholder="e.g. Cabins, Beachfront, Large Groups">
        <small class="text-muted">Shown only when you create a division. With no categories, properties stay in one undivided list.</small>
    </div>
    <div class="form-group">
        <label for="category-slug">URL slug</label>
        <input id="category-slug" class="form-control" name="slug"
               value="{{ old('slug', $propertyCategory->slug ?? '') }}"
               placeholder="Generated from the name if left blank">
    </div>
    <div class="form-group">
        <label for="category-description">Description</label>
        <textarea id="category-description" class="form-control" name="description" rows="3">{{ old('description', $propertyCategory->description ?? '') }}</textarea>
    </div>
    <div class="row">
        <div class="col-md-6 form-group">
            <label for="category-status">Status</label>
            <select id="category-status" class="form-control" name="status">
                <option value="1" @selected((string) old('status', isset($propertyCategory) ? (int) $propertyCategory->status : 1) === '1')>Active</option>
                <option value="0" @selected((string) old('status', isset($propertyCategory) ? (int) $propertyCategory->status : 1) === '0')>Hidden</option>
            </select>
        </div>
        <div class="col-md-6 form-group">
            <label for="category-sort">Display order</label>
            <input id="category-sort" class="form-control" type="number" min="0"
                   name="sort_order" value="{{ old('sort_order', $propertyCategory->sort_order ?? 0) }}">
        </div>
    </div>
</div>
<div class="card-footer">
    <button class="btn btn-success"><i class="fa fa-save"></i> Save category</button>
    <a class="btn btn-outline-secondary" href="{{ route('property-categories.index') }}">Cancel</a>
</div>
