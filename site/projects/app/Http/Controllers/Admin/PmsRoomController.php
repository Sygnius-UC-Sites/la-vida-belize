<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pms\PmsRoom;
use Helper;
use App\Helper\Upload;
use Validator;
use LiveCart;
use Carbon\Carbon;

class PmsRoomController extends Controller{
    
    public function __construct(PmsRoom $model){
        $this->model=$model;
        $this->admin_base_url="admin.pms_rooms.index";
        $this->admin_view="admin.pms_rooms";
    }

    public function index(){
        // Unified admin: PMS rooms live in the main Properties list now.
        // The standalone list is redundant — send admins there instead.
        return redirect()->route('properties.index');

        $data=$this->model::orderBy("id","desc")->get();
        return view($this->admin_view.".index",compact("data"));
    }
    
    public function create(){
        return redirect()->route($this->admin_base_url);
    }

    public function store(Request $request){
        return redirect()->route($this->admin_base_url);
    }

    /**
     * Publish a PMS-synced room to the public site with chosen placements.
     *
     * Creates a core Property page sharing the room's seo_url (which is what
     * links the two — the PMS widget enhances /{slug} automatically).
     * Placements:
     *   - search listing: always (status=true → appears on /stays + filters)
     *   - show_home=1   : also featured on the home page (is_home=true)
     */
    public function publishToSite(Request $request, $id){
        $request->validate([
            'property_category_ids' => 'nullable|array',
            'property_category_ids.*' => 'integer|exists:property_categories,id',
        ]);
        $room = $this->model::find($id);
        if (!$room) {
            return redirect()->back()->with('danger', 'PMS room not found.');
        }
        if (!$room->seo_url) {
            return redirect()->back()->with('danger', 'This room has no URL slug yet — edit it and set one first.');
        }
        $property = \App\Models\Property::where('seo_url', $room->seo_url)->first();
        if ($property && (
            ($property->pms_source && $property->pms_source !== $room->pms_source)
            || ($property->pms_room_id && (int) $property->pms_room_id !== (int) $room->id)
        )) {
            return redirect()->back()->with('danger', 'That property page is already linked to a different PMS listing.');
        }

        $desc      = trim((string) $room->description);
        $shortDesc = \Illuminate\Support\Str::limit(trim(strip_tags($desc)), 180);
        $thumb     = (string) $room->image_url;
        if ($thumb && str_starts_with($thumb, '//')) $thumb = 'https:' . $thumb;

        // Guarded insert — fill every NOT-NULL-without-default column so the
        // legacy schema can't 500 us (same approach as the seeders).
        $wasCreated = !$property;
        if ($property) {
            $property->forceFill([
                'pms_source' => $room->pms_source,
                'pms_room_id' => $room->id,
                'status' => 'true',
                'property_status' => 'publish',
                'is_home' => $request->boolean('show_home') ? 'true' : $property->is_home,
            ])->save();
            $propertyId = $property->id;
        } else {
            $columns  = \Illuminate\Support\Facades\Schema::getColumns('properties');
            $defaults = [];
            foreach ($columns as $c) {
                if (in_array($c['name'], ['id', 'created_at', 'updated_at'], true)) continue;
                if (!empty($c['nullable']) || ($c['default'] ?? null) !== null) continue;
                $type = strtolower($c['type_name'] ?? $c['type'] ?? '');
                $defaults[$c['name']] = preg_match('/int|decimal|float|double|numeric/', $type) ? 0 : '';
            }
            $row = array_merge($defaults, [
                'name'              => $room->name,
                'seo_url'           => $room->seo_url,
                'type'              => 'property',
                'sleeps'            => (int) ($room->max_people ?: 2),
                'bedroom'           => (int) ($room->bedrooms ?: 0),
                'bathroom'          => (int) ($room->bathrooms ?: 1),
                'beds'              => max(1, (int) ($room->bedrooms ?: 1)),
                'standard_rate'     => round((float) ($room->min_price ?: 0), 2),
                'min_stay'          => 1,
                'checkin'           => '16:00',
                'checkout'          => '11:00',
                'feature_image'     => $thumb,
                'banner_image'      => $room->banner_image ?: $thumb,
                'short_description' => $shortDesc,
                'long_description'  => $desc,
                'description'       => $shortDesc,
                'meta_title'        => $room->meta_title ?: $room->name,
                'meta_description'  => $room->meta_description ?: $shortDesc,
                'status'            => 'true',
                'property_status'   => 'publish',
                'is_home'           => $request->boolean('show_home') ? 'true' : 'false',
                'ordering'          => (int) (\App\Models\Property::max('ordering') + 1),
                'instant_booking_button' => 'true',
                'instant_booking_enabled' => 1,
                'accepts_pets'      => $room->pets_allowed === null ? 0 : (int) $room->pets_allowed,
                'max_pet'           => $room->pets_allowed ? (int) ($room->max_pets ?: 0) : null,
                'no_of_guest'       => (int) ($room->max_people ?: 2),
                'pms_source'        => $room->pms_source,
                'pms_room_id'       => $room->id,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
            $colNames = array_flip(array_column($columns, 'name'));
            $propertyId = \Illuminate\Support\Facades\DB::table('properties')
                ->insertGetId(array_intersect_key($row, $colNames));
        }

        \App\Models\Property::find($propertyId)
            ?->categories()
            ->sync($request->input('property_category_ids', []));

        $room->forceFill(['status' => 'true', 'active' => 1])->save();
        \App\Models\Pms\PmsProperty::where('pms_source', $room->pms_source)
            ->where('pms_property_id', $room->pms_property_id)
            ->update(['status' => 'true', 'updated_at' => now()]);

        if (\Illuminate\Support\Facades\Schema::hasTable('pms_property_links')) {
            \Illuminate\Support\Facades\DB::table('pms_property_links')
                ->where('cms_property_id', $propertyId)
                ->where('provider_slug', $room->pms_source)
                ->where('external_id', '!=', (string) $room->pms_room_id)
                ->delete();
            \Illuminate\Support\Facades\DB::table('pms_property_links')->updateOrInsert(
                ['provider_slug' => $room->pms_source, 'external_id' => (string) $room->pms_room_id],
                [
                    'cms_property_id' => $propertyId,
                    'link_mode' => 'mirror',
                    'last_synced_at' => $room->last_synced_at,
                    'last_sync_status' => 'linked',
                    'last_sync_error' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        \Illuminate\Support\Facades\Cache::forget('pms_widget_room_' . md5((string) $room->seo_url));
        \Illuminate\Support\Facades\Cache::forget('pms_widget_room_' . md5((string) $room->pms_room_id));
        \Illuminate\Support\Facades\Cache::forget('pms_widget_link_' . $room->id);

        // Fetch this room's availability calendar NOW. pms:sync-calendars only
        // covers rooms with status='true', so a room published AFTER the last
        // pms:sync-all run would otherwise have an empty calendar (every date
        // selectable → "Period is closed" quote errors) until the next sync.
        try {
            \Illuminate\Support\Facades\Artisan::call('pms:sync-calendars', [
                '--provider' => $room->pms_source,
                '--room'     => $room->pms_room_id,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('lodgify')->warning(
                'publishToSite: immediate calendar sync failed — will retry on next scheduled sync',
                ['room' => $room->pms_room_id, 'error' => $e->getMessage()]
            );
        }

        // Copy the first synced photos into a newly created site gallery.
        if ($wasCreated && \Illuminate\Support\Facades\Schema::hasTable('pms_images')) {
            // NB: pms_images.pms_room_id stores the PROVIDER's room id, not our PK.
            $images = \Illuminate\Support\Facades\DB::table('pms_images')
                ->where('pms_room_id', $room->pms_room_id)->orderBy('id')->limit(12)->get();
            foreach ($images as $i => $img) {
                $url = $img->url ?? $img->image ?? null;
                if (!$url) continue;
                if (str_starts_with($url, '//')) $url = 'https:' . $url;
                \Illuminate\Support\Facades\DB::table('property_galleries')->insert([
                    'property_id' => $propertyId,
                    'image'       => $url,
                    'caption'     => $img->text ?? '',
                    'sorting'     => $i + 1,
                    'status'      => 'true',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }

        $property = \App\Models\Property::find($propertyId);
        $publicUrl = $property ? \App\Support\PropertyUrl::url($property) : url('admin/properties');

        return redirect()->back()->with('success',
            $room->name . ' is now live at ' . $publicUrl .
            ($request->boolean('show_home') ? ' and featured on the home page.' : ' (search & stays listing).'));
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
        $validator = Validator::make($request->all(), [
            'seo_url' => 'required|unique:pms_rooms,seo_url,'.$id,
            'rental_agreement_required' => 'nullable|boolean',
            'rental_agreement_signing_mode' => 'nullable|in:inherit,pre_payment,post_confirmation',
            'rental_agreement_template_html' => 'nullable|string',
            'rental_agreement_house_rules_html' => 'nullable|string',
        ]);
        if($validator->fails()){
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        $exist=$this->model::find($id);
        if($exist){
            $data=$request->all();
            $data['rental_agreement_required'] = $request->boolean('rental_agreement_required') ? 1 : 0;
            $data['rental_agreement_signing_mode'] = $request->input('rental_agreement_signing_mode') ?: 'inherit';
             if ($request->hasFile("banner_image")) {
                Helper::deleteFile($exist->banner_image);
                $data['banner_image'] = Upload::uploadWithLogoImageData($request->file("banner_image"),"rooms");
            }
            if($request->remove_banner_image){
                $data['banner_image'] ='';
                Helper::deleteFile($exist->banner_image);
            }
            $this->model::find($id)->update($data);
            return redirect()->route($this->admin_base_url)->with("success","Successfully Updated");
        }
        return redirect()->back()->with("danger","Invalid Calling");
    }

    public function destroy($id){
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }
}
