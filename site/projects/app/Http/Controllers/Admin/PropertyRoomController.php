<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PropertyRoom;
use App\Models\PropertyAmenity;
use App\Models\Property;
use App\Helper\Upload;
use Validator;
use Carbon\Carbon;

class PropertyRoomController extends Controller{
    
    public function __construct(PropertyRoom $model){
        $this->model=$model;
        $this->admin_base_url="properties-group-rooms";
        $this->admin_view="admin.properties-group-rooms";
    }

    
    public function index($property_id){
        $property=Property::find($property_id);
        if($property):
            $data=$this->model::where("property_id",$property_id)->orderBy("id","desc")->get();

            return view($this->admin_view.".index",compact("data","property_id","property"));
        else:
            return redirect()->route("properties.index");
        endif;
    }

     public function active($property_id,$id){
        $data=$this->model::find($id);
        if($data){
            $data->room_status="true";
            $data->save();
            return redirect()->route($this->admin_base_url,[$property_id])->with("success","Successfully Active");
        }
        return redirect()->route($this->admin_base_url,[$property_id])->with("danger","Invalid Calling");
    }
    public function deactive($property_id,$id){
        $data=$this->model::find($id);
        if($data){
            $data->room_status="false";
            $data->save();
            return redirect()->route($this->admin_base_url,[$property_id])->with("success","Successfully Deactive");
        }
        return redirect()->route($this->admin_base_url,[$property_id])->with("danger","Invalid Calling");
    }

    
    public function create($property_id){
        $property=Property::find($property_id);
        if($property){}else{
            return redirect()->route("properties.index");
        }
        return view($this->admin_view.".create",compact("property_id","property"));
    }

    public function store(Request $request,$property_id){
        $property=Property::find($property_id);
        if($property){}else{
            return redirect()->route("properties.index");
        }
        $validator = Validator::make($request->all(), [
            'room_title' => 'required'
        ]);   
        if($validator->fails()){
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        $data=$request->all();
        if ($request->hasFile("image")) {
            $data['image'] = Upload::fileUpload($request->file("image"),"properties-group-rooms");
        }
        if ($request->hasFile("bannerImage")) {
            $data['bannerImage'] = Upload::fileUpload($request->file("bannerImage"),"properties-group-rooms");
        }
        $this->model::create($data);
        return redirect()->route($this->admin_base_url,[$property_id])->with("success","Successfully Added");
    }

  
    public function show($property_id,$id){
        $property=Property::find($property_id);
        if($property){}else{
            return redirect()->route("properties.index");
        }
        return redirect()->route($this->admin_base_url);
    }

    public function copyData($property_id,$id){
        $data=$this->model::find($id);
        if($data){
            
            $newPost = $data->replicate();
            $newPost->created_at = Carbon::now();
            $newPost->save();
            return redirect()->route($this->admin_base_url,[$property_id])->with("success","Successfully Coppied");
        }
        return redirect()->route($this->admin_base_url,[$property_id])->with("danger","Invalid Calling");
    }

    public function edit($property_id,$id){
        $property=Property::find($property_id);
        if($property){}else{
            return redirect()->route("properties.index");
        }
        $data=$this->model::find($id);
        if($data){
            return view($this->admin_view.".edit",compact("data","property_id","property"));
        }
        return redirect()->route($this->admin_base_url,[$property_id])->with("danger","Invalid Calling");
    }

  
    public function update(Request $request,$property_id, $id){
        $property=Property::find($property_id);
        if($property){}else{
            return redirect()->route("properties.index");
        }
        $validator = Validator::make($request->all(), [
            'room_title' => 'required'
        ]);   
        if($validator->fails()){
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        $exist=$this->model::find($id);
        if($exist){
            $data=$request->all();
            if ($request->hasFile("image")) {
                $data['image'] = Upload::fileUpload($request->file("image"),"properties-group-rooms");
            }
            if ($request->hasFile("bannerImage")) {
                $data['bannerImage'] = Upload::fileUpload($request->file("bannerImage"),"properties-group-rooms");
            }
            $this->model::find($id)->update($data);
            return redirect()->route($this->admin_base_url,[$property_id])->with("success","Successfully Updated");
        }
        return redirect()->route($this->admin_base_url,[$property_id])->with("danger","Invalid Calling");
    }

    public function destroy($property_id,$id){
        $property=Property::find($property_id);
        if($property){}else{
            return redirect()->route("properties.index");
        }
        $exist=$this->model::find($id);
        if($exist){
            //PropertyAmenity::where("property_amenity_id",$id)->delete();
            $exist->delete();
            return redirect()->route($this->admin_base_url,[$property_id])->with("success","Successfully Deleted");
        }
        return redirect()->route($this->admin_base_url,[$property_id])->with("danger","Invalid Calling");
    }
}
