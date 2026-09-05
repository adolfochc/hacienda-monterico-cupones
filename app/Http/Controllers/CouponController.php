<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\CouponAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Services\CouponQrToken;

class CouponController extends Controller
{
    public function staff()
    {
        return Inertia::render('coupons/index', ['staffOnly' => true, 'coupons' => [], 'members' => [], 'assignments' => []]);
    }

    public function index()
    {
        $coupons = Coupon::withCount([
            'assignments',
            'assignments as redeemed_count' => fn ($q) => $q->where('status', 'redeemed'),
            'assignments as available_count' => fn ($q) => $q->where('status', 'available'),
        ])->latest()->get();

        return Inertia::render('coupons/index', [
            'coupons' => $coupons,
            'members' => User::where('role', 'member')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'member_code']),
            'assignments' => CouponAssignment::with(['member:id,name,member_code', 'coupon:id,name,valid_until'])
                ->where('status', 'available')->latest('assigned_at')->limit(100)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'], 'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
        ]);
        $coupon = Coupon::create($data + ['is_active' => true]);
        return back()->with('success', "Cupón {$coupon->name} creado.");
    }

    public function assign(Request $request, Coupon $coupon)
    {
        $data = $request->validate(['all_members' => ['sometimes', 'boolean'], 'user_ids' => ['required_unless:all_members,true', 'array'], 'user_ids.*' => ['integer', 'exists:users,id']]);
        $members = User::where('role', 'member')->where('status', 'active');
        if (!($data['all_members'] ?? false)) {
            if (empty($data['user_ids'])) throw ValidationException::withMessages(['user_ids' => 'Selecciona al menos un socio.']);
            $members->whereIn('id', $data['user_ids']);
        }
        DB::transaction(function () use ($members, $coupon) {
            foreach ($members->orderBy('id')->lockForUpdate()->get() as $member) {
                CouponAssignment::firstOrCreate(['coupon_id' => $coupon->id, 'user_id' => $member->id], ['assigned_at' => now(), 'public_id' => (string) \Illuminate\Support\Str::uuid()]);
            }
        });
        return back()->with('success', 'Cupón asignado correctamente.');
    }

    public function redeem(Request $request, CouponAssignment $assignment)
    {
        DB::transaction(function () use ($assignment, $request) {
            $locked = CouponAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            if ($locked->member->status !== 'active' || $locked->coupon->valid_from->gt(today()) || $locked->status !== 'available' || !$locked->coupon->is_active || now()->toDateString() > $locked->coupon->valid_until->toDateString()) {
                throw ValidationException::withMessages(['coupon' => 'Este cupón ya no está disponible para canje.']);
            }
            $locked->update(['status' => 'redeemed', 'redeemed_at' => now(), 'redeemed_by' => $request->user()->id, 'redemption_note' => $request->input('note'), 'redemption_method' => $request->filled('code') ? 'code' : ($request->filled('token') ? 'qr' : 'manual')]);
            if ($locked->booklet_id && !CouponAssignment::where('booklet_id', $locked->booklet_id)->where('status', 'available')->exists()) {
                $locked->booklet()->update(['status' => 'exhausted']);
            }
        });
        return back()->with('success', 'Cupón canjeado y bloqueado correctamente.');
    }

    public function validateQr(Request $request)
    {
        $data = $request->validate(['token' => ['required_without:code', 'string', 'max:5000'], 'code' => ['required_without:token', 'string', 'regex:/^[0-9\s-]{10,16}$/']]);
        $assignment = $request->filled('code') ? \App\Services\CouponRedemptionCode::resolve($data['code']) : CouponQrToken::resolve($data['token']);
        if ($assignment->coupon->valid_from->gt(today()) || $assignment->status !== 'available' || !$assignment->coupon->is_active || $assignment->coupon->valid_until->lt(today())) {
            throw ValidationException::withMessages(['token' => 'Este cupón ya fue canjeado, venció o no está activo.']);
        }
        if ($assignment->member->status !== 'active') {
            throw ValidationException::withMessages(['token' => 'El socio se encuentra bloqueado.']);
        }

        return response()->json([
            'assignment_id' => $assignment->id,
            'member' => $assignment->member->only(['name', 'member_code']),
            'coupon' => [
                'name' => $assignment->coupon->name,
                'description' => $assignment->coupon->description,
                'valid_until' => $assignment->coupon->valid_until->format('d/m/Y'),
            ],
        ]);
    }

    public function redeemQr(Request $request)
    {
        $data = $request->validate(['token' => ['required_without:code', 'string', 'max:5000'], 'code' => ['required_without:token', 'string', 'regex:/^[0-9\s-]{10,16}$/']]);
        $assignment = $request->filled('code') ? \App\Services\CouponRedemptionCode::resolve($data['code']) : CouponQrToken::resolve($data['token']);
        return $this->redeem($request, $assignment);
    }
}
