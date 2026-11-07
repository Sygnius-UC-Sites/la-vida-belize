@extends('admin.layouts')
@section('title', 'Property Categories')
@section('content')
@parent
<section class="content">
    <div class="ca-page-toolbar">
        <div class="ca-page-toolbar-title"><h1>Property categories</h1></div>
        <a href="{{ route('property-categories.create') }}" class="ca-page-toolbar-action">
            <i class="fa fa-plus"></i> Add category
        </a>
    </div>
    <div class="callout callout-info">
        Categories are optional owner-defined groupings. They do not change a property's canonical URL.
        If no active categories exist, the guest website shows no category division.
    </div>
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead><tr><th>Name</th><th>Slug</th><th>Properties</th><th>Status</th><th>Order</th><th></th></tr></thead>
                <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td><strong>{{ $category->name }}</strong></td>
                        <td><code>{{ $category->slug }}</code></td>
                        <td>{{ $category->properties_count }}</td>
                        <td><span class="badge badge-{{ $category->status ? 'success' : 'secondary' }}">{{ $category->status ? 'Active' : 'Hidden' }}</span></td>
                        <td>{{ $category->sort_order }}</td>
                        <td class="text-right">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('property-categories.edit', $category) }}">Edit</a>
                            <form class="d-inline" method="post" action="{{ route('property-categories.destroy', $category) }}" onsubmit="return confirm('Delete this category? Properties will remain.');">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No categories. Properties are shown in one list.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@stop
