<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Coupon;
use App\Models\CouponAssignment;
use App\Services\CouponQrToken;

class VelzonRoutesController extends Controller
{
    //

    public function dashboard(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            $assignments = $request->user()->couponAssignments()->with('coupon')->latest('assigned_at')->get()->map(fn ($assignment) => [
                'id' => $assignment->id,
                'status' => $assignment->status,
                'redeemed_at' => $assignment->redeemed_at?->format('d/m/Y H:i'),
                'coupon' => [
                    'name' => $assignment->coupon->name,
                    'description' => $assignment->coupon->description,
                    'valid_until' => $assignment->coupon->valid_until->format('d/m/Y'),
                ],
            ]);
            return Inertia::render('member/Dashboard', ['assignments' => $assignments]);
        }

        return Inertia::render('dashboards/ecommerce/index', [
            'stats' => [
                'members' => User::where('role', 'member')->where('status', 'active')->count(),
                'assigned' => CouponAssignment::count(),
                'redeemed' => CouponAssignment::where('status', 'redeemed')->whereMonth('redeemed_at', now()->month)->count(),
                'expiring' => Coupon::whereBetween('valid_until', [today(), today()->addDays(7)])->count(),
            ],
            'activity' => CouponAssignment::with(['member:id,name', 'coupon:id,name'])->where('status', 'redeemed')->latest('redeemed_at')->limit(6)->get()->map(fn ($item) => [
                'name' => $item->member->name, 'coupon' => $item->coupon->name, 'time' => $item->redeemed_at->diffForHumans(),
            ]),
        ]);
    }

    public function refreshCouponQr(Request $request, CouponAssignment $assignment)
    {
        abort_unless($assignment->user_id === $request->user()->id, 403);
        $assignment->load('coupon');
        abort_unless(
            $assignment->status === 'available' && $assignment->coupon->is_active && !$assignment->coupon->valid_until->lt(today()),
            422,
            'Este cupón ya no está disponible.'
        );

        return response()->json([
            'token' => CouponQrToken::issue($assignment),
            'expires_at' => now()->addMinutes(5)->timestamp,
        ]);
    }


    public function pages_starter() {
        return Inertia::render('pages/starter');
    }

    public function pages_maintenance() {
        return Inertia::render('pages/maintenance');
    }

    public function pages_coming_soon() {
        return Inertia::render('pages/coming-soon');
    }

    public function auth_signin_basic() {
        return Inertia::render('auth-pages/signin/basic');
    }

    public function auth_signin_cover() {
        return Inertia::render('auth-pages/signin/cover');
    }

    public function auth_signup_basic() {
        return Inertia::render('auth-pages/signup/basic');
    }

    public function auth_signup_cover() {
        return Inertia::render('auth-pages/signup/cover');
    }

    public function auth_reset_pwd_basic() {
        return Inertia::render('auth-pages/reset/basic');
    }

    public function auth_reset_pwd_cover() {
        return Inertia::render('auth-pages/reset/cover');
    }

    public function auth_create_pwd_basic() {
        return Inertia::render('auth-pages/create/basic');
    }

    public function auth_create_pwd_cover() {
        return Inertia::render('auth-pages/create/cover');
    }

    public function auth_lockscreen_basic() {
        return Inertia::render('auth-pages/lockscreen/basic');
    }

    public function auth_lockscreen_cover() {
        return Inertia::render('auth-pages/lockscreen/cover');
    }

    public function auth_twostep_basic() {
        return Inertia::render('auth-pages/twostep/basic');
    }

    public function auth_twostep_cover() {
        return Inertia::render('auth-pages/twostep/cover');
    }

    public function auth_404() {
        return Inertia::render('auth-pages/errors/404');
    }

    public function auth_500() {
        return Inertia::render('auth-pages/errors/500');
    }

    public function auth_404_basic() {
        return Inertia::render('auth-pages/errors/404-basic');
    }

    public function auth_404_cover() {
        return Inertia::render('auth-pages/errors/404-cover');
    }

    public function auth_ofline() {
        return Inertia::render('auth-pages/errors/ofline');
    }

    public function auth_logout_basic() {
        return Inertia::render('auth-pages/logout/basic');
    }

    public function auth_logout_cover() {
        return Inertia::render('auth-pages/logout/cover');
    }

    public function auth_success_msg_basic() {
        return Inertia::render('auth-pages/success-msg/basic');
    }

    public function auth_success_msg_cover() {
        return Inertia::render('auth-pages/success-msg/cover');
    }

}
