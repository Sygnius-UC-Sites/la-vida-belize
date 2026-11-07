<?php

namespace App\Modules\MultiPms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\MultiPms\Models\PmsRoom;
use App\Modules\MultiPms\Pms\PmsManager;
use Illuminate\Http\Request;

class PmsPropertyController extends Controller
{
    public function index()
    {
        $properties = PmsRoom::orderBy('pms_source')->orderBy('name')->paginate(50);
        return view('multi-pms::admin.properties.index', compact('properties'));
    }

    public function show($id)
    {
        $property = PmsRoom::findOrFail($id);
        return view('multi-pms::admin.properties.show', compact('property'));
    }
}
