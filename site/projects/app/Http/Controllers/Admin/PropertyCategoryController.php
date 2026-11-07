<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PropertyCategoryController extends Controller
{
    public function index()
    {
        $categories = PropertyCategory::withCount('properties')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.property-categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.property-categories.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        PropertyCategory::create($data);

        return redirect()->route('property-categories.index')
            ->with('success', 'Property category created.');
    }

    public function edit(PropertyCategory $propertyCategory)
    {
        return view('admin.property-categories.edit', compact('propertyCategory'));
    }

    public function update(Request $request, PropertyCategory $propertyCategory)
    {
        $propertyCategory->update($this->validated($request, $propertyCategory));

        return redirect()->route('property-categories.index')
            ->with('success', 'Property category updated.');
    }

    public function destroy(PropertyCategory $propertyCategory)
    {
        $propertyCategory->delete();

        return redirect()->route('property-categories.index')
            ->with('success', 'Property category deleted. Properties were not deleted.');
    }

    private function validated(
        Request $request,
        ?PropertyCategory $category = null
    ): array {
        $request->merge([
            'slug' => Str::slug($request->input('slug') ?: $request->input('name')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'alpha_dash',
                'max:100',
                Rule::unique('property_categories', 'slug')->ignore($category?->id),
                function ($attribute, $value, $fail) {
                    $reserved = [
                        'admin', 'api', 'booking', 'gallery', 'login', 'logout',
                        'pms', 'stays', 'webhooks',
                    ];
                    if (in_array($value, $reserved, true)
                        || \App\Models\Cms::where('seo_url', $value)->exists()
                        || \App\Models\Property::where('seo_url', $value)->exists()
                    ) {
                        $fail('This URL slug is already used by another page.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        return $data;
    }
}
