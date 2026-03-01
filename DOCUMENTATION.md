# Pawsitive API Documentation

**Laravel Pet Store E-commerce Backend**

A comprehensive e-commerce backend for a pet store application with customer shopping, admin management, analytics, and delivery tracking features.

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Services](#services)
3. [Models](#models)
4. [Controllers](#controllers)
5. [Middleware](#middleware)
6. [Request Validation](#request-validation)
7. [Exceptions](#exceptions)
8. [Jobs & Mail](#jobs--mail)
9. [API Endpoints](#api-endpoints)

---

## Architecture Overview

The application follows a **Service Layer Architecture** with:
- **Controllers** — Handle HTTP requests, validate input, delegate to services
- **Services** — Contain business logic, interact with models
- **Models** — Eloquent ORM models with relationships and helper methods
- **Middleware** — Request filtering and authorization
- **Form Requests** — Input validation

---

## Services

### AuthService
**Location:** `app/Services/AuthService.php`

Handles user authentication with rate limiting and account lockout protection.

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `register(array $data)` | `['name', 'email', 'password', 'phone?']` | `['user' => User, 'token' => string]` | Creates a new customer account with Sanctum API token |
| `login(string $email, string $password)` | Email and password | `['user' => User, 'token' => string]` | Authenticates user with 5-attempt lockout (15-minute window). Resets failed attempts on success. |
| `logout(User $user)` | Authenticated user | `void` | Revokes current access token |

**Security Features:**
- Maximum 5 failed login attempts before 15-minute lockout
- Automatic lockout reset after successful login
- Role-based token abilities (`role:customer`, `role:admin`)

---

### CartService
**Location:** `app/Services/CartService.php`

Manages shopping cart with inventory locking system to prevent overselling.

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `addItem(string $petId, ?string $userId, string $sessionId)` | Pet ID, optional user ID, session ID | `CartItem` | Adds pet to cart with 15-minute inventory lock. Prevents double-reservation. |
| `getCart(?string $userId, string $sessionId)` | User ID or session ID | `Collection<CartItem>` | Returns cart items with pet thumbnails. Refreshes lock timer on read. |
| `removeItem(string $petId, ?string $userId, string $sessionId)` | Pet ID, user/session context | `void` | Removes item and releases inventory lock |
| `removeItemById(string $cartItemId, ?string $userId, string $sessionId)` | Cart item UUID, user/session context | `void` | Removes by cart item ID instead of pet ID |
| `mergeGuestCart(string $sessionId, string $userId)` | Guest session ID, authenticated user ID | `void` | Transfers guest cart items to authenticated user after login |
| `releaseExpiredLocks()` | None | `int` | Clears expired cart locks, sets pets back to 'available'. Returns count released. |

**Inventory Lock Flow:**
1. Guest/User adds pet → Pet status set to `reserved`, `locked_until` set to 15 minutes
2. Lock expires OR checkout completes → Pet status changes accordingly
3. Background job runs `releaseExpiredLocks()` every minute

---

### OrderService
**Location:** `app/Services/OrderService.php`

Handles order creation and status management with atomic transactions.

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `create(array $data, ?string $userId, string $sessionId)` | Order data, user/session context | `Order` | Creates order from cart in single transaction. Captures pet snapshots, sends confirmation email. |
| `updateStatus(Order $order, string $newStatus, string $adminId, ?string $notes, ?string $cancellationReason)` | Order, new status, admin ID, optional notes | `Order` | Admin status transition with history tracking. Restores inventory on cancellation. |

**Order Creation Flow:**
1. Validates cart is not empty
2. Extends cart locks during checkout
3. Creates delivery address
4. Creates order with calculated totals
5. Creates order items with snapshot data (name, breed, species, price)
6. Sets pets to `sold` status
7. Deletes cart items
8. Creates status history entry
9. Creates delivery record
10. Sends confirmation email

**Status Transitions:**
- `pending` → `confirmed` | `cancelled`
- `confirmed` → `out_for_delivery` | `cancelled`
- `out_for_delivery` → `delivered` | `cancelled`

---

### PetService
**Location:** `app/Services/PetService.php`

Pet catalog management with filtering, search, and geolocation.

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `list(array $filters)` | Filter array | `LengthAwarePaginator` | Public storefront listing with filters, search, and geo-proximity |
| `adminList(array $filters)` | Filter array | `LengthAwarePaginator` | Admin listing with optional soft-deleted pets |
| `findOrFail(string $id)` | Pet UUID | `Pet` | Retrieves pet with images and behaviours |
| `create(array $data, array $images)` | Pet data, optional image files | `Pet` | Creates pet with behaviours and images in transaction |
| `update(Pet $pet, array $data, array $images)` | Pet instance, update data, optional images | `Pet` | PATCH update with optional behaviour sync |
| `delete(Pet $pet)` | Pet instance | `void` | Soft-delete with guards for active carts/orders |

**Available Filters:**
- `species`, `breed`, `gender`, `size`, `color`
- `min_price`, `max_price`, `min_age`, `max_age`
- `behaviour` — Filter by behaviour tag
- `search` — Fuzzy name search using PostgreSQL `%` operator
- `lat`, `lng`, `radius_km` — Geospatial proximity search (PostGIS)
- `sort_by` — `price`, `age_months`, `created_at`
- `sort_dir` — `asc`, `desc`

---

### MediaService
**Location:** `app/Services/MediaService.php`

Pet image upload and management.

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `storeImages(Pet $pet, array $files)` | Pet, array of UploadedFile | `array<PetImage>` | Stores images in `storage/app/public/pets/{pet_id}/`. First image auto-set as thumbnail. |
| `setThumbnail(Pet $pet, string $imageId)` | Pet, image UUID | `PetImage` | Sets specific image as thumbnail, clears previous |
| `deleteImage(Pet $pet, string $imageId)` | Pet, image UUID | `void` | Deletes from disk and database. Guards against deleting only thumbnail. |

---

### DeliveryService
**Location:** `app/Services/DeliveryService.php`

Delivery scheduling and tracking.

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `updateStatus(Delivery $delivery, array $data)` | Delivery instance, update data | `Delivery` | Updates status, sets timestamps (`dispatched_at`, `delivered_at`). Auto-updates order status on delivery. |
| `getCalendar(int $year, int $month)` | Year, month | `Collection` | Returns deliveries grouped by date for calendar view |

**Delivery Statuses:** `pending`, `dispatched`, `delivered`

---

### AnalyticsService
**Location:** `app/Services/AnalyticsService.php`

Admin dashboard metrics.

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `getSales(string $from, string $to)` | Date range | `array` | Total orders, revenue, daily breakdown (excludes cancelled) |
| `getInventory()` | None | `array` | Count by status: `available`, `reserved`, `sold` |
| `getCustomers()` | None | `array` | Total customers, customers with orders, guest order count |

---

### GeolocationService
**Location:** `app/Services/GeolocationService.php`

IP-based and coordinate geolocation using BigDataCloud API.

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `detectFromIp(string $ip)` | Public IP address | `?array` | Returns `['lat', 'lng', 'city', 'country']` or null. Validates against private/reserved IPs. |
| `reverseGeocode(float $lat, float $lng)` | Coordinates | `?array` | Returns `['city', 'locality', 'country']` or null |

---

### SettingsService
**Location:** `app/Services/SettingsService.php`

System-wide configuration management with type casting.

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `all()` | None | `array` | Returns all settings as key-value pairs with typed values |
| `get(string $key)` | Setting key | `mixed` | Single setting with type casting |
| `set(string $key, string $rawValue, string $updatedBy)` | Key, value, admin ID | `SystemSetting` | Updates setting with type validation |

**Supported Types:** `string`, `integer`, `boolean`, `json`

---

### NotificationService
**Location:** `app/Services/NotificationService.php`

Email notification dispatch.

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `sendOrderConfirmation(Order $order)` | Order with relations | `void` | Sends confirmation email with PDF receipt to user or guest email |

---

## Models

### User
**Location:** `app/Models/User.php`

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | UUID | Primary key |
| `name` | string | Display name |
| `email` | string | Unique email |
| `password` | hashed | Password hash |
| `phone` | ?string | Phone number |
| `role` | string | `customer` or `admin` |
| `failed_login_attempts` | int | Login failure counter |
| `locked_until` | ?datetime | Account lockout expiry |

**Relationships:**
- `pets()` — HasMany (created pets, admin only)
- `cartItems()` — HasMany
- `orders()` — HasMany
- `orderStatusChanges()` — HasMany

**Methods:**
- `isAdmin(): bool` — Checks if user is admin
- `isLocked(): bool` — Checks if account is locked

---

### Pet
**Location:** `app/Models/Pet.php`

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | UUID | Primary key |
| `name` | string | Pet name |
| `species` | string | e.g., "dog", "cat" |
| `breed` | ?string | Breed name |
| `age_months` | int | Age in months |
| `gender` | string | `male` or `female` |
| `size` | ?string | `small`, `medium`, `large`, `extra_large` |
| `color` | ?string | Color description |
| `price` | float | Price in currency |
| `health_records` | ?string | Health notes |
| `description` | ?string | Description |
| `status` | string | `available`, `reserved`, `sold` |
| `latitude` | ?float | Geo coordinate |
| `longitude` | ?float | Geo coordinate |
| `location_name` | ?string | Human-readable location |
| `geo_point` | geography | PostGIS point (synced via `syncGeoPoint()`) |

**Relationships:**
- `creator()` — BelongsTo User
- `behaviours()` — HasMany PetBehaviour
- `images()` — HasMany PetImage (sorted)
- `thumbnail()` — HasOne PetImage (is_thumbnail=true)
- `cartItem()` — HasOne CartItem
- `orderItems()` — HasMany OrderItem

**Methods:**
- `isAvailable(): bool` — Checks availability
- `syncGeoPoint(): void` — Updates PostGIS `geo_point` column from lat/lng

---

### Order
**Location:** `app/Models/Order.php`

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | UUID | Primary key |
| `order_number` | string | Unique `ORD-XXXXXX` format |
| `user_id` | ?UUID | Foreign key (null for guests) |
| `guest_contact_id` | ?UUID | Foreign key for guest orders |
| `delivery_address_id` | UUID | Foreign key |
| `subtotal` | float | Items total |
| `delivery_fee` | float | Delivery cost |
| `payment_method` | string | Currently only `cod` |
| `status` | string | Order status |
| `cancellation_reason` | ?string | Cancellation explanation |
| `cancelled_at` | ?datetime | Cancellation timestamp |
| `delivered_at` | ?datetime | Delivery timestamp |
| `notes` | ?string | Customer notes |

**Computed:**
- `total` = `subtotal + delivery_fee`

**Relationships:**
- `user()` — BelongsTo User
- `guestContact()` — BelongsTo GuestContact
- `deliveryAddress()` — BelongsTo Address
- `items()` — HasMany OrderItem
- `statusHistory()` — HasMany OrderStatusHistory
- `delivery()` — HasOne Delivery

---

### CartItem
**Location:** `app/Models/CartItem.php`

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | UUID | Primary key |
| `session_id` | ?string | Guest session identifier |
| `user_id` | ?UUID | Authenticated user |
| `pet_id` | UUID | Reserved pet |
| `locked_until` | datetime | Lock expiration |

**Relationships:**
- `user()` — BelongsTo User
- `pet()` — BelongsTo Pet

**Methods:**
- `isExpired(): bool` — Checks if lock has expired

---

### OrderItem
**Location:** `app/Models/OrderItem.php`

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | UUID | Primary key |
| `order_id` | UUID | Parent order |
| `pet_id` | UUID | Reference (pet may be deleted) |
| `pet_name_snapshot` | string | Name at time of purchase |
| `pet_breed_snapshot` | ?string | Breed at time of purchase |
| `pet_species_snapshot` | string | Species at time of purchase |
| `price_snapshot` | float | Price at time of purchase |

**Relationships:**
- `order()` — BelongsTo Order
- `pet()` — BelongsTo Pet (withTrashed)

---

### PetImage
**Location:** `app/Models/PetImage.php`

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | UUID | Primary key |
| `pet_id` | UUID | Parent pet |
| `file_path` | string | Storage path |
| `file_name` | string | Original filename |
| `mime_type` | string | MIME type |
| `file_size_bytes` | int | File size |
| `is_thumbnail` | bool | Thumbnail flag |
| `sort_order` | int | Display ordering |

**Accessors:**
- `url` — Returns public URL via storage link

---

### PetBehaviour
**Location:** `app/Models/PetBehaviour.php`

Simple tag model for pet behaviours (e.g., "friendly", "playful").

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | UUID | Primary key |
| `pet_id` | UUID | Parent pet |
| `behaviour` | string | Behaviour tag |

---

### Delivery
**Location:** `app/Models/Delivery.php`

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | UUID | Primary key |
| `order_id` | UUID | Parent order |
| `status` | string | `pending`, `dispatched`, `delivered` |
| `scheduled_date` | ?date | Scheduled delivery date |
| `dispatched_at` | ?datetime | Dispatch timestamp |
| `delivered_at` | ?datetime | Delivery timestamp |
| `notes` | ?string | Delivery notes |

---

### Address
**Location:** `app/Models/Address.php`

Immutable delivery addresses.

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | UUID | Primary key |
| `address_line` | string | Street address |
| `city` | ?string | City |
| `area` | ?string | Area/district |

---

### GuestContact
**Location:** `app/Models/GuestContact.php`

Contact info for guest checkouts.

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | UUID | Primary key |
| `email` | string | Email address |
| `name` | string | Name |
| `phone` | ?string | Phone number |

---

### OrderStatusHistory
**Location:** `app/Models/OrderStatusHistory.php`

Audit trail for order status changes.

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | UUID | Primary key |
| `order_id` | UUID | Parent order |
| `status` | string | New status value |
| `changed_by` | ?UUID | Admin who made change |
| `notes` | ?string | Change notes |

---

### SystemSetting
**Location:** `app/Models/SystemSetting.php`

Key-value configuration store.

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | UUID | Primary key |
| `key` | string | Setting key |
| `value` | string | Raw value |
| `type` | string | `string`, `integer`, `boolean`, `json` |
| `description` | ?string | Setting description |
| `updated_by` | ?UUID | Last modifier |

**Methods:**
- `typedValue(): mixed` — Returns value cast to declared type

---

## Controllers

### Public Controllers

#### AuthController
**Location:** `app/Http/Controllers/AuthController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `register(RegisterRequest)` | `POST /register` | Create customer account |
| `login(LoginRequest)` | `POST /login` | Authenticate and get token |
| `logout(Request)` | `POST /logout` | Revoke current token |
| `me(Request)` | `GET /profile` | Get authenticated user |

#### PetController
**Location:** `app/Http/Controllers/PetController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `index(Request)` | `GET /pets` | List available pets with filters |
| `show(string $id)` | `GET /pets/{id}` | Get pet details |

#### CartController
**Location:** `app/Http/Controllers/CartController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `index(Request)` | `GET /cart` | Get cart contents |
| `add(AddToCartRequest)` | `POST /cart/items` | Add pet to cart |
| `remove(Request, string $id)` | `DELETE /cart/items/{id}` | Remove from cart |
| `sync(Request)` | `PUT /cart` | Merge guest cart after login |

#### OrderController
**Location:** `app/Http/Controllers/OrderController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `place(PlaceOrderRequest)` | `POST /orders` | Create order from cart |
| `history(Request)` | `GET /orders` | List user's orders |
| `track(Request, string $orderNumber)` | `GET /orders/{orderNumber}` | Track order by number + email |

---

### Admin Controllers

All prefixed with `/admin` and require `auth:sanctum` + `role:admin` middleware.

#### Admin\PetController
**Location:** `app/Http/Controllers/Admin/PetController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `index(Request)` | `GET /admin/pets` | List all pets (admin view) |
| `show(string $id)` | `GET /admin/pets/{id}` | Get pet details |
| `store(StorePetRequest)` | `POST /admin/pets` | Create new pet |
| `update(UpdatePetRequest, string $id)` | `PUT /admin/pets/{id}` | Update pet |
| `destroy(string $id)` | `DELETE /admin/pets/{id}` | Soft-delete pet |
| `uploadImages(Request, string $id)` | `POST /admin/pets/{id}/images` | Upload pet images |
| `setThumbnail(string $petId, string $imageId)` | `PATCH /admin/pets/{petId}/images/{imageId}/thumbnail` | Set image as thumbnail |
| `deleteImage(string $petId, string $imageId)` | `DELETE /admin/pets/{petId}/images/{imageId}` | Delete pet image |

#### Admin\OrderController
**Location:** `app/Http/Controllers/Admin/OrderController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `index(Request)` | `GET /admin/orders` | List all orders |
| `show(string $id)` | `GET /admin/orders/{id}` | Get order details |
| `updateStatus(UpdateOrderStatusRequest, string $id)` | `PATCH /admin/orders/{id}/status` | Update order status |
| `cancel(Request, string $id)` | `DELETE /admin/orders/{id}` | Cancel order |

#### Admin\DeliveryController
**Location:** `app/Http/Controllers/Admin/DeliveryController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `index(Request)` | `GET /admin/deliveries` | List deliveries or get calendar (`?month=YYYY-MM`) |
| `update(UpdateDeliveryRequest, string $id)` | `PATCH /admin/deliveries/{id}` | Update delivery |

#### Admin\AnalyticsController
**Location:** `app/Http/Controllers/Admin/AnalyticsController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `sales(Request)` | `GET /admin/analytics/sales` | Sales metrics (`?from=&to=`) |
| `inventory()` | `GET /admin/analytics/inventory` | Inventory counts |
| `customers()` | `GET /admin/analytics/customers` | Customer metrics |

#### Admin\SettingsController
**Location:** `app/Http/Controllers/Admin/SettingsController.php`

| Method | Route | Description |
|--------|-------|-------------|
| `index()` | `GET /admin/settings` | Get all settings |
| `updateAll(Request)` | `PUT /admin/settings` | Bulk update settings |

---

## Middleware

### EnsureRole
**Location:** `app/Http/Middleware/EnsureRole.php`

Verifies authenticated user has required role via Sanctum token abilities and `role` column.

```php
// Usage in routes
Route::middleware(['auth:sanctum', 'role:admin'])->group(...)
```

**Logic:**
1. Check user is authenticated
2. Verify token has `role:{role}` ability
3. Verify user's `role` column matches
4. Return 403 on mismatch

---

## Request Validation

### LoginRequest
| Field | Rules |
|-------|-------|
| `email` | required, email |
| `password` | required, string |
| `remember_me` | boolean |

### RegisterRequest
| Field | Rules |
|-------|-------|
| `name` | required, string, max:255 |
| `email` | required, email:rfc,dns, unique:users |
| `password` | required, min:8, confirmed |
| `phone` | nullable, string, max:20 |

### AddToCartRequest
| Field | Rules |
|-------|-------|
| `pet_id` | required, uuid, exists:pets |

### PlaceOrderRequest
| Field | Rules |
|-------|-------|
| `address_line` | required, string, max:500 |
| `city` | nullable, string, max:100 |
| `area` | nullable, string, max:100 |
| `delivery_fee` | required, numeric, min:0 |
| `payment_method` | required, in:cod |
| `notes` | nullable, string, max:1000 |

### StorePetRequest
| Field | Rules |
|-------|-------|
| `name` | required, string, max:255 |
| `species` | required, string, max:100 |
| `breed` | nullable, string, max:100 |
| `age_months` | required, integer, 0-300 |
| `gender` | required, in:male,female |
| `size` | nullable, in:small,medium,large,extra_large |
| `color` | nullable, string, max:100 |
| `price` | required, numeric, 0-50000 |
| `health_records` | nullable, string |
| `description` | nullable, string |
| `status` | nullable, in:available,reserved,sold |
| `latitude` | nullable, numeric, -90 to 90 |
| `longitude` | nullable, numeric, -180 to 180 |
| `location_name` | nullable, string, max:255 |
| `behaviours` | nullable, array of strings |
| `images` | nullable, array of images (max 10, 5MB each) |

---

## Exceptions

All custom exceptions extend `ApiException` and render JSON responses.

| Exception | HTTP Status | Description |
|-----------|-------------|-------------|
| `AuthenticationException` | 401 | Invalid credentials |
| `AuthorizationException` | 403 | Insufficient permissions |
| `ValidationException` | 422 | Input validation failed |
| `NotFoundException` | 404 | Resource not found |
| `BusinessLogicException` | 422 | Business rule violation |
| `ConflictException` | 409 | Resource conflict (e.g., already reserved) |

**Response Format:**
```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    "field": ["Specific error message"]
  }
}
```

---

## Jobs & Mail

### ExpireCartLocks Job
**Location:** `app/Jobs/ExpireCartLocks.php`

Scheduled job that runs every minute to:
1. Find cart items where `locked_until < now()`
2. Set associated pets back to `available`
3. Delete expired cart items

### OrderConfirmationMail
**Location:** `app/Mail/OrderConfirmationMail.php`

Sends order confirmation email with:
- HTML email body from `emails.order_confirmation` view
- PDF receipt attachment generated by DomPDF

---

## API Endpoints

### Public Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/health` | Health check |
| GET | `/pets` | List available pets |
| GET | `/pets/{id}` | Get pet details |
| GET | `/orders/{orderNumber}` | Track order |
| POST | `/register` | Register customer (rate limited) |
| POST | `/login` | Authenticate (rate limited) |
| GET | `/cart` | View cart |
| POST | `/cart/items` | Add to cart |
| DELETE | `/cart/items/{id}` | Remove from cart |

### Authenticated Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/logout` | Logout |
| GET | `/profile` | Get current user |
| PUT | `/cart` | Merge guest cart |
| POST | `/orders` | Place order |
| GET | `/orders` | Order history |

### Admin Endpoints (require `role:admin`)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/pets` | List all pets |
| POST | `/admin/pets` | Create pet |
| GET | `/admin/pets/{id}` | Get pet |
| PUT | `/admin/pets/{id}` | Update pet |
| DELETE | `/admin/pets/{id}` | Delete pet |
| POST | `/admin/pets/{id}/images` | Upload images |
| PATCH | `/admin/pets/{petId}/images/{imageId}/thumbnail` | Set thumbnail |
| DELETE | `/admin/pets/{petId}/images/{imageId}` | Delete image |
| GET | `/admin/orders` | List orders |
| GET | `/admin/orders/{id}` | Get order |
| PATCH | `/admin/orders/{id}/status` | Update status |
| DELETE | `/admin/orders/{id}` | Cancel order |
| GET | `/admin/deliveries` | List/calendar deliveries |
| PATCH | `/admin/deliveries/{id}` | Update delivery |
| GET | `/admin/analytics/sales` | Sales metrics |
| GET | `/admin/analytics/inventory` | Inventory metrics |
| GET | `/admin/analytics/customers` | Customer metrics |
| GET | `/admin/settings` | Get settings |
| PUT | `/admin/settings` | Update settings |

---

## Response Format

### Success Response
```json
{
  "success": true,
  "message": "Optional message",
  "data": { ... }
}
```

### Paginated Response
```json
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field": ["Validation message"]
  }
}
```

---

## Authentication

Uses **Laravel Sanctum** for API token authentication.

1. Register/Login returns a bearer token
2. Include token in subsequent requests: `Authorization: Bearer {token}`
3. Guest carts use `X-Session-Id` header for identification
4. Tokens carry role abilities: `role:customer` or `role:admin`
