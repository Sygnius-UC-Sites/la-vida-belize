<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Hook fired after a successful authentication. Flashes a one-time
     * welcome message so the dashboard can render the toast on landing.
     */
    protected function authenticated($request, $user)
    {
        $hour  = (int) now()->format('G');
        $tod   = $hour < 5 ? 'Good evening' : ($hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening'));
        $first = trim(strtok((string) ($user->name ?? ''), ' '));
        $name  = $first ?: ($user->email ?? 'there');
        session()->flash('welcome_back', $tod . ', ' . $name);
    }
}
