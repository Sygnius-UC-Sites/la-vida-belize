<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cms;
use App\Helper\Upload;
use Validator;
use Helper;

class CmsController extends Controller
{

    public function __construct(Cms $model)
    {
        $this->model = $model;
        $this->admin_base_url = "cms.index";
        $this->admin_view = "admin.cms";
    }

    public function index()
    {
        $guestSeo = Cms::guestSeoUrls();
        $data = $this->model::query()->get()->sortBy(function ($row) use ($guestSeo) {
            $idx = array_search($row->seo_url, $guestSeo, true);
            return $idx === false ? (1000 + (int) $row->id) : $idx;
        })->values();

        return view($this->admin_view . ".index", compact("data", "guestSeo"));
    }

    public function create()
    {
        return view($this->admin_view . ".create");
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), ['seo_url' => 'required|unique:cms,seo_url', 'name' => 'required']);
        if ($validator->fails()) {
            return redirect()->back()->withInput()->with("danger", $validator->errors()->first())->withErrors($validator->errors());
        }
        $data = $request->all();
        // FIX (Jun 18 2026): cms.templete is NOT NULL with no default; the Add-page form doesn't
        // post it, so creating a CMS page 500'd. Default it (existing pages are seeded / the
        // booking-flow firstOrCreate set it; only the admin "Add" path missed it).
        $data['templete'] = $data['templete'] ?? 'common';
        $data['publish']  = $data['publish'] ?? 'published';
        if ($request->hasFile("image")) {
            $data['image'] = Upload::fileUpload($request->file("image"), "cms");
        }
        if ($request->hasFile("bannerImage")) {
            $data['bannerImage'] = Upload::fileUpload($request->file("bannerImage"), "cms");
        }
        foreach (['story_image', 'story_image_accent', 'location_image', 'story_block_image', 'inner_banner_image'] as $imgField) {
            if ($request->hasFile($imgField)) {
                $data[$imgField] = Upload::fileUpload($request->file($imgField), "cms");
            }
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
        $validator = Validator::make($request->all(), ['seo_url' => 'required|unique:cms,seo_url,' . $id, 'name' => 'required']);
        if ($validator->fails()) {
            return redirect()->back()->withInput()->with("danger", $validator->errors()->first())->withErrors($validator->errors());
        }
        $exist = $this->model::find($id);
        if ($exist) {
            $data = $request->all();
            $templateOptions = Helper::getTempletes();

            if (!empty($exist->templete) && !array_key_exists((string) $exist->templete, $templateOptions)) {
                $data['templete'] = $exist->templete;
            }

            // Image
            if ($request->hasFile("image")) {
                Helper::deleteFile($exist->image);
                $data['image'] = Upload::fileUpload($request->file("image"), "cms");
            }

            if ($request->remove_image) {
                $data['image'] = '';
                Helper::deleteFile($exist->image);
            }

            // Banner Image
            if ($request->hasFile("bannerImage")) {
                Helper::deleteFile($exist->bannerImage);
                $data['bannerImage'] = Upload::fileUpload($request->file("bannerImage"), "cms");
            }

            if ($request->remove_bannerImage) {
                $data['bannerImage'] = '';
                Helper::deleteFile($exist->bannerImage);
            }

            // Strip Image
            if ($request->hasFile("strip_image")) {
                Helper::deleteFile($exist->sripe_image);
                $data['strip_image'] = Upload::fileUpload($request->file("strip_image"), "cms");
            }

            if ($request->remove_strip_image) {
                $data['strip_image'] = '';
                Helper::deleteFile($exist->strip_image);
            }

            // About Image 1
            if ($request->hasFile("about_image1")) {
                Helper::deleteFile($exist->about_image1);
                $data['about_image1'] = Upload::fileUpload($request->file("about_image1"), "cms");
            }

            if ($request->remove_about_image1) {
                $data['about_image1'] = '';
                Helper::deleteFile($exist->about_image1);
            }

            // About Image 2
            if ($request->hasFile("about_image2")) {
                Helper::deleteFile($exist->about_image2);
                $data['about_image2'] = Upload::fileUpload($request->file("about_image2"), "cms");
            }

            if ($request->remove_about_image2) {
                $data['about_image2'] = '';
                Helper::deleteFile($exist->about_image2);
            }

            // Owner Image
            if ($request->hasFile("owner_image")) {
                Helper::deleteFile($exist->owner_image);
                $data['owner_image'] = Upload::fileUpload($request->file("owner_image"), "cms");
            }

            if ($request->remove_owner_image) {
                $data['owner_image'] = '';
                Helper::deleteFile($exist->owner_image);
            }

            // Gallery Background
            if ($request->hasFile("gallery_background")) {
                Helper::deleteFile($exist->gallery_background);
                $data['gallery_background'] = Upload::fileUpload($request->file("gallery_background"), "cms");
            }

            if ($request->remove_gallery_background) {
                $data['gallery_background'] = '';
                Helper::deleteFile($exist->gallery_background);
            }

            // Property Background
            if ($request->hasFile("property_background")) {
                Helper::deleteFile($exist->property_background);
                $data['property_background'] = Upload::fileUpload($request->file("property_background"), "cms");
            }

            if ($request->remove_property_background) {
                $data['property_background'] = '';
                Helper::deleteFile($exist->property_background);
            }

            // About Background
            if ($request->hasFile("about_background")) {
                Helper::deleteFile($exist->about_background);
                $data['about_background'] = Upload::fileUpload($request->file("about_background"), "cms");
            }

            if ($request->remove_about_background) {
                $data['about_background'] = '';
                Helper::deleteFile($exist->about_background);
            }
          
           // About Image 4
            if ($request->hasFile("about_image4")) {
                Helper::deleteFile($exist->about_image4);
                $data['about_image4'] = Upload::fileUpload($request->file("about_image4"), "cms");
            }

            if ($request->remove_about_image4) {
                $data['about_image4'] = '';
                Helper::deleteFile($exist->about_image4);
            }
          
           // About Image 5
            if ($request->hasFile("about_image5")) {
                Helper::deleteFile($exist->about_image5);
                $data['about_image5'] = Upload::fileUpload($request->file("about_image5"), "cms");
            }

            if ($request->remove_about_image5) {
                $data['about_image5'] = '';
                Helper::deleteFile($exist->about_image5);
            }
          
           // About Image 6
            if ($request->hasFile("about_image6")) {
                Helper::deleteFile($exist->about_image6);
                $data['about_image6'] = Upload::fileUpload($request->file("about_image6"), "cms");
            }

            if ($request->remove_about_image6) {
                $data['about_image6'] = '';
                Helper::deleteFile($exist->about_image6);
            }
          
           // About Image 2
            if ($request->hasFile("about_image2")) {
                Helper::deleteFile($exist->about_image2);
                $data['about_image2'] = Upload::fileUpload($request->file("about_image2"), "cms");
            }

            if ($request->remove_about_image2) {
                $data['about_image2'] = '';
                Helper::deleteFile($exist->about_image2);
            }
          
            // why_choose_us_image
            if ($request->hasFile("why_choose_us_image")) {
                Helper::deleteFile($exist->why_choose_us_image);
                $data['why_choose_us_image'] = Upload::fileUpload($request->file("why_choose_us_image"), "cms");
            }
            if ($request->remove_why_choose_us_image) {
                $data['why_choose_us_image'] = '';
                Helper::deleteFile($exist->why_choose_us_image);
            }

            // Legacy panel section images
            foreach (['villa_image', 'living_image', 'rooftop_image', 'beach_image', 'experiences_image'] as $imgField) {
                if ($request->hasFile($imgField)) {
                    Helper::deleteFile($exist->$imgField);
                    $data[$imgField] = Upload::fileUpload($request->file($imgField), "cms");
                }
                if ($request->get("remove_{$imgField}")) {
                    Helper::deleteFile($exist->$imgField);
                    $data[$imgField] = '';
                }
            }

            // Home page images + our-story image + per-page banner override
            // (`inner_banner_image` powers the inner-page banner editor; `story_image_accent`
            // is the 2nd photo in the Our Story homepage section.)
            foreach (['story_image', 'story_image_accent', 'location_image', 'story_block_image', 'inner_banner_image'] as $imgField) {
                if ($request->hasFile($imgField)) {
                    Helper::deleteFile($exist->$imgField);
                    $data[$imgField] = Upload::fileUpload($request->file($imgField), "cms");
                }
                if ($request->get("remove_{$imgField}")) {
                    Helper::deleteFile($exist->$imgField);
                    $data[$imgField] = '';
                }
            }

            $this->model::find($id)->update($data);
            return redirect()->route($this->admin_base_url)->with("success", "Successfully Updated");
        }
        return redirect()->route($this->admin_base_url)->with("danger", "Invalid Calling");
    }

    public function duplicate($id)
    {
        $source = $this->model::find($id);
        if (!$source) {
            return redirect()->route($this->admin_base_url)->with("danger", "Invalid Calling");
        }
        $copy = $source->replicate();
        $base = $source->seo_url . '_copy';
        $slug = $base . '_1';
        $n = 1;
        while ($this->model::where('seo_url', $slug)->exists()) {
            $n++;
            $slug = $base . '_' . $n;
        }
        $copy->seo_url = $slug;
        $copy->name = $source->name . ' (copy)';
        $copy->save();
        return redirect()->route('cms.edit', $copy->id)->with('success', 'Page duplicated. Edit the copy.');
    }

    public function destroy($id)
    {
        $exist = $this->model::find($id);
        if ($exist) {
            Helper::deleteFile($exist->image);
            Helper::deleteFile($exist->bannerImage);
            Helper::deleteFile($exist->sripe_image);
            Helper::deleteFile($exist->about_image1);
            Helper::deleteFile($exist->about_image2);
            Helper::deleteFile($exist->owner_image);
            $exist->delete();
            return redirect()->route($this->admin_base_url)->with("success", "Successfully Deleted");
        }
        return redirect()->route($this->admin_base_url)->with("danger", "Invalid Calling");
    }
}
