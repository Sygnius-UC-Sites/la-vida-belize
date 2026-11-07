<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OurTeam;
use App\Helper\Upload;
use Helper;

class OurTeamController extends Controller
{

    public function __construct(OurTeam $model)
    {
        $this->model = $model;
        $this->admin_base_url = "our-teams.index";
        $this->admin_view = "admin.our-teams";
    }


    public function index()
    {
        $data = $this->model::orderBy("id", "desc")->get();
        return view($this->admin_view . ".index", compact("data"));
    }


    public function create()
    {
        return view($this->admin_view . ".create");
    }

    public function store(Request $request)
    {
        $data = $request->all();
        if ($request->hasFile("image")) {
            $data['image'] = Upload::fileUpload($request->file("image"), "our-teams");
        }
        if ($request->hasFile("bannerImage")) {
            $data['bannerImage'] = Upload::fileUpload($request->file("bannerImage"), "our-teams");
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
            return view($this->admin_view . ".edit", compact("data"));
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
                $data['image'] = Upload::fileUpload($request->file("image"), "our-teams");
            }

            if ($request->remove_image) {
                $data['image'] = '';
                Helper::deleteFile($exist->image);
            }

            if ($request->hasFile("bannerImage")) {
                $data['bannerImage'] = Upload::fileUpload($request->file("bannerImage"), "our-teams");
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
            $exist->delete();
            Helper::deleteFile($exist->image);
            return redirect()->route($this->admin_base_url)->with("success", "Successfully Deleted");
        }
        return redirect()->route($this->admin_base_url)->with("danger", "Invalid Calling");
    }
}
