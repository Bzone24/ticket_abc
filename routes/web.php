<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::controller(AuthController::class)->group(function () {
    Route::get('login', 'showLoginForm')->name('login');
    Route::post('login', 'login');
});

// Authentication Routes
Route::middleware('auth:web')->group(function () {

    Route::controller(DashboardController::class)->prefix('dashboard')->group(function () {
        Route::get('/', 'index')->name('dashboard');
        Route::get('add-ticket', 'addTicket')->name('ticket.add');
        // Route::get('/option-list', 'optionList')->name('dashboard.option.list');
        Route::get('/draw-details-list', 'drawDetailsList')->name('dashboard.draw.details.list');
        Route::get('/total-qty-detail-list/{drawDetail}', 'totalQtyDetailList')->name('dashboard.draw.total.qty.list.details');
        Route::get('cross-abc-detail-list', 'crossAbcList')->name('dashboard.draw.cross.abc.details.list');
        Route::get('cross-ab-list', 'getCrossAcList')->name('dashboard.draw.cross.ac.list');
        Route::get('cross-bc-list', 'getCrossBcList')->name('dashboard.draw.cross.bc.list');

    });

    // Logout User
    Route::get('logout', function () {
        Auth::logout();
        // request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    })->name('logout');
});
