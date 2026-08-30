<?php

use App\Http\Controllers\VelzonRoutesController;
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

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified',])->group(function () {
    
    Route::controller(VelzonRoutesController::class)->group(function () {

        // dashboards
        Route::get('/', 'dashboard');
        Route::get('/socios', 'members');
        Route::get('/cupones', 'coupons');

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
