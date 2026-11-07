<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyFee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyFeeController extends Controller
{
    public function store(Request $request, $property_id)
    {
        $data = $this->validateRequest($request);
        $data['property_id'] = (int) $property_id;
        $data['taxable']          = $request->boolean('taxable');
        $data['applies_discount'] = $request->boolean('applies_discount');
        $data['is_mandatory']     = $request->boolean('is_mandatory');
        PropertyFee::create($data);
        return redirect()->back()->with('success', 'Fee added.');
    }

    public function update(Request $request, $property_id, $fee_id)
    {
        $data = $this->validateRequest($request);
        $data['taxable']          = $request->boolean('taxable');
        $data['applies_discount'] = $request->boolean('applies_discount');
        $data['is_mandatory']     = $request->boolean('is_mandatory');
        PropertyFee::where('id', $fee_id)->where('property_id', $property_id)->update($data);
        return redirect()->back()->with('success', 'Fee updated.');
    }

    public function destroy($property_id, $fee_id)
    {
        PropertyFee::where('id', $fee_id)->where('property_id', $property_id)->delete();
        return redirect()->back()->with('success', 'Fee deleted.');
    }

    public function sort(Request $request, $property_id, $fee_id)
    {
        $request->validate(['direction' => 'required|in:up,down']);

        $fee = PropertyFee::where('property_id', $property_id)->findOrFail($fee_id);

        $neighbour = PropertyFee::where('property_id', $property_id)
            ->when($request->input('direction') === 'up',
                fn ($q) => $q->where('sort_order', '<', $fee->sort_order)->orderByDesc('sort_order'),
                fn ($q) => $q->where('sort_order', '>', $fee->sort_order)->orderBy('sort_order'),
            )
            ->first();

        if ($neighbour) {
            DB::transaction(function () use ($fee, $neighbour) {
                $a = $fee->sort_order;
                $b = $neighbour->sort_order;
                $fee->update(['sort_order' => $b]);
                $neighbour->update(['sort_order' => $a]);
            });
        }

        return redirect()->back()->with('success', 'Order updated.');
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'name'             => 'required|string|max:191',
            'rate'             => 'required|numeric|min:0',
            'billing_type'     => 'required|in:one_time,per_day',
            'taxable'          => 'nullable|boolean',
            'applies_discount' => 'nullable|boolean',
            'is_mandatory'     => 'nullable|boolean',
            'sort_order'       => 'nullable|integer|min:0',
            'status'           => 'required|in:active,inactive',
        ]);
    }
}
