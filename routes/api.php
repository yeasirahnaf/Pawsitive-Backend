<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PetController;
use Illuminate\Support\Facades\Route;



Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'Pawsitive API']));

Route::get('/pets',      [PetController::class, 'index']);
Route::get('/pets/{id}', [PetController::class, 'show']);

Route::get('/orders/{orderNumber}', [OrderController::class, 'track']);

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->withoutMiddleware([\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class])->group(function () {

});


Route::get('/cart',                  [CartController::class, 'index']);   
Route::post('/cart/items',           [CartController::class, 'add']);     
Route::delete('/cart/items/{id}',    [CartController::class, 'remove']);  


Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout',  [AuthController::class, 'logout']);   
    Route::get('/profile',  [AuthController::class, 'me']);       
    // Cart — merge guest cart into user account after login
    Route::put('/cart',     [CartController::class, 'sync']);     

    // Orders (customer)
    Route::post('/orders',  [OrderController::class, 'place']);   
    Route::get('/orders',   [OrderController::class, 'history']); 
});

// ── Admin (`role:admin` — token + admin role required) ───────────────────────
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {

    // Pets — CRUD
    Route::get('/pets',               [Admin\PetController::class, 'index']);    
    Route::post('/pets',              [Admin\PetController::class, 'store']);    
    Route::get('/pets/{id}',          [Admin\PetController::class, 'show']);    
    Route::put('/pets/{id}',          [Admin\PetController::class, 'update']);   
    Route::delete('/pets/{id}',       [Admin\PetController::class, 'destroy']); 

    // Pet images
    Route::post('/pets/{id}/images',                                   [Admin\PetController::class, 'uploadImages']);
    Route::patch('/pets/{petId}/images/{imageId}/thumbnail',           [Admin\PetController::class, 'setThumbnail']);
    Route::delete('/pets/{petId}/images/{imageId}',                    [Admin\PetController::class, 'deleteImage']);

    // Orders
    Route::get('/orders',                  [Admin\OrderController::class, 'index']);        
    Route::get('/orders/{id}',             [Admin\OrderController::class, 'show']);         
    Route::patch('/orders/{id}/status',    [Admin\OrderController::class, 'updateStatus']); 
    Route::delete('/orders/{id}',          [Admin\OrderController::class, 'cancel']);      

    // Deliveries — GET /admin/deliveries?month=YYYY-MM
    Route::get('/deliveries',      [Admin\DeliveryController::class, 'index']);
    Route::patch('/deliveries/{id}', [Admin\DeliveryController::class, 'update']);

    // Analytics
    Route::get('/analytics/sales',      [Admin\AnalyticsController::class, 'sales']);
    Route::get('/analytics/inventory',  [Admin\AnalyticsController::class, 'inventory']);
    Route::get('/analytics/customers',  [Admin\AnalyticsController::class, 'customers']);

    // Settings
    Route::get('/settings', [Admin\SettingsController::class, 'index']);
    Route::put('/settings', [Admin\SettingsController::class, 'updateAll']); 
});
