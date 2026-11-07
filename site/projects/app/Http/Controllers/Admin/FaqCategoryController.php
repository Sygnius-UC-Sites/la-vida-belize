<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FaqCategory;
use Illuminate\Support\Str;
use Validator;

class FaqCategoryController extends Controller{

    public function __construct(FaqCategory $model){
        $this->model=$model;
        $this->admin_base_url="faq-categories.index";
        $this->admin_view="admin.faq-categories";
    }

    public function index(){
        $data=$this->model::orderBy("ordering","asc")->orderBy("id","asc")->get();
        return view($this->admin_view.".index",compact("data"));
    }

    public function create(){
        return view($this->admin_view.".create");
    }

    public function store(Request $request){
        $this->normaliseCategoryInput($request);
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'seo_url' => 'required|unique:faq_categories,seo_url',
            'ordering' => 'nullable|integer|min:0',
            'publish' => 'nullable|in:published,draft',
        ]);
        if($validator->fails()){
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        $this->model::create($request->only(['name', 'seo_url', 'ordering', 'publish']));
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
        $this->normaliseCategoryInput($request);
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'seo_url' => 'required|unique:faq_categories,seo_url,'.$id,
            'ordering' => 'nullable|integer|min:0',
            'publish' => 'nullable|in:published,draft',
        ]);
        if($validator->fails()){
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        $exist=$this->model::find($id);
        if($exist){
            $oldSlug = $exist->seo_url;
            $exist->update($request->only(['name', 'seo_url', 'ordering', 'publish']));
            // Keep existing FAQs in sync if the slug changed (faqs.type stores the slug).
            if ($oldSlug && $oldSlug !== $exist->seo_url) {
                \App\Models\Faq::where('type', $oldSlug)->update(['type' => $exist->seo_url]);
            }
            return redirect()->route($this->admin_base_url)->with("success","Successfully Updated");
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }

    public function destroy($id){
        $exist=$this->model::find($id);
        if($exist){
            $exist->delete();
            return redirect()->route($this->admin_base_url)->with("success","Successfully Deleted");
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }

    private function normaliseCategoryInput(Request $request): void
    {
        $request->merge([
            'seo_url' => $request->seo_url ?: Str::slug((string) $request->name),
            'ordering' => $request->input('ordering') === '' || $request->input('ordering') === null
                ? 0
                : $request->input('ordering'),
            'publish' => $request->input('publish') ?: 'published',
        ]);
    }
}
