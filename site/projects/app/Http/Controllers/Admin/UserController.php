<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Helper\FeatureFlags;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Validator;

class UserController extends Controller{
    protected User $model;
    protected string $admin_base_url;
    protected string $admin_view;
    
    public function __construct(User $model){
        $this->model=$model;
        $this->admin_base_url="users.index";
        $this->admin_view="admin.users";
    }

    
    public function index(){
        $query = $this->model::query()->orderBy("id","desc");

        if (!$this->canManageDevelopers()) {
            $query->where(function ($q) {
                $q->where('is_developer', false)->orWhereNull('is_developer');
            })->whereNotIn('email', FeatureFlags::developerEmails());
        }

        $data = $query->get();
        $canManageDevelopers = $this->canManageDevelopers();

        return view($this->admin_view.".index",compact("data", "canManageDevelopers"));
    }

    
    public function create(){
        $canManageDevelopers = $this->canManageDevelopers();

        return view($this->admin_view.".create", compact("canManageDevelopers"));
    }

    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);   
        if($validator->fails()){
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }

        $email = strtolower(trim((string) $request->input('email')));
        $requestedDeveloper = $request->boolean('is_developer') || FeatureFlags::emailWouldBeDeveloper($email);

        if ($requestedDeveloper && !$this->canManageDevelopers()) {
            return redirect()->back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->with("danger", "Only developer accounts can create another developer account.");
        }

        $this->model::create([
            'name' => trim((string) $request->input('name')),
            'email' => $email,
            'password' => Hash::make((string) $request->input('password')),
            'is_developer' => $this->canManageDevelopers() && $requestedDeveloper,
            'email_verified_at' => now(),
        ]);

        return redirect()->route($this->admin_base_url)->with("success","Successfully Added");
    }

  
    public function show($id){
        return redirect()->route($this->admin_base_url);
    }

   
    public function edit($id){
        $data=$this->model::find($id);
        if($data){
            if (!$this->canTouch($data)) {
                return redirect()->route($this->admin_base_url)->with("danger","Developer accounts can only be managed by another developer.");
            }

            $canManageDevelopers = $this->canManageDevelopers();

            return view($this->admin_view.".edit",compact("data", "canManageDevelopers"));
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }

  
    public function update(Request $request, $id){
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:10', 'confirmed'],
        ]);   
        if($validator->fails()){
            return redirect()->back()->withInput()->with("danger",$validator->errors()->first())->withErrors($validator->errors());
        }
        $exist=$this->model::find($id);
        if($exist){
            if (!$this->canTouch($exist)) {
                return redirect()->route($this->admin_base_url)->with("danger","Developer accounts can only be managed by another developer.");
            }

            $email = strtolower(trim((string) $request->input('email')));
            $requestedDeveloper = $request->boolean('is_developer') || FeatureFlags::emailWouldBeDeveloper($email);

            if ($requestedDeveloper && !$this->canManageDevelopers()) {
                return redirect()->back()
                    ->withInput($request->except('password', 'password_confirmation'))
                    ->with("danger", "Only developer accounts can create another developer account.");
            }

            if ($this->canManageDevelopers()
                && (int) auth()->id() === (int) $exist->id
                && FeatureFlags::isDeveloper($exist)
                && !$requestedDeveloper) {
                return redirect()->back()
                    ->withInput($request->except('password', 'password_confirmation'))
                    ->with("danger", "You cannot remove developer access from your own account.");
            }

            $data = [
                'name' => trim((string) $request->input('name')),
                'email' => $email,
            ];

            if ($request->filled('password')) {
                $data['password'] = Hash::make((string) $request->input('password'));
            }

            if ($this->canManageDevelopers()) {
                $data['is_developer'] = $requestedDeveloper;
            }

            $exist->update($data);
            return redirect()->route($this->admin_base_url)->with("success","Successfully Updated");
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }

    public function destroy($id){
        $exist=$this->model::find($id);
        if($exist){
            if ((int) auth()->id() === (int) $exist->id) {
                return redirect()->route($this->admin_base_url)->with("danger","You cannot delete your own account.");
            }

            if (!$this->canTouch($exist)) {
                return redirect()->route($this->admin_base_url)->with("danger","Developer accounts can only be managed by another developer.");
            }

            if (FeatureFlags::isDeveloper($exist) && $this->developerCount() <= 1) {
                return redirect()->route($this->admin_base_url)->with("danger","At least one developer account must remain.");
            }

            $exist->delete();
            return redirect()->route($this->admin_base_url)->with("success","Successfully Deleted");
        }
        return redirect()->route($this->admin_base_url)->with("danger","Invalid Calling");
    }

    private function canManageDevelopers(): bool
    {
        return FeatureFlags::isDeveloper(auth()->user());
    }

    private function canTouch(User $user): bool
    {
        return $this->canManageDevelopers() || !FeatureFlags::isDeveloper($user);
    }

    private function developerCount(): int
    {
        return User::query()
            ->get()
            ->filter(fn (User $user) => FeatureFlags::isDeveloper($user))
            ->count();
    }
}
