<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Rule;
use App\Repositories\Admin\AdminRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AdminsController extends Controller
{
    protected $adminRepository;

    public function __construct(AdminRepository $adminRepository)
    {
        $this->adminRepository = $adminRepository;
    }

    /**
     * A non-super-admin must never be able to touch a super_admin account
     * (change its password, edit it or delete it) — otherwise any staff
     * admin with the matching ability could take over the super admin.
     */
    private function ensureTargetIsNotProtected(Admin $target): void
    {
        $actor = Auth::guard('admin')->user();

        if ($target->super_admin && ! $actor?->super_admin) {
            abort(403);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('admin.view');
        $admins = $this->adminRepository->getAll();
        return view('dashboard.admins.index', compact('admins'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('admin.create');
        $rules = Rule::all();
        //$adminRules = $admin->rules.blade.php()->pluck('id')->toArray();
        return view('dashboard.admins.create', \compact('rules'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize('admin.create');
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'new_password' => 'required|confirmed|min:6',
            'rules' => 'required|array',
            'rules.*' => 'exists:rules,id',
        ]);

        // Validated data only — never $request->all(), which could carry
        // extra fields (e.g. super_admin) or non-existent rule ids.
        $this->adminRepository->store($data);
        return to_route('admins.index')->with('success', __('messages.ADMIN_CREATED'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        Gate::authorize('admin.edit');

        $admin = Admin::findOrFail($id);
        $this->ensureTargetIsNotProtected($admin);
        $rules = Rule::all();
        $adminRules = $admin->rules()->pluck('id')->toArray();
        return view("dashboard.admins.edit", compact("admin", 'rules', 'adminRules'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        Gate::authorize('admin.edit');

        $this->ensureTargetIsNotProtected(Admin::findOrFail($id));

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email,' . $id,
            'rules' => 'required|array',
            'rules.*' => 'exists:rules,id',
        ]);

        $this->adminRepository->update($data, $id);

        return to_route('admins.index')->with('success', __('messages.ADMIN_UPDATED'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Gate::authorize('admin.delete');
        $this->ensureTargetIsNotProtected(Admin::findOrFail($id));
        $this->adminRepository->delete($id);
        return back()->with('dark', __('messages.ADMIN_DELETED'));
    }

    public function ChangePassword(Request $request, $id)
    {
        Gate::authorize('admin.change_password');

        $this->ensureTargetIsNotProtected(Admin::findOrFail($id));

        $request->validate([
            'new_password' => 'required|confirmed|min:6'
        ]);

        $data = $request->validate(['new_password' => 'required|confirmed|min:6']);
        $this->adminRepository->changePassword($id, $data);

        return back()->with('success', __('messages.ADMIN_UPDATE_PASSWORD'));
    }

    public function login(Request $request)
    {
        // Validate the input
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        // Brute-force guard: 5 failed admin logins per email + IP, then
        // locked out for a minute (same limit as the web LoginRequest).
        $throttleKey = 'admin-login:' . strtolower($request->email) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->with('error', 'لقد تجاوزت الحد المسموح به. يرجى الانتظار ' . ceil($seconds / 60) . ' دقيقة قبل المحاولة مجدداً.');
        }

        // Attempt to authenticate the admin
        if (Auth::guard('admin')->attempt([
            'email' => $request->email,
            'password' => $request->password
        ])) {
            RateLimiter::clear($throttleKey);
            // Regenerate the session id so a pre-login session id cannot
            // be reused after authentication (session fixation).
            $request->session()->regenerate();
            // Redirect to admin dashboard
            return redirect()->route('dashboard.index');
        }

        RateLimiter::hit($throttleKey, 60);

        // Authentication failed
        return back()->with('error', 'خطأ اثناء تسجيل الدخول');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
