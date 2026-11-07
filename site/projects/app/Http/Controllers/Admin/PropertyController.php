<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Property;
use App\Models\PropertyGallery;
use App\Models\PropertyAmenityGroup;
use App\Models\PropertyAmenity;
use App\Models\PropertyFee;
use App\Models\PropertySpace;
use App\Models\PropertyRate;
use App\Models\PropertyRateGroup;
use App\Models\PropertyRoom;
use App\Models\BookingRequest;
use App\Models\PropertyHighlight;
use App\Models\PropertySleepCard;
use App\Models\PropertyAmenityHighlight;
use App\Helper\Upload;
use Validator;
use LiveCart;
use Carbon\Carbon;

class PropertyController extends Controller{
    
    public function __construct(Property $model){
        $this->model=$model;
        $this->admin_base_url="properties.index";
        $this->admin_view="admin.properties";
    }
    
    public function index(){
        $data=$this->model::query()
            ->withCount(['galleryImages as photos_count'])
            ->with(['location:id,name', 'categories:id,name,slug'])
            ->orderBy("id","desc")
            ->get();

        // Unified listing: PMS-synced rooms appear here alongside core
        // properties (no separate "PMS properties" screen).
        //  - $pmsBySlug: rooms whose seo_url matches a core property → that
        //    core row gets a "synced" badge.
        //  - $pmsUnmatched: rooms not yet linked to a core property → shown
        //    as their own rows (edit goes to the PMS room editor).
        $pmsBySlug = collect();
        $pmsUnmatched = collect();
        if (class_exists(\App\Models\Pms\PmsRoom::class) && \Illuminate\Support\Facades\Schema::hasTable('pms_rooms')) {
            $rooms = \App\Models\Pms\PmsRoom::orderBy('id', 'desc')->get();
            $coreSlugs = $data->pluck('seo_url')->filter()->flip();
            foreach ($rooms as $room) {
                if ($room->seo_url && isset($coreSlugs[$room->seo_url])) {
                    $pmsBySlug[$room->seo_url] = $room;
                } else {
                    $pmsUnmatched->push($room);
                }
            }
        }

        $propertyCategories = \App\Models\PropertyCategory::where('status', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        return view($this->admin_view.".index",compact("data","pmsBySlug","pmsUnmatched", 'propertyCategories'));
    }

    public function create(){
        $propertyCategories = \App\Models\PropertyCategory::where('status', true)
            ->orderBy('sort_order')->orderBy('name')->get();
        return view($this->admin_view.".create", compact('propertyCategories'));
    }

    public function store(Request $request){
        // Plan A: Coerce empty-string overrides to NULL (admin form posts "" for "use global")
        $overrideCols = [
            'installment_plan', 'installment_2_days', 'installment_2_percent', 'installment_2_min_lead_days',
            'installment_3_days', 'installment_3_percent', 'installment_3_min_lead_days',
            'tax_applies_to_nights', 'tax_applies_to_cleaning_fee', 'tax_applies_to_pet_fee', 'tax_applies_to_extra_guest_fee',
            'discount_applies_to_nights', 'discount_applies_to_cleaning_fee', 'discount_applies_to_pet_fee', 'discount_applies_to_extra_guest_fee',
            'max_stay',
        ];
        foreach ($overrideCols as $col) {
            if ($request->has($col) && $request->input($col) === '') {
                $request->merge([$col => null]);
            }
        }

        $validator = Validator::make($request->all(), [
            'seo_url' => 'required|unique:properties,seo_url',
            'name' => 'required',
            'type' => 'nullable|in:property,condo,house',
            'instant_booking_enabled' => 'nullable|boolean',
            'rental_agreement_required' => 'nullable|boolean',
            'rental_agreement_signing_mode' => 'nullable|in:inherit,pre_payment,post_confirmation',
            'rental_agreement_template_html' => 'nullable|string',
            'rental_agreement_house_rules_html' => 'nullable|string',
            'override_template' => 'nullable|in:0,1',
            'override_rules' => 'nullable|in:0,1',
            'detail_check_in_instructions' => 'nullable|string',
            'checkin_arrival_window' => 'nullable|string|max:100',
            'checkin_door_code' => 'nullable|string|max:100',
            'checkin_wifi_name' => 'nullable|string|max:191',
            'checkin_wifi_password' => 'nullable|string|max:191',
            'checkin_parking_instructions' => 'nullable|string',
            'checkin_host_name' => 'nullable|string|max:191',
            'checkin_host_phone' => 'nullable|string|max:50',
            'checkin_emergency_phone' => 'nullable|string|max:50',
            'checkout_departure_time' => 'nullable|string|max:100',
            'checkout_key_return' => 'nullable|string',
            'checkout_cleaning_checklist' => 'nullable|string',
            'checkout_notes' => 'nullable|string',
            'welcome_email_send_day' => 'nullable|integer|min:0',
            'checkin_email_send_day' => 'nullable|integer|min:0',
            'checkout_email_send_day' => 'nullable|integer|min:0',
            'cancellation_policy_type' => 'nullable|in:flexible,moderate,strict,firm,super_strict,custom',
            'cancellation_policy' => 'nullable|string',
            'min_stay' => 'nullable|integer|min:1',
            'max_stay' => 'nullable|integer|min:1',
            'accepts_pets' => 'nullable|boolean',
            'smoking_allowed' => 'nullable|boolean',
            'parties_allowed' => 'nullable|boolean',
            'wheelchair_accessible' => 'nullable|boolean',
            'installment_plan' => 'nullable|in:full,two,three',
            'installment_2_days' => 'nullable|integer|min:0',
            'installment_2_percent' => 'nullable|integer|min:0|max:100',
            'installment_2_min_lead_days' => 'nullable|integer|min:0',
            'installment_3_days' => 'nullable|integer|min:0',
            'installment_3_percent' => 'nullable|integer|min:0|max:100',
            'installment_3_min_lead_days' => 'nullable|integer|min:0',
            'tax_applies_to_nights' => 'nullable|boolean',
            'tax_applies_to_cleaning_fee' => 'nullable|boolean',
            'tax_applies_to_pet_fee' => 'nullable|boolean',
            'tax_applies_to_extra_guest_fee' => 'nullable|boolean',
            'discount_applies_to_nights' => 'nullable|boolean',
            'discount_applies_to_cleaning_fee' => 'nullable|boolean',
            'discount_applies_to_pet_fee' => 'nullable|boolean',
            'discount_applies_to_extra_guest_fee' => 'nullable|boolean',
            'tax' => 'nullable|numeric|min:0|max:100',
            'cleaning_fee' => 'nullable|numeric|min:0',
            'security_deposit_amount' => 'nullable|numeric|min:0',
            'security_deposit_text' => 'nullable|string|max:191',
            'security_deposit_charge_at_booking' => 'nullable|boolean',
            'pet_fee' => 'nullable|numeric|min:0',
            'pet_fee_interval' => 'nullable|in:one_time,per_day',
            'pet_fee_type' => 'nullable|in:taxable,non-taxable',
            'max_pet' => 'nullable|integer|min:0|max:50',
            'guest_fee' => 'nullable|numeric|min:0',
            'no_of_guest' => 'nullable|integer|min:0|max:200',
            'get_quote_description' => 'nullable|string',
            'property_category_ids' => 'nullable|array',
            'property_category_ids.*' => 'integer|exists:property_categories,id',
        ]);
        if($validator->fails()){
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        if (\App\Models\PropertyCategory::where('slug', $request->seo_url)->exists()) {
            return redirect()->back()->withInput()->with('danger', 'This URL slug is already used by a property category.');
        }
        $data=$request->all();
        $categoryIds = $data['property_category_ids'] ?? [];
        unset($data['property_category_ids']);

        // Normalize tagsinput comma-strings to JSON arrays for JSON-typed columns
        foreach (['card_amenities'] as $jsonField) {
            if (isset($data[$jsonField]) && is_string($data[$jsonField]) && $data[$jsonField] !== '') {
                $value = $data[$jsonField];
                if (!\Illuminate\Support\Str::startsWith($value, '[')) {
                    $parts = array_values(array_filter(array_map('trim', explode(',', $value))));
                    $data[$jsonField] = json_encode($parts);
                }
            }
        }

        if ($request->hasFile("banner_image")) {
            $data['banner_image'] = Upload::fileUpload($request->file("banner_image"),"properties");
        }
        if ($request->hasFile("feature_image")) {
            $data['feature_image'] = Upload::uploadWithLogoImageData($request->file("feature_image"),"properties");
        }
        if ($request->hasFile("welcome_package_attachment")) {
            $data['welcome_package_attachment'] = Upload::uploadWithLogoImageData($request->file("welcome_package_attachment"),"properties");
        }
        // Blank form fields arrive as NULL (ConvertEmptyStringsToNull middleware), which breaks
        // the insert on NOT-NULL columns like `type` (an explicit NULL bypasses the DB default).
        // Drop nulls so DB defaults apply, then set the columns that have no usable default.
        $data = array_filter($data, fn ($v) => !is_null($v));
        $data['type'] = ($data['type'] ?? null) ?: 'property';
        if (!isset($data['instant_booking_button'])) {
            $data['instant_booking_button'] = ''; // NOT NULL, no DB default
        }
        $result=$this->model::create($data);
        if($result){
            $result->categories()->sync($categoryIds);
            $id=$result->id;
            LiveCart::getFileIcalFileData($id);
            if($request->file("images")){
                foreach($request->file("images") as $key1=>$image){
                    if($request->file("images")[$key1]){
                        $asset=['image' =>Upload::uploadWithLogoImageData($request->file("images")[$key1]),'property_id'=>$id,];
                        PropertyGallery::create($asset);
                    }
                }
            }
            // Phase 3.2: removed legacy PropertyFee wipe-and-recreate block. See update() for context.
            PropertySpace::where("property_id",$id)->delete();
            if($request->space_name){
                foreach($request->space_name as $key => $value){
                    $ar=["property_id"=>$id,"space_name"=>$request->space_name[$key],"space_status"=>$request->space_status[$key]];
                    if($request->file("space_image")[$key]){
                        $ar['space_image']=Upload::uploadWithLogoImageData($request->file("space_image")[$key]);
                    }
                    PropertySpace::create($ar);
                }
            } 
        }
        if ($result) {
            return redirect()
                ->to(route('properties.edit', $result->id) . '#photos')
                ->with("success", "Listing created. Continue the setup checklist before publishing.");
        }

        return redirect()->route($this->admin_base_url)->with("danger", "Unable to create listing.");
    }
  
    public function show($id){
        return redirect()->route($this->admin_base_url);
    }

    public function edit($id){
        $data=$this->model::find($id);
        if($data){
            // PMS-synced properties: content is owned by the provider and
            // overwritten on every sync, so editing it locally is misleading.
            // Show a streamlined SEO + placement editor instead of the full
            // content editor.
            if (!empty($data->pms_source)) {
                $linkedRoom = \App\Models\Pms\PmsRoom::where('pms_source', $data->pms_source)
                    ->where('id', $data->pms_room_id)->first()
                    ?: \App\Models\Pms\PmsRoom::where('pms_source', $data->pms_source)
                        ->where('seo_url', $data->seo_url)->first();
                $propertyCategories = \App\Models\PropertyCategory::where('status', true)
                    ->orderBy('sort_order')->orderBy('name')->get();
                return view('admin.properties.edit-pms', compact('data', 'linkedRoom', 'propertyCategories'));
            }
            $ranges = \App\Models\PropertyRateGroup::where('property_id', $id)->orderBy('start_date','desc')->get();
            $propertyCategories = \App\Models\PropertyCategory::where('status', true)
                ->orderBy('sort_order')->orderBy('name')->get();
            return view($this->admin_view.".edit",compact("data","ranges", 'propertyCategories'));
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }
  
    public function update(Request $request, $id){
        // Plan A: Coerce empty-string overrides to NULL (admin form posts "" for "use global")
        $overrideCols = [
            'installment_plan', 'installment_2_days', 'installment_2_percent', 'installment_2_min_lead_days',
            'installment_3_days', 'installment_3_percent', 'installment_3_min_lead_days',
            'tax_applies_to_nights', 'tax_applies_to_cleaning_fee', 'tax_applies_to_pet_fee', 'tax_applies_to_extra_guest_fee',
            'discount_applies_to_nights', 'discount_applies_to_cleaning_fee', 'discount_applies_to_pet_fee', 'discount_applies_to_extra_guest_fee',
            'max_stay',
        ];
        foreach ($overrideCols as $col) {
            if ($request->has($col) && $request->input($col) === '') {
                $request->merge([$col => null]);
            }
        }

        // PMS-synced property: the streamlined editor only posts SEO + placement
        // (content like `name` is synced and not in the form). Validate just
        // those fields and short-circuit to the whitelisted save below.
        $existing = $this->model::find($id);
        if ($existing && !empty($existing->pms_source)) {
            $v = Validator::make($request->all(), [
                'seo_url' => 'required|unique:properties,seo_url,'.$id,
                'type' => 'nullable|in:property,condo,house',
                'status'  => 'nullable|in:true,false',
                'is_home' => 'nullable|in:true,false',
                'ordering'=> 'nullable|integer|min:0',
                'instant_booking_enabled' => 'nullable|boolean',
                'property_category_ids' => 'nullable|array',
                'property_category_ids.*' => 'integer|exists:property_categories,id',
                'pets_allowed_override' => 'nullable|in:0,1',
                'max_pets_override' => 'nullable|integer|min:1|max:50',
            ]);
            if ($v->fails()) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'message' => $v->errors()->first(),
                        'errors' => $v->errors(),
                    ], 422);
                }
                return redirect()->back()->withInput()->with('danger', $v->errors()->first())->withErrors($v->errors());
            }
            if (\App\Models\PropertyCategory::where('slug', $request->seo_url)->exists()) {
                return redirect()->back()->withInput()->with('danger', 'This URL slug is already used by a property category.');
            }
            $allowed = ['seo_url', 'type', 'subtitle', 'meta_title', 'meta_keywords',
                        'meta_description', 'status', 'is_home', 'ordering',
                        'instant_booking_enabled'];
            $pmsUpdateData = array_intersect_key($request->all(), array_flip($allowed));
            $instantBooking = $request->has('instant_booking_enabled')
                ? (int) $request->boolean('instant_booking_enabled')
                : (int) ($existing->instant_booking_enabled ?? 1);
            $pmsUpdateData['instant_booking_enabled'] = $instantBooking;
            $pmsUpdateData['instant_booking_button'] = $instantBooking ? 'true' : 'false';
            $existing->update($pmsUpdateData);
            $existing->categories()->sync($request->input('property_category_ids', []));

            $linkedRoom = \App\Models\Pms\PmsRoom::find($existing->pms_room_id);
            if ($linkedRoom && $request->has('pets_allowed_override')) {
                $petsAllowed = $request->input('pets_allowed_override');
                $petsAllowed = $petsAllowed === '' || $petsAllowed === null
                    ? null
                    : (int) $petsAllowed;
                $maxPets = $petsAllowed === 1
                    ? (int) ($request->input('max_pets_override') ?: 0)
                    : null;
                $linkedRoom->forceFill([
                    'pets_allowed' => $petsAllowed,
                    'max_pets' => $maxPets,
                ])->save();
                $existing->forceFill([
                    'accepts_pets' => $petsAllowed === 1 ? 1 : 0,
                    'max_pet' => $petsAllowed === 1 ? $maxPets : null,
                ])->save();
            }

            // Site-gallery photos: owners often have MORE photos than the PMS
            // listing, so the streamlined editor still allows ADDING gallery
            // images. These live only in property_galleries (site side) — the
            // synced PMS content is never touched and syncs never delete them.
            $added = 0;
            if ($request->file('images')) {
                $nextSort = (int) PropertyGallery::where('property_id', $id)->max('sorting');
                foreach ($request->file('images') as $image) {
                    if (!$image) continue;
                    PropertyGallery::create([
                        'image'       => Upload::uploadWithLogoImageData($image),
                        'property_id' => $id,
                        'status'      => 'true',
                        'sorting'     => ++$nextSort,
                    ]);
                    $added++;
                }
            }

            $message = 'SEO & placement updated.'
                . ($added ? " {$added} photo" . ($added === 1 ? '' : 's') . " added to the site gallery." : '')
                . ' (Content stays synced from ' . ucfirst($existing->pms_source) . '.)';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => true, 'message' => $message]);
            }

            return redirect()->back()->with('success', $message);
        }

        $validator = Validator::make($request->all(), [
            'seo_url' => 'required|unique:properties,seo_url,'.$id,
            'name' => 'required',
            'type' => 'nullable|in:property,condo,house',
            'instant_booking_enabled' => 'nullable|boolean',
            'rental_agreement_required' => 'nullable|boolean',
            'rental_agreement_signing_mode' => 'nullable|in:inherit,pre_payment,post_confirmation',
            'rental_agreement_template_html' => 'nullable|string',
            'rental_agreement_house_rules_html' => 'nullable|string',
            'override_template' => 'nullable|in:0,1',
            'override_rules' => 'nullable|in:0,1',
            'detail_check_in_instructions' => 'nullable|string',
            'checkin_arrival_window' => 'nullable|string|max:100',
            'checkin_door_code' => 'nullable|string|max:100',
            'checkin_wifi_name' => 'nullable|string|max:191',
            'checkin_wifi_password' => 'nullable|string|max:191',
            'checkin_parking_instructions' => 'nullable|string',
            'checkin_host_name' => 'nullable|string|max:191',
            'checkin_host_phone' => 'nullable|string|max:50',
            'checkin_emergency_phone' => 'nullable|string|max:50',
            'checkout_departure_time' => 'nullable|string|max:100',
            'checkout_key_return' => 'nullable|string',
            'checkout_cleaning_checklist' => 'nullable|string',
            'checkout_notes' => 'nullable|string',
            'welcome_email_send_day' => 'nullable|integer|min:0',
            'checkin_email_send_day' => 'nullable|integer|min:0',
            'checkout_email_send_day' => 'nullable|integer|min:0',
            'cancellation_policy_type' => 'nullable|in:flexible,moderate,strict,firm,super_strict,custom',
            'cancellation_policy' => 'nullable|string',
            'min_stay' => 'nullable|integer|min:1',
            'max_stay' => 'nullable|integer|min:1',
            'accepts_pets' => 'nullable|boolean',
            'smoking_allowed' => 'nullable|boolean',
            'parties_allowed' => 'nullable|boolean',
            'wheelchair_accessible' => 'nullable|boolean',
            'installment_plan' => 'nullable|in:full,two,three',
            'installment_2_days' => 'nullable|integer|min:0',
            'installment_2_percent' => 'nullable|integer|min:0|max:100',
            'installment_2_min_lead_days' => 'nullable|integer|min:0',
            'installment_3_days' => 'nullable|integer|min:0',
            'installment_3_percent' => 'nullable|integer|min:0|max:100',
            'installment_3_min_lead_days' => 'nullable|integer|min:0',
            'tax_applies_to_nights' => 'nullable|boolean',
            'tax_applies_to_cleaning_fee' => 'nullable|boolean',
            'tax_applies_to_pet_fee' => 'nullable|boolean',
            'tax_applies_to_extra_guest_fee' => 'nullable|boolean',
            'discount_applies_to_nights' => 'nullable|boolean',
            'discount_applies_to_cleaning_fee' => 'nullable|boolean',
            'discount_applies_to_pet_fee' => 'nullable|boolean',
            'discount_applies_to_extra_guest_fee' => 'nullable|boolean',
            'tax' => 'nullable|numeric|min:0|max:100',
            'cleaning_fee' => 'nullable|numeric|min:0',
            'security_deposit_amount' => 'nullable|numeric|min:0',
            'security_deposit_text' => 'nullable|string|max:191',
            'security_deposit_charge_at_booking' => 'nullable|boolean',
            'pet_fee' => 'nullable|numeric|min:0',
            'pet_fee_interval' => 'nullable|in:one_time,per_day',
            'pet_fee_type' => 'nullable|in:taxable,non-taxable',
            'max_pet' => 'nullable|integer|min:0|max:50',
            'guest_fee' => 'nullable|numeric|min:0',
            'no_of_guest' => 'nullable|integer|min:0|max:200',
            'get_quote_description' => 'nullable|string',
            'property_category_ids' => 'nullable|array',
            'property_category_ids.*' => 'integer|exists:property_categories,id',
        ]);
        if($validator->fails()){
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        if (\App\Models\PropertyCategory::where('slug', $request->seo_url)->exists()) {
            return redirect()->back()->withInput()->with('danger', 'This URL slug is already used by a property category.');
        }
        $exist=$this->model::find($id);
        if($exist){
            $data=$request->all();
            $categoryIds = $data['property_category_ids'] ?? [];
            unset($data['property_category_ids']);

            // Normalize tagsinput comma-strings to JSON arrays for JSON-typed columns
            foreach (['card_amenities'] as $jsonField) {
                if (isset($data[$jsonField]) && is_string($data[$jsonField]) && $data[$jsonField] !== '') {
                    $value = $data[$jsonField];
                    if (!\Illuminate\Support\Str::startsWith($value, '[')) {
                        $parts = array_values(array_filter(array_map('trim', explode(',', $value))));
                        $data[$jsonField] = json_encode($parts);
                    }
                }
            }

            // Per-property rental agreement template / house rules: NULL out when override checkbox is unchecked
            $data['rental_agreement_template_html']    = $request->input('override_template') ? $request->input('rental_agreement_template_html') : null;
            $data['rental_agreement_house_rules_html'] = $request->input('override_rules')    ? $request->input('rental_agreement_house_rules_html') : null;
            unset($data['override_template'], $data['override_rules']);

            // Per-property stay-communications timing: empty form value -> NULL (inherit global)
            foreach (['welcome_email_send_day', 'checkin_email_send_day', 'checkout_email_send_day'] as $intField) {
                if (array_key_exists($intField, $data) && $data[$intField] === '') {
                    $data[$intField] = null;
                }
            }

            if ($request->hasFile("banner_image")) {
                $data['banner_image'] = Upload::fileUpload($request->file("banner_image"),"properties");
            }
            if ($request->hasFile("feature_image")) {
                $data['feature_image'] = Upload::uploadWithLogoImageData($request->file("feature_image"),"properties");
            }
            if ($request->hasFile("welcome_package_attachment")) {
                $data['welcome_package_attachment'] = Upload::uploadWithLogoImageData($request->file("welcome_package_attachment"),"properties");
            }
            $this->model::find($id)->update($data);
            $exist->categories()->sync($categoryIds);
            LiveCart::getFileIcalFileData($id);
            if($request->preloaded){
                PropertyGallery::whereNotIn("id",$request->preloaded)->delete();
            }
            if($request->file("images")){
                foreach($request->file("images") as $key1=>$image){
                    if($request->file("images")[$key1]){
                        $asset=['image' =>Upload::uploadWithLogoImageData($request->file("images")[$key1]),'property_id'=>$id,];
                        PropertyGallery::create($asset);
                    }
                }
            }
            // Phase 3.2: removed legacy PropertyFee wipe-and-recreate block. Property fees
            // are managed exclusively via PropertyFeeController endpoints (POST/PUT/DELETE
            // on /admin/properties/{property_id}/fees). The old block deleted all fees
            // every save then re-INSERTed using legacy column names that no longer exist
            // in the Phase 3 schema, wiping the property's fees on every property edit.
            if($request->space_name){
                foreach($request->space_name as $key => $value){
                    $ar=["property_id"=>$id,"space_name"=>$request->space_name[$key],"space_status"=>$request->space_status[$key]];
                    if($request->file("space_image")){
                        if(isset($request->file("space_image")[$key])){
                            $ar['space_image']=Upload::uploadWithLogoImageData($request->file("space_image")[$key]);
                        }
                    }
                    if(isset($request->space_id[$key])){
                        $ar12=PropertySpace::find($request->space_id[$key]);
                        if($ar12){
                            $ar12->update($ar);
                        }
                    }else{
                        PropertySpace::create($ar);
                    }
                }
            }

            // Highlights
            $keptHighlightIds = [];
            if (is_array($request->highlight_title)) {   // QA 2026-06-12: guard non-array input (500 on hostile/manual POST)
                foreach ($request->highlight_title as $key => $value) {
                    $title = trim((string) ($request->highlight_title[$key] ?? ''));
                    $description = trim((string) ($request->highlight_description[$key] ?? ''));
                    $existingId = (int) ($request->highlight_id[$key] ?? 0);
                    if ($existingId <= 0 && $title === '' && $description === '') {
                        continue;
                    }
                    $ar = [
                        "property_id" => $id,
                        "icon"        => $request->highlight_icon[$key]        ?? '',
                        "title"       => $title,
                        "description" => $description,
                        "sorting"     => $key,
                        "status"      => "true",
                    ];
                    if ($existingId > 0) {
                        if ($row = PropertyHighlight::where('property_id', $id)->find($existingId)) {
                            $row->update($ar);
                            $keptHighlightIds[] = $existingId;
                        }
                    } else {
                        $created = PropertyHighlight::create($ar);
                        $keptHighlightIds[] = $created->id;
                    }
                }
            }
            if ($request->has('highlight_id') || $request->has('highlight_title')) {
                PropertyHighlight::where('property_id', $id)
                    ->when(!empty($keptHighlightIds), fn ($q) => $q->whereNotIn('id', $keptHighlightIds))
                    ->delete();
            }

            // Sleep cards
            $keptSleepCardIds = [];
            if (is_array($request->sleep_card_title)) {   // QA 2026-06-12: guard non-array input (500 on hostile/manual POST)
                foreach ($request->sleep_card_title as $key => $value) {
                    $title = trim((string) ($request->sleep_card_title[$key] ?? ''));
                    $text = trim((string) ($request->sleep_card_text[$key] ?? ''));
                    $existingId = (int) ($request->sleep_card_id[$key] ?? 0);
                    if ($existingId <= 0 && $title === '' && $text === '') {
                        continue;
                    }
                    $ar = [
                        "property_id" => $id,
                        "title"       => $title,
                        "text"        => $text,
                        "sorting"     => $key,
                        "status"      => "true",
                    ];
                    if ($existingId > 0) {
                        if ($row = PropertySleepCard::where('property_id', $id)->find($existingId)) {
                            $row->update($ar);
                            $keptSleepCardIds[] = $existingId;
                        }
                    } else {
                        $created = PropertySleepCard::create($ar);
                        $keptSleepCardIds[] = $created->id;
                    }
                }
            }
            if ($request->has('sleep_card_id') || $request->has('sleep_card_title')) {
                PropertySleepCard::where('property_id', $id)
                    ->when(!empty($keptSleepCardIds), fn ($q) => $q->whereNotIn('id', $keptSleepCardIds))
                    ->delete();
            }

            // Amenity highlights
            $keptAmenityHighlightIds = [];
            if (is_array($request->amenity_highlight_label)) {   // QA 2026-06-12: guard non-array input (500 on hostile/manual POST)
                foreach ($request->amenity_highlight_label as $key => $value) {
                    $label = trim((string) ($request->amenity_highlight_label[$key] ?? ''));
                    $emoji = trim((string) ($request->amenity_highlight_emoji[$key] ?? ''));
                    $existingId = (int) ($request->amenity_highlight_id[$key] ?? 0);
                    if ($existingId <= 0 && $label === '' && $emoji === '') {
                        continue;
                    }
                    $ar = [
                        "property_id" => $id,
                        "emoji"       => $emoji,
                        "label"       => $label,
                        "sorting"     => $key,
                        "status"      => "true",
                    ];
                    if ($existingId > 0) {
                        if ($row = PropertyAmenityHighlight::where('property_id', $id)->find($existingId)) {
                            $row->update($ar);
                            $keptAmenityHighlightIds[] = $existingId;
                        }
                    } else {
                        $created = PropertyAmenityHighlight::create($ar);
                        $keptAmenityHighlightIds[] = $created->id;
                    }
                }
            }
            if ($request->has('amenity_highlight_id') || $request->has('amenity_highlight_label')) {
                PropertyAmenityHighlight::where('property_id', $id)
                    ->when(!empty($keptAmenityHighlightIds), fn ($q) => $q->whereNotIn('id', $keptAmenityHighlightIds))
                    ->delete();
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => true, 'message' => 'Successfully Updated']);
            }

            return redirect()->back()->with("success","Successfully Updated");
        }
        return redirect()->back()->with("danger","Invalid Calling");
    }

    public function destroy($id){
        $exist=$this->model::find($id);
        if($exist){
            foreach(PropertyAmenityGroup::where("property_id",$id)->get() as $c){
                PropertyAmenity::where("property_amenity_id",$c->id)->delete();
                $c->delete();
            }
            PropertyFee::where("property_id",$id)->delete();
            PropertyGallery::where("property_id",$id)->delete();
            PropertyRate::where("property_id",$id)->delete();
            PropertyRateGroup::where("property_id",$id)->delete();
            PropertyRoom::where("property_id",$id)->delete();
            PropertySpace::where("property_id",$id)->delete();
            BookingRequest::where("property_id",$id)->delete();
            PropertyHighlight::where("property_id",$id)->delete();
            PropertySleepCard::where("property_id",$id)->delete();
            PropertyAmenityHighlight::where("property_id",$id)->delete();
            $exist->delete();
            return redirect()->route($this->admin_base_url)->with("success","Successfully Deleted");
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }

    public function imageDeleteAsset(Request $request){
        $data=PropertyGallery::find($request->id);
        if($data){
            $data->delete();
        }
    }

    public function setFeatureImage($property_id, $gallery_id)
    {
        $property = $this->model::findOrFail($property_id);
        $gallery  = PropertyGallery::where('property_id', $property_id)->findOrFail($gallery_id);
        $property->update(['feature_image' => $gallery->image]);
        return redirect()->back()->with('success', 'Cover photo updated.');
    }

    public function galleryBulkDelete(Request $request, $property_id)
    {
        $ids = array_filter(array_map('intval', (array)$request->input('ids', [])));
        if (empty($ids)) {
            return redirect()->back()->with('danger', 'No photos selected.');
        }
        $deleted = PropertyGallery::whereIn('id', $ids)
            ->where('property_id', $property_id)
            ->delete();
        return redirect()->back()->with('success', "$deleted photos deleted.");
    }

    public function galleryBulkUpdateCategory(Request $request, $property_id)
    {
        $ids = array_filter(array_map('intval', (array)$request->input('ids', [])));
        $category = $request->input('category');
        if (empty($ids)) {
            return redirect()->back()->with('danger', 'No photos selected.');
        }
        $valid = array_keys(\Helper::getGalleryCategories());
        if (!in_array($category, $valid, true) && $category !== '' && $category !== null) {
            return redirect()->back()->with('danger', 'Invalid category.');
        }
        $updated = PropertyGallery::whereIn('id', $ids)
            ->where('property_id', $property_id)
            ->update(['category' => $category ?: null]);
        return redirect()->back()->with('success', "$updated photos recategorized.");
    }

    public function galleryUpdateOne(Request $request, $property_id, $image_id)
    {
        $photo = PropertyGallery::where('property_id', $property_id)->findOrFail($image_id);
        $payload = [];
        if ($request->has('caption'))  $payload['caption']  = (string)$request->input('caption');
        if ($request->has('category')) {
            $cat = $request->input('category');
            $valid = array_keys(\Helper::getGalleryCategories());
            if (in_array($cat, $valid, true) || $cat === '' || $cat === null) {
                $payload['category'] = $cat ?: null;
            }
        }
        if ($request->has('sorting')) $payload['sorting'] = (int)$request->input('sorting');

        if (!empty($payload)) {
            $photo->update($payload);
        }
        return response()->json(['ok' => true]);
    }

    public function syncAmenityLibrary(Request $request, $property_id)
    {
        $property = $this->model::findOrFail($property_id);
        $library  = \Helper::getAmenityLibrary();

        $picks = collect($request->input('library_amenities', []))
            ->map(fn ($v) => explode('::', $v, 2))
            ->filter(fn ($pair) => count($pair) === 2)
            ->groupBy(fn ($pair) => $pair[0])
            ->map(fn ($pairs) => $pairs->pluck(1)->all());

        foreach ($library as $categoryName => $libraryAmenities) {
            $pickedNames = $picks->get($categoryName, []);

            $group = \App\Models\PropertyAmenityGroup::firstOrCreate(
                ['property_id' => $property_id, 'name' => $categoryName],
                ['status' => 'true', 'sorting' => 0]
            );

            foreach ($libraryAmenities as $entry) {
                $exists = \App\Models\PropertyAmenity::where('property_amenity_id', $group->id)
                    ->where('name', $entry['name'])
                    ->first();

                if (in_array($entry['name'], $pickedNames, true)) {
                    if ($exists) {
                        $exists->update(['icon' => $entry['icon'], 'status' => 'true']);
                    } else {
                        \App\Models\PropertyAmenity::create([
                            'property_amenity_id' => $group->id,
                            'name'                => $entry['name'],
                            'icon'                => $entry['icon'],
                            'status'              => 'true',
                            'sorting'             => 0,
                        ]);
                    }
                } else {
                    if ($exists) {
                        $exists->delete();
                    }
                }
            }
        }

        // Phase 4F-1: also sync card highlights from the same form
        $highlightIds = collect((array)$request->input('card_highlights', []))
            ->map(fn ($v) => (int)$v)
            ->filter(fn ($v) => $v > 0)
            ->all();

        $allGroupIds = \App\Models\PropertyAmenityGroup::where('property_id', $property_id)->pluck('id');
        \App\Models\PropertyAmenity::whereIn('property_amenity_id', $allGroupIds)
            ->update(['is_card_highlight' => 0]);
        if (!empty($highlightIds)) {
            \App\Models\PropertyAmenity::whereIn('property_amenity_id', $allGroupIds)
                ->whereIn('id', $highlightIds)
                ->update(['is_card_highlight' => 1]);
        }

        return redirect()->to(route('properties.edit', [$property_id]) . '#amenities')
            ->with('success', 'Amenities updated.');
    }

    public function customAmenitiesBatchAdd(Request $request, $property_id)
    {
        $property   = $this->model::findOrFail($property_id);
        $names      = $request->input('name', []);
        $icons      = $request->input('icon', []);
        $categories = $request->input('category', []);
        $customs    = $request->input('custom_category', []);
        $highlights = (array)$request->input('highlight', []);

        $created = 0;
        foreach ($names as $i => $name) {
            $name = trim((string)($name ?? ''));
            if ($name === '') continue;

            $cat = (string)($categories[$i] ?? '');
            if ($cat === '__custom__') {
                $cat = trim((string)($customs[$i] ?? 'Other'));
                if ($cat === '') $cat = 'Other';
            }

            $group = \App\Models\PropertyAmenityGroup::firstOrCreate(
                ['property_id' => $property_id, 'name' => $cat],
                ['status' => 'true', 'sorting' => 0]
            );

            \App\Models\PropertyAmenity::create([
                'property_amenity_id' => $group->id,
                'name'                => $name,
                'icon'                => trim((string)($icons[$i] ?? '')) ?: null,
                'status'              => 'true',
                'sorting'             => $i,
                'is_card_highlight'   => !empty($highlights[$i]) ? 1 : 0,
            ]);
            $created++;
        }

        return redirect()->to(route('properties.edit', [$property_id]) . '#amenities')
            ->with('success', "$created custom amenities added.");
    }

    public function customAmenitiesBatchDelete(Request $request, $property_id)
    {
        $ids = array_filter(array_map('intval', (array)$request->input('ids', [])));
        if (empty($ids)) {
            return redirect()->to(route('properties.edit', [$property_id]) . '#amenities')
                ->with('danger', 'No amenities selected.');
        }

        $groupIds = \App\Models\PropertyAmenityGroup::where('property_id', $property_id)->pluck('id');
        $deleted  = \App\Models\PropertyAmenity::whereIn('id', $ids)
            ->whereIn('property_amenity_id', $groupIds)
            ->delete();

        return redirect()->to(route('properties.edit', [$property_id]) . '#amenities')
            ->with('success', "$deleted custom amenities deleted.");
    }

    public function  deletePropertySpace(Request $request){
        if($request->id){
            $data=  PropertySpace::find($request->id);
            if($data){
                $data->delete();
            }
        }
    }

    public function deactive($id){
        $data=$this->model::find($id);
        if($data){
            $data->status="false";
            $data->save();
            return redirect()->route($this->admin_base_url)->with("success","Successfully deactive");
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }

    public function active($id){
        $data=$this->model::find($id);
        if($data){
            $data->status="true";
            $data->save();
            return redirect()->route($this->admin_base_url)->with("success","Successfully activated");
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }

    /**
     * One-click toggle for home-page placement (mirrors the
     * "Show on homepage?" field on the property edit form).
     */
    public function toggleHome($id){
        $data=$this->model::find($id);
        if($data){
            $data->is_home = ($data->is_home === "true") ? "false" : "true";
            $data->save();
            return redirect()->route($this->admin_base_url)->with("success",
                $data->is_home === "true"
                    ? $data->name." is now featured on the home page."
                    : $data->name." removed from the home page (still listed on /stays).");
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }
    
    public function updateCaptionSOrt(Request $request){
        foreach($request->captionidsarray as $key=>$value){
            if(isset($value['id'])){
                $data=PropertyGallery::find($value['id']);
                if($data){
                    $data->sorting=$key;
                    if(isset($value['value']))
                    $data->caption=$value['value'];
                    $data->save();
                }
            }
        }
        echo "successfully update gallery";
    }

    public function deletePropertyHighlight(Request $request)
    {
        return $this->deletePropertyRepeaterRow(PropertyHighlight::class, $request);
    }

    public function deletePropertySleepCard(Request $request)
    {
        return $this->deletePropertyRepeaterRow(PropertySleepCard::class, $request);
    }

    public function deletePropertyAmenityHighlight(Request $request)
    {
        return $this->deletePropertyRepeaterRow(PropertyAmenityHighlight::class, $request);
    }

    private function deletePropertyRepeaterRow(string $modelClass, Request $request)
    {
        $request->validate([
            'id' => 'required|integer|min:1',
        ]);

        $deleted = $modelClass::where('id', (int) $request->input('id'))->delete();

        return response()->json([
            'ok' => true,
            'deleted' => $deleted,
        ]);
    }

    public function copyData($id){
        $data=$this->model::find($id);
        if($data){
            $newPost = $data->replicate();
            $seo_url=$data->seo_url.'-'.uniqid().'-copy';
            $newPost->seo_url=$seo_url;
            $newPost->created_at = Carbon::now();
            $result=$newPost->save();
            if($result){
                $property=Property::where("seo_url",$seo_url)->first();
                $id1=$property->id;
                LiveCart::getFileIcalFileData($id1);
                $data=PropertyGallery::where("property_id",$id)->get();
                foreach($data as $c){
                    $asset=['image' =>$c->image,'property_id'=>$id1,'status'=>$c->status,'sorting'=>$c->sorting,'caption'=>$c->caption];
                    PropertyGallery::create($asset);
                }
                foreach(PropertyAmenityGroup::where("property_id",$id)->get() as $c){
                    $am_group=PropertyAmenityGroup::create(["property_id"=>$id1,"status"=>$c->status,"name"=>$c->name,"image"=>$c->image,"sorting"=>$c->sorting]);
                    foreach(PropertyAmenity::where("property_amenity_id",$c->id)->get() as $c1){
                        PropertyAmenity::create(["property_amenity_id"=>$am_group->id,"name"=>$c1->name,"status"=>$c1->status,"image"=>$c1->image,"sorting"=>$c1->sorting]);
                    }
                }
            }
            return redirect()->route($this->admin_base_url)->with("success","Successfully Coppied");
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }

    // ==== Phase 7: drag-reorder for the 3 small repeaters ====================
    public function reorderHighlights(Request $request, $property_id)
    {
        return $this->_repeaterReorder(PropertyHighlight::class, $property_id, $request);
    }
    public function reorderSleepCards(Request $request, $property_id)
    {
        return $this->_repeaterReorder(PropertySleepCard::class, $property_id, $request);
    }
    public function reorderAmenityHighlights(Request $request, $property_id)
    {
        return $this->_repeaterReorder(PropertyAmenityHighlight::class, $property_id, $request);
    }
    private function _repeaterReorder(string $modelClass, $property_id, Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|min:1',
        ]);
        $ids = $request->input('ids');
        \DB::transaction(function () use ($modelClass, $property_id, $ids) {
            foreach ($ids as $position => $id) {
                $modelClass::where('property_id', $property_id)
                    ->where('id', $id)
                    ->update(['sorting' => $position]);
            }
        });
        return response()->json(['ok' => true, 'reordered' => count($ids)]);
    }

    // ==== Phase 7: bulk actions on the index ================================
    public function bulkActivate(Request $request)
    {
        $ids = $this->_validateBulkIds($request);
        Property::whereIn('id', $ids)->update(['status' => 'true']);
        return response()->json(['ok' => true, 'updated' => count($ids)]);
    }
    public function bulkDeactivate(Request $request)
    {
        $ids = $this->_validateBulkIds($request);
        Property::whereIn('id', $ids)->update(['status' => 'false']);
        return response()->json(['ok' => true, 'updated' => count($ids)]);
    }
    public function bulkDelete(Request $request)
    {
        $ids = $this->_validateBulkIds($request);
        // Reuse single-destroy cascade by iterating — keeps the existing
        // PropertyFee / PropertyRate / PropertyGallery cleanup in one place.
        foreach ($ids as $id) { $this->destroy($id); }
        return response()->json(['ok' => true, 'deleted' => count($ids)]);
    }
    private function _validateBulkIds(Request $request): array
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|min:1',
        ]);
        return $request->input('ids');
    }
}
