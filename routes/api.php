<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes  — prefix: /api/v1  (set in bootstrap/app.php)
| Matches: Pawsitive-API-Endpoints.md (30 endpoints)
|--------------------------------------------------------------------------
*/

// ── Health ────────────────────────────────────────────────────────────────────
Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'Pawsitive API']));

// ── Public: Pets ──────────────────────────────────────────────────────────────
// GET  /pets   — q, species[], breed, age_min, age_max, gender, size[], color,
//                price_min, price_max, behaviour[], latitude, longitude,
//                radius_km, sort_by, sort_order, page, per_page
Route::get('/pets',      [PetController::class, 'index']);
Route::get('/pets/{id}', [PetController::class, 'show']);

// ── Public: Order tracking (no auth) — GET /orders/{order_number}?email= ─────
Route::get('/orders/{orderNumber}', [OrderController::class, 'track']);

// ── Auth (rate-limited) ───────────────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:10,1');

// ── Cart — optional auth (guests use X-Session-Id, logged-in users use token) ─
// Sanctum optional: auth()->user() is null for guests, the controllers handle both
Route::middleware('auth:sanctum')->withoutMiddleware([\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class])->group(function () {
    // These three allow guest access because Sanctum won't 401 if no token present
    // when registered as optional — we handle guest vs user inside the controllers.
});

// NOTE: Cart routes are intentionally PUBLIC (no auth:sanctum guard).
// Guest identity is tracked by X-Session-Id header.
// Authenticated users' cart is also accessible (controller checks auth()->user()).
Route::get('/cart',                  [CartController::class, 'index']);   // GET  /cart
Route::post('/cart/items',           [CartController::class, 'add']);     // POST /cart/items
Route::delete('/cart/items/{id}',    [CartController::class, 'remove']);  // DELETE /cart/items/{id}

// ── Authenticated (strict — token required) ───────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout',  [AuthController::class, 'logout']);   // POST /logout
    Route::get('/profile',  [AuthController::class, 'me']);       // GET  /profile

    // Cart — merge guest cart into user account after login
    Route::put('/cart',     [CartController::class, 'sync']);     // PUT  /cart

    // Orders (customer)
    Route::post('/orders',  [OrderController::class, 'place']);   // POST /orders
    Route::get('/orders',   [OrderController::class, 'history']); // GET  /orders
});

// ── Admin (`role:admin` — token + admin role required) ───────────────────────
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {

    // Pets — CRUD
    Route::get('/pets',               [Admin\PetController::class, 'index']);    // GET  /admin/pets
    Route::post('/pets',              [Admin\PetController::class, 'store']);    // POST /admin/pets
    Route::get('/pets/{id}',          [Admin\PetController::class, 'show']);     // GET  /admin/pets/{id}
    Route::put('/pets/{id}',          [Admin\PetController::class, 'update']);   // PUT  /admin/pets/{id}
    Route::delete('/pets/{id}',       [Admin\PetController::class, 'destroy']); // DELETE /admin/pets/{id}

    // Pet images
    Route::post('/pets/{id}/images',                                   [Admin\PetController::class, 'uploadImages']);
    Route::patch('/pets/{petId}/images/{imageId}/thumbnail',           [Admin\PetController::class, 'setThumbnail']);
    Route::delete('/pets/{petId}/images/{imageId}',                    [Admin\PetController::class, 'deleteImage']);

    // Orders
    Route::get('/orders',                  [Admin\OrderController::class, 'index']);        // GET  /admin/orders
    Route::get('/orders/{id}',             [Admin\OrderController::class, 'show']);         // GET  /admin/orders/{id}
    Route::patch('/orders/{id}/status',    [Admin\OrderController::class, 'updateStatus']); // PATCH /admin/orders/{id}/status
    Route::delete('/orders/{id}',          [Admin\OrderController::class, 'cancel']);       // DELETE /admin/orders/{id}

    // Deliveries — GET /admin/deliveries?month=YYYY-MM
    Route::get('/deliveries',      [Admin\DeliveryController::class, 'index']);
    Route::patch('/deliveries/{id}', [Admin\DeliveryController::class, 'update']);

    // Analytics
    Route::get('/analytics/sales',      [Admin\AnalyticsController::class, 'sales']);
    Route::get('/analytics/inventory',  [Admin\AnalyticsController::class, 'inventory']);
    Route::get('/analytics/customers',  [Admin\AnalyticsController::class, 'customers']);

    // Settings
    Route::get('/settings', [Admin\SettingsController::class, 'index']);
    Route::put('/settings', [Admin\SettingsController::class, 'updateAll']); // PUT — bulk update
});
