<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AttractionCategory;
use App\Helper\Upload;
use Illuminate\Support\Str;
use Validator;
use Helper;

class AttractionCategoryController extends Controller{
    
    public function __construct(AttractionCategory $model){
        $this->model=$model;
        $this->admin_base_url="attraction-categories.index";
        $this->admin_view="admin.attraction-categories";
    }
    
    public function index(){
        $data=$this->model::orderBy("id","desc")->get();
        return view($this->admin_view.".index",compact("data"));
    }
    
    public function create(){
        return view($this->admin_view.".create");
    }

    public function store(Request $request){
        if (!trim((string) $request->input('seo_url')) && trim((string) $request->input('name'))) {
            $request->merge(['seo_url' => Str::slug($request->input('name'))]);
        }
        $validator = Validator::make($request->all(), ['seo_url' => 'required|unique:attraction_categories,seo_url','name' => 'required']);   
        if($validator->fails()){
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        $data=$request->all();
        // FIX (Jun 18 2026): attraction_categories.templete is NOT NULL with no default and the
        // create form doesn't post it → Add Category 500'd. Default templete/publish (quickCreate
        // already did this; the standard store() did not).
        $data['templete'] = $data['templete'] ?? 'common';
        $data['publish']  = $data['publish'] ?? 'published';
        if ($request->hasFile("image")) {
            $data['image'] = Upload::fileUpload($request->file("image"),"attraction-categories");
        }
        if ($request->hasFile("attraction_image")) {
            $data['attraction_image'] = Upload::fileUpload($request->file("attraction_image"),"attraction-categories");
        }
        if ($request->hasFile("bannerImage")) {
            $data['bannerImage'] = Upload::fileUpload($request->file("bannerImage"),"attraction-categories");
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
            $request->merge(['seo_url' => Str::slug($request->input('name'))]);
        }
        $validator = Validator::make($request->all(), ['seo_url' => 'required|unique:attraction_categories,seo_url,'.$id,'name' => 'required']);   
        if($validator->fails()){
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        $exist=$this->model::find($id);
        if($exist){
            $data=$request->all();
            $data['templete'] = $data['templete'] ?? 'common';
            $data['publish']  = $data['publish'] ?? 'published';
            if ($request->hasFile("image")) {
                Helper::deleteFile($exist->image);
                $data['image'] = Upload::fileUpload($request->file("image"),"attraction-categories");
            }
            if ($request->hasFile("attraction_image")) {
                Helper::deleteFile($exist->attraction_image);
                $data['attraction_image'] = Upload::fileUpload($request->file("attraction_image"),"attraction-categories");
            }
            if ($request->hasFile("bannerImage")) {
                Helper::deleteFile($exist->bannerImage);
                $data['bannerImage'] = Upload::fileUpload($request->file("bannerImage"),"attraction-categories");
            }
            if($request->remove_attraction_image){
                $data['attraction_image'] ='';
                Helper::deleteFile($exist->attraction_image);
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
            Helper::deleteFile($exist->attraction_image);
            Helper::deleteFile($exist->bannerImage);
            Helper::deleteFile($exist->image);
            $exist->delete();
            return redirect()->route($this->admin_base_url)->with("success","Successfully Deleted");
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }

    /**
     * Phase 11 — Inline-create endpoint. Returns JSON for the modal flow.
     */
    public function quickCreate(Request $request)
    {
        $request->merge([
            'seo_url' => $request->seo_url ?: Str::slug((string) $request->name),
        ]);
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'seo_url' => 'required|string|max:255|regex:/^[a-zA-Z0-9-_]+$/|unique:attraction_categories,seo_url',
            'image'   => 'nullable|file|mimes:jpeg,png,jpg,webp|max:4096',
        ]);
        $payload = [
            'name'     => $validated['name'],
            'seo_url'  => $validated['seo_url'],
            'templete' => 'common',
            'publish'  => 'published',
        ];
        if ($request->hasFile('image')) {
            $payload['image'] = Upload::fileUpload($request->file('image'), 'attraction-categories');
        }
        $row = $this->model::create($payload);
        return response()->json([
            'id'    => $row->id,
            'name'  => $row->name,
            'image' => $row->image ? asset($row->image) : null,
        ]);
    }
}
