<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attraction;
use App\Helper\Upload;
use Validator;
use Helper;

class AttractionController extends Controller{
    
    public function __construct(Attraction $model){
        $this->model=$model;
        $this->admin_base_url="attractions.index";
        $this->admin_view="admin.attractions";
    }
    
    public function index(){
        $data=$this->model::orderBy("id","desc")->get();
        return view($this->admin_view.".index",compact("data"));
    }
    
    public function create(){
        return view($this->admin_view.".create");
    }

    public function store(Request $request){
        // Auto-derive seo_url from the name when the admin leaves the URL field
        // blank. Previously a blank URL failed the `seo_url` required rule and
        // redirected back with a flash the create page doesn't render, so the
        // save looked like a silent no-op (QA D-004).
        if (!trim((string) $request->input('seo_url')) && trim((string) $request->input('name'))) {
            $request->merge(['seo_url' => \Illuminate\Support\Str::slug($request->input('name'))]);
        }
        $validator = Validator::make($request->all(), ['seo_url' => 'required','name' => 'required']);
        if($validator->fails()){
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        $data=$request->all();
        // `templete` is NOT NULL without a default (legacy schema) — the admin
        // form doesn't send it, so default it to avoid a DB-level 500.
        $data['templete'] = $data['templete'] ?? '';
        // `ordering` no longer client-side required — default blank to 0.
        if (!isset($data['ordering']) || $data['ordering'] === '') {
            $data['ordering'] = 0;
        }
        if ($request->hasFile("image")) {
            $data['image'] = Upload::fileUpload($request->file("image"),"attractions");
        }
        if ($request->hasFile("bannerImage")) {
            $data['bannerImage'] = Upload::fileUpload($request->file("bannerImage"),"attractions");
        }
        $this->model::create($data);
        return redirect()->route($this->admin_base_url)->with("success","Successfully Added");
    }
  
    public function show($id){
        return redirect()->route($this->admin_base_url);
    }
   
    public function edit($id){
        $data=$this->model::find($id);
        if($data){
            return view($this->admin_view.".edit",compact("data"));
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }
  
    public function update(Request $request, $id){
        if (!trim((string) $request->input('seo_url')) && trim((string) $request->input('name'))) {
            $request->merge(['seo_url' => \Illuminate\Support\Str::slug($request->input('name'))]);
        }
        $validator = Validator::make($request->all(), ['seo_url' => 'required','name' => 'required']);
        if($validator->fails()){
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        $exist=$this->model::find($id);
        if($exist){
            $data=$request->all();
            $data['templete'] = $data['templete'] ?? '';
            if (!isset($data['ordering']) || $data['ordering'] === '') {
                $data['ordering'] = 0;
            }
            if ($request->hasFile("image")) {
                Helper::deleteFile($exist->image);
                $data['image'] = Upload::fileUpload($request->file("image"),"attractions");
            }
            if ($request->hasFile("bannerImage")) {
                Helper::deleteFile($exist->bannerImage);
                $data['bannerImage'] = Upload::fileUpload($request->file("bannerImage"),"attractions");
            }
            if($request->remove_banner_image){
                $data['bannerImage'] ='';
                Helper::deleteFile($exist->bannerImage);
            }
            if($request->remove_image){
                $data['image'] ='';
                Helper::deleteFile($exist->image);
            }
            $this->model::find($id)->update($data);
            return redirect()->route($this->admin_base_url)->with("success","Successfully Updated");
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }

    public function destroy($id){
        $exist=$this->model::find($id);
        if($exist){
            Helper::deleteFile($exist->bannerImage);
            Helper::deleteFile($exist->image);
            $exist->delete();
            return redirect()->route($this->admin_base_url)->with("success","Successfully Deleted");
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }
}
