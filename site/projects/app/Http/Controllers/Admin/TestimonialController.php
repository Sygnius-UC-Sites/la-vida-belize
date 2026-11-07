<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Testimonial;
use App\Helper\Upload;
use Carbon\Carbon;
use Helper;
use App\Models\Property;

class TestimonialController extends Controller
{

    public function __construct(Testimonial $model)
    {
        $this->model = $model;
        $this->admin_base_url = "testimonials.index";
        $this->admin_view = "admin.testimonials";
    }
    public function copyData($id)
    {
        $data = $this->model::find($id);
        if ($data) {

            $newPost = $data->replicate();
            $newPost->created_at = Carbon::now();
            $newPost->save();
            return redirect()->route($this->admin_base_url)->with("success", "Successfully Coppied");
        }
        return redirect()->route($this->admin_base_url)->with("danger", "Invalid Calling");
    }


    public function index()
    {
        // Filter out 'pending-link-sent' rows — those are pre-created by SendReviewEmails
        // for the magic-link flow and represent guests who haven't submitted yet.
        $data = $this->model::where('status', '!=', 'pending-link-sent')
            ->orderBy("id", "desc")->get();
        return view($this->admin_view . ".index", compact("data"));
    }


    public function create()
    {
        $properties = Property::where('status', 'true')->orderBy('name')->get();
        return view($this->admin_view . ".create", compact("properties"));
    }

    public function store(Request $request)
    {
        $data = $request->all();
        if ($request->hasFile("image")) {
            $data['image'] = Upload::fileUpload($request->file("image"), "testimonials");
        }
        if ($request->hasFile("bannerImage")) {
            $data['bannerImage'] = Upload::fileUpload($request->file("bannerImage"), "testimonials");
        }
        $this->model::create($data);
        return redirect()->route($this->admin_base_url)->with("success", "Successfully Added");
    }


    public function show($id)
    {
        return redirect()->route($this->admin_base_url);
    }


    public function edit($id)
    {
        $data = $this->model::find($id);
        if ($data) {
            $properties = Property::where('status', 'true')->orderBy('name')->get();
            return view($this->admin_view . ".edit", compact("data", "properties"));
        }
        return redirect()->route($this->admin_base_url)->with("danger", "Invalid Calling");
    }


    public function update(Request $request, $id)
    {
        $exist = $this->model::find($id);
        if ($exist) {
            $data = $request->all();
            if ($request->hasFile("image")) {
                Helper::deleteFile($exist->image);
                $data['image'] = Upload::fileUpload($request->file("image"), "testimonials");
            }

            if ($request->remove_image) {
                $data['image'] = '';
                Helper::deleteFile($exist->image);
            }

            $this->model::find($id)->update($data);
            return redirect()->route($this->admin_base_url)->with("success", "Successfully Updated");
        }
        return redirect()->route($this->admin_base_url)->with("danger", "Invalid Calling");
    }

    public function destroy($id)
    {
        $exist = $this->model::find($id);
        if ($exist) {
            Helper::deleteFile($exist->image);
            $exist->delete();
            return redirect()->route($this->admin_base_url)->with("success", "Successfully Deleted");
        }
        return redirect()->route($this->admin_base_url)->with("danger", "Invalid Calling");
    }
}
