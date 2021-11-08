<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


// Route::get('email/verify/{id}/{hash}', [AuthController::class, 'emailVerificationHandler'])->name('verification.verify');

Route::prefix('v1')->group(function () {

    Route:: get('user/verify/{verification_code}', [AuthController::class, 'verifyUser'])->name('user.verify');
    Route:: get('password/reset/{token}', 'Auth\ResetPasswordController@showResetForm')->name('password.request');
    Route:: post('password/reset', 'Auth\ResetPasswordController@postReset')->name('password.reset');

    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::group(['middleware' => ['jwt.verify']], function () {
        Route::get('user/{type}', [UserController::class, 'user']);
    });
});


// Route::get('userall', 'UserController@userAuth')->middleware('jwt.verify');
// Route::get('user', 'AuthController@getAuthenticatedUser')->middleware('jwt.verify');
