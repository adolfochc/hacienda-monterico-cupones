<?php

use App\Http\Controllers\VelzonRoutesController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\FirstPasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->group(function () {
    Route::get('/primera-contrasena', [FirstPasswordController::class, 'edit'])->name('password.first.edit');
    Route::put('/primera-contrasena', [FirstPasswordController::class, 'update'])->name('password.first.update');

    Route::middleware('password.changed')->group(function () {
        Route::get('/', [VelzonRoutesController::class, 'dashboard']);
        Route::post('/mis-cupones/{assignment}/qr', [VelzonRoutesController::class, 'refreshCouponQr'])->name('member.coupons.qr');

        Route::middleware('admin')->group(function () {
            Route::get('/socios', [MemberController::class, 'index'])->name('members.index');
            Route::post('/socios', [MemberController::class, 'store'])->name('members.store');
            Route::patch('/socios/{member}/estado', [MemberController::class, 'toggleStatus'])->name('members.status');
            Route::get('/cupones', [CouponController::class, 'index'])->name('coupons.index');
            Route::post('/cupones', [CouponController::class, 'store'])->name('coupons.store');
            Route::post('/cupones/{coupon}/asignar', [CouponController::class, 'assign'])->name('coupons.assign');
            Route::post('/asignaciones/{assignment}/canjear', [CouponController::class, 'redeem'])->name('coupons.redeem');
            Route::post('/canjes/qr/validar', [CouponController::class, 'validateQr'])->name('coupons.qr.validate');
            Route::post('/canjes/qr/canjear', [CouponController::class, 'redeemQr'])->name('coupons.qr.redeem');
        });
    
    Route::controller(VelzonRoutesController::class)->group(function () {

        // dashboards
        // pages routes
        Route::get("/pages/starter", "pages_starter"); 
        Route::get("/pages/maintenance", "pages_maintenance"); 
        Route::get("/pages/coming-soon", "pages_coming_soon"); 

        // auth sample page routes
        Route::get("/auth/signin-basic", "auth_signin_basic");
        Route::get("/auth/signin-cover", "auth_signin_cover");
        Route::get("/auth/signup-basic", "auth_signup_basic");
        Route::get("/auth/signup-cover", "auth_signup_cover");
        Route::get("/auth/reset-pwd-basic", "auth_reset_pwd_basic");
        Route::get("/auth/reset-pwd-cover", "auth_reset_pwd_cover");
        Route::get("/auth/create-pwd-basic", "auth_create_pwd_basic");
        Route::get("/auth/create-pwd-cover", "auth_create_pwd_cover");
        Route::get("/auth/lockscreen-basic", "auth_lockscreen_basic");
        Route::get("/auth/lockscreen-cover", "auth_lockscreen_cover");
        Route::get("/auth/twostep-basic", "auth_twostep_basic");
        Route::get("/auth/twostep-cover", "auth_twostep_cover");
        Route::get("/auth/404", "auth_404");
        Route::get("/auth/500", "auth_500");
        Route::get("/auth/404-basic", "auth_404_basic");
        Route::get("/auth/404-cover", "auth_404_cover");
        Route::get("/auth/ofline", "auth_ofline");
        Route::get("/auth/logout-basic", "auth_logout_basic");
        Route::get("/auth/logout-cover", "auth_logout_cover");
        Route::get("/auth/success-msg-basic", "auth_success_msg_basic");
        Route::get("/auth/success-msg-cover", "auth_success_msg_cover");

        });
    });
});
