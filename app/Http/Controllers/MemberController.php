<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $members = User::query()->where('role', 'member')
            ->withCount(['couponAssignments as available_coupons_count' => fn ($q) => $q->where('status', 'available')])
            ->when($request->search, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('dni', 'like', "%{$search}%")->orWhere('member_code', 'like', "%{$search}%")))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()->paginate(15)->withQueryString();

        return Inertia::render('members/index', [
            'members' => $members,
            'coupons' => Coupon::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('search', 'status'),
            'temporaryCredentials' => session('temporaryCredentials'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'dni' => ['nullable', 'digits:8', 'unique:users,dni'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'coupon_ids' => ['array'],
            'coupon_ids.*' => ['integer', 'exists:coupons,id'],
        ]);

        $plainPassword = Str::upper(Str::random(4)).random_int(1000, 9999);
        $member = DB::transaction(function () use ($data, $plainPassword) {
            $member = User::create([
                'name' => $data['name'], 'dni' => $data['dni'] ?? null, 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null,
                'password' => Hash::make($plainPassword), 'role' => 'member', 'status' => 'active',
                'must_change_password' => true, 'email_verified_at' => now(),
            ]);
            $member->update(['member_code' => 'HMR-'.str_pad((string) $member->id, 6, '0', STR_PAD_LEFT)]);
            $ids = $data['coupon_ids'] ?? Coupon::where('is_active', true)->pluck('id')->all();
            foreach ($ids as $couponId) $member->couponAssignments()->create(['coupon_id' => $couponId, 'assigned_at' => now()]);
            return $member;
        });

        return back()->with('temporaryCredentials', [
            'name' => $member->name, 'user' => $member->member_code, 'password' => $plainPassword,
            'phone' => $member->phone, 'login_url' => url('/login'),
        ]);
    }

    public function toggleStatus(User $member)
    {
        abort_unless($member->role === 'member', 404);
        $member->update(['status' => $member->status === 'active' ? 'blocked' : 'active']);
        return back()->with('success', 'Estado del socio actualizado.');
    }
}
