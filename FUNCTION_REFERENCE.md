# Function Reference Guide

Complete reference of all functions in the Pawsitive API backend with detailed descriptions of their internal workings.

---

## Table of Contents

1. [AuthService Functions](#authservice-functions)
2. [CartService Functions](#cartservice-functions)
3. [OrderService Functions](#orderservice-functions)
4. [PetService Functions](#petservice-functions)
5. [MediaService Functions](#mediaservice-functions)
6. [DeliveryService Functions](#deliveryservice-functions)
7. [AnalyticsService Functions](#analyticsservice-functions)
8. [GeolocationService Functions](#geolocationservice-functions)
9. [SettingsService Functions](#settingsservice-functions)
10. [NotificationService Functions](#notificationservice-functions)
11. [Model Functions](#model-functions)
12. [Controller Functions](#controller-functions)
13. [Middleware Functions](#middleware-functions)
14. [Job Functions](#job-functions)
15. [Mail Functions](#mail-functions)
16. [Trait Functions](#trait-functions)

---

## AuthService Functions

**File:** `app/Services/AuthService.php`

### `register(array $data): array`

Registers a new customer account and returns authentication credentials.

**How it works:**
1. Creates a new `User` record with the provided name, email, hashed password, and optional phone
2. Sets the role to `'customer'` by default
3. Generates a Sanctum API token with `role:customer` ability
4. Returns an array containing the user object and plaintext token

**Parameters:**
- `$data['name']` — User's display name
- `$data['email']` — User's email address
- `$data['password']` — Plain text password (auto-hashed by model)
- `$data['phone']` — Optional phone number

**Returns:** `['user' => User, 'token' => string]`

---

### `login(string $email, string $password): array`

Authenticates a user with rate limiting and account lockout protection.

**How it works:**
1. Looks up user by email address
2. If user not found, throws `AuthenticationException`
3. Checks if account is currently locked via `isLocked()` method
4. If locked, calculates remaining lockout time and throws `ValidationException`
5. Verifies password using `Hash::check()`
6. On password mismatch:
   - Increments `failed_login_attempts` counter
   - If attempts reach 5 (MAX_ATTEMPTS), sets `locked_until` to 15 minutes from now and resets counter
   - Throws `AuthenticationException`
7. On success:
   - Resets `failed_login_attempts` to 0
   - Clears `locked_until`
   - Creates Sanctum token with role-based ability
8. Returns user and token

**Parameters:**
- `$email` — User's email address
- `$password` — Plain text password to verify

**Returns:** `['user' => User, 'token' => string]`

**Throws:** `AuthenticationException`, `ValidationException`

---

### `logout(User $user): void`

Revokes the current API access token.

**How it works:**
1. Retrieves the current access token from the user via `currentAccessToken()`
2. Deletes the token record from the database
3. Token is immediately invalidated for all future requests

**Parameters:**
- `$user` — The authenticated user instance

---

## CartService Functions

**File:** `app/Services/CartService.php`

### `addItem(string $petId, ?string $userId, string $sessionId): CartItem`

Adds a pet to the shopping cart with inventory locking.

**How it works:**
1. Finds the pet by ID using `findOrFail()` (throws 404 if not found)
2. Checks if pet is available via `isAvailable()` method
3. If not available, throws `BusinessLogicException`
4. Looks for existing cart item for this pet
5. If existing item found:
   - If lock not expired, throws `ConflictException` (pet reserved by another)
   - If expired, deletes the old cart item
6. Updates pet status to `'reserved'`
7. Creates new `CartItem` with:
   - Pet ID
   - User ID (if authenticated) OR session ID (for guests)
   - `locked_until` set to 15 minutes from now

**Parameters:**
- `$petId` — UUID of the pet to add
- `$userId` — Authenticated user's UUID (null for guests)
- `$sessionId` — Guest session identifier

**Returns:** The created `CartItem` instance

**Throws:** `BusinessLogicException`, `ConflictException`

---

### `getCart(?string $userId, string $sessionId): Collection`

Retrieves all cart items for a user or guest session.

**How it works:**
1. Calls `releaseExpiredLocks()` to clean up stale items first
2. If user is authenticated, extends lock time by 15 minutes for their items
3. Builds query with `pet.thumbnail` eager loading
4. Filters by user ID (if authenticated) or session ID (for guests)
5. Returns collection of cart items with pet data

**Parameters:**
- `$userId` — Authenticated user's UUID (null for guests)
- `$sessionId` — Guest session identifier

**Returns:** `Collection` of `CartItem` models

---

### `removeItem(string $petId, ?string $userId, string $sessionId): void`

Removes a cart item by pet ID and releases the inventory lock.

**How it works:**
1. Queries `CartItem` by pet ID
2. Filters by user ID or session ID based on authentication
3. Throws 404 if not found via `firstOrFail()`
4. Updates the associated pet's status back to `'available'`
5. Deletes the cart item record

**Parameters:**
- `$petId` — UUID of the pet to remove
- `$userId` — Authenticated user's UUID
- `$sessionId` — Guest session identifier

---

### `removeItemById(string $cartItemId, ?string $userId, string $sessionId): void`

Removes a cart item by its own ID (alternative to pet ID).

**How it works:**
1. Queries `CartItem` by its UUID
2. Filters by user ID or session ID based on authentication
3. Throws 404 if not found
4. Updates associated pet status to `'available'`
5. Deletes the cart item

**Parameters:**
- `$cartItemId` — UUID of the cart item
- `$userId` — Authenticated user's UUID
- `$sessionId` — Guest session identifier

---

### `mergeGuestCart(string $sessionId, string $userId): void`

Transfers guest cart items to an authenticated user after login.

**How it works:**
1. Finds all cart items matching the guest session ID where user_id is null
2. Updates all found items in bulk:
   - Sets `user_id` to the authenticated user
   - Clears `session_id`
   - Extends `locked_until` by 15 minutes

**Parameters:**
- `$sessionId` — Guest session to transfer from
- `$userId` — Authenticated user to transfer to

---

### `releaseExpiredLocks(): int`

Releases all expired cart locks and restores pet availability.

**How it works:**
1. Queries all cart items where `locked_until` is in the past
2. Iterates through expired items:
   - Updates associated pet status to `'available'` (if pet exists)
   - Deletes the cart item
3. Returns count of released locks

**Returns:** Number of expired locks released

---

## OrderService Functions

**File:** `app/Services/OrderService.php`

### `create(array $data, ?string $userId, string $sessionId): Order`

Creates an order from the current cart in an atomic database transaction.

**How it works:**
1. Wraps entire operation in `DB::transaction()` for atomicity
2. Loads cart items with pets for the user/session
3. Validates cart is not empty (throws `ValidationException` if empty)
4. Extends cart locks by 15 minutes during checkout
5. Creates `Address` record with delivery details
6. Calculates subtotal by summing all pet prices
7. Creates `Order` record with:
   - Generated order number (ORD-XXXXXX format)
   - User ID, address ID, totals, payment method, status 'pending'
8. For each cart item:
   - Creates `OrderItem` with pet snapshots (name, breed, species, price)
   - Updates pet status to `'sold'`
   - Deletes cart item
9. Creates initial `OrderStatusHistory` entry ('pending')
10. Creates `Delivery` record with 'pending' status
11. Sends order confirmation email via `NotificationService`
12. Returns the created order

**Parameters:**
- `$data` — Order data (address_line, city, area, delivery_fee, payment_method, notes)
- `$userId` — Authenticated user's UUID (null for guests)
- `$sessionId` — Guest session identifier

**Returns:** Created `Order` model

**Throws:** `ValidationException`

---

### `updateStatus(Order $order, string $newStatus, string $adminId, ?string $notes, ?string $cancellationReason): Order`

Updates order status with state machine validation and history tracking.

**How it works:**
1. Calls `allowedTransitions()` to get valid next statuses
2. Validates the new status is in the allowed list (throws `BusinessLogicException` if not)
3. Wraps updates in `DB::transaction()`
4. Builds update array with new status
5. Special handling for cancellation:
   - Sets `cancellation_reason` and `cancelled_at`
   - Iterates order items, sets each pet back to `'available'`
6. Special handling for delivery:
   - Sets `delivered_at` timestamp
7. Updates order record
8. Creates `OrderStatusHistory` entry with admin ID and notes
9. Returns fresh order with relationships

**Parameters:**
- `$order` — Order instance to update
- `$newStatus` — Target status
- `$adminId` — Admin user making the change
- `$notes` — Optional notes
- `$cancellationReason` — Required for cancellation

**Returns:** Updated `Order` model

**Throws:** `BusinessLogicException`

---

### `generateOrderNumber(): string` (private)

Generates a unique order number.

**How it works:**
1. Enters a do-while loop
2. Generates `ORD-` prefix with 6 random uppercase alphanumeric characters
3. Checks if number already exists in database
4. Repeats until unique number found
5. Returns the unique order number

**Returns:** Unique order number string (e.g., "ORD-X7K9M2")

---

### `allowedTransitions(string $current): array` (private)

Returns valid status transitions from current state.

**How it works:**
Uses PHP match expression to map current status to allowed next statuses:
- `'pending'` → `['confirmed', 'cancelled']`
- `'confirmed'` → `['out_for_delivery', 'cancelled']`
- `'out_for_delivery'` → `['delivered', 'cancelled']`
- Any other status → `[]` (no transitions allowed)

**Parameters:**
- `$current` — Current order status

**Returns:** Array of valid next status values

---

## PetService Functions

**File:** `app/Services/PetService.php`

### `list(array $filters): LengthAwarePaginator`

Lists pets for the public storefront with comprehensive filtering.

**How it works:**
1. Starts query with `thumbnail` and `behaviours` eager loading
2. Excludes soft-deleted pets
3. Filters by status (defaults to 'available')
4. Applies optional filters if provided:
   - `species`, `breed`, `gender`, `size` — exact match
   - `min_price`, `max_price` — price range
   - `min_age`, `max_age` — age range in months
   - `behaviour` — filters pets having specific behaviour tag
5. Fuzzy search on name using PostgreSQL `%` operator if `search` provided
6. Geospatial search using PostGIS `ST_DWithin` if lat/lng/radius provided
7. Applies sorting (price, age_months, or created_at) with direction
8. Returns paginated results (default 15 per page)

**Parameters:**
- `$filters` — Associative array of filter values

**Returns:** `LengthAwarePaginator` with pet results

---

### `adminList(array $filters): LengthAwarePaginator`

Lists all pets for admin dashboard with extended options.

**How it works:**
1. Starts query with `thumbnail` and `behaviours` eager loading
2. If `include_deleted` filter is true, includes soft-deleted via `withTrashed()`
3. Filters by status if provided
4. Orders by `created_at` descending
5. Returns paginated results (default 20 per page)

**Parameters:**
- `$filters` — Filter options (status, include_deleted, per_page)

**Returns:** `LengthAwarePaginator` with pet results

---

### `findOrFail(string $id): Pet`

Retrieves a single pet with all its images and behaviours.

**How it works:**
1. Queries `Pet` model with `images` and `behaviours` eager loaded
2. Uses `findOrFail()` to throw 404 if not found
3. Returns the pet with relationships

**Parameters:**
- `$id` — Pet UUID

**Returns:** `Pet` model with relationships

---

### `create(array $data, array $images = []): Pet`

Creates a new pet with optional behaviours and images.

**How it works:**
1. Wraps operation in `DB::transaction()` for atomicity
2. Creates `Pet` record with provided data
3. If behaviours provided, calls `syncBehaviours()` to create tags
4. Calls `syncGeoPoint()` to update PostGIS geography column
5. If images provided, calls `MediaService::storeImages()`
6. Returns fresh pet with all relationships loaded

**Parameters:**
- `$data` — Pet attributes
- `$images` — Array of `UploadedFile` objects

**Returns:** Created `Pet` model

---

### `update(Pet $pet, array $data, array $images = []): Pet`

Updates an existing pet with PATCH semantics.

**How it works:**
1. Wraps operation in `DB::transaction()`
2. Updates pet with provided data (partial update)
3. If `behaviours` key exists in data (even if null/empty), syncs behaviours
4. If latitude or longitude changed, refreshes model and syncs geo point
5. If new images provided, stores them via `MediaService`
6. Returns fresh pet with relationships

**Parameters:**
- `$pet` — Pet instance to update
- `$data` — Attributes to update
- `$images` — Optional new images

**Returns:** Updated `Pet` model

---

### `delete(Pet $pet): void`

Soft-deletes a pet with business rule validation.

**How it works:**
1. Checks if pet has an active cart item via `cartItem()->exists()`
2. If in cart, throws `BusinessLogicException`
3. Checks if pet has order items with active orders (not delivered/cancelled)
4. If has active orders, throws `BusinessLogicException`
5. Performs soft-delete via `$pet->delete()`

**Parameters:**
- `$pet` — Pet instance to delete

**Throws:** `BusinessLogicException`

---

### `syncBehaviours(Pet $pet, array $behaviours): void` (private)

Replaces pet's behaviour tags with new set.

**How it works:**
1. Deletes all existing behaviours for the pet
2. Iterates unique behaviour strings
3. Creates new `PetBehaviour` record for each

**Parameters:**
- `$pet` — Pet instance
- `$behaviours` — Array of behaviour strings

---

## MediaService Functions

**File:** `app/Services/MediaService.php`

### `storeImages(Pet $pet, array $files): array`

Stores uploaded images for a pet with automatic thumbnail assignment.

**How it works:**
1. Initializes empty stored array
2. Checks if pet already has a thumbnail image
3. Gets the next sort order value
4. For each uploaded file:
   - Stores file to `storage/app/public/pets/{pet_id}/` using Laravel Storage
   - First image automatically becomes thumbnail if none exists
   - Creates `PetImage` record with path, filename, mime type, size, thumbnail flag, sort order
   - Increments sort order for next image
5. Returns array of created `PetImage` models

**Parameters:**
- `$pet` — Pet to attach images to
- `$files` — Array of `UploadedFile` objects

**Returns:** Array of `PetImage` models

---

### `setThumbnail(Pet $pet, string $imageId): PetImage`

Designates a specific image as the pet's thumbnail.

**How it works:**
1. Finds the image by ID, ensuring it belongs to the pet (throws 404 if not)
2. Clears `is_thumbnail` flag on all other pet images
3. Sets `is_thumbnail` to true on the specified image
4. Returns the refreshed image model

**Parameters:**
- `$pet` — Pet instance
- `$imageId` — UUID of image to set as thumbnail

**Returns:** Updated `PetImage` model

---

### `deleteImage(Pet $pet, string $imageId): void`

Removes an image from disk and database.

**How it works:**
1. Finds the image by ID, ensuring it belongs to the pet
2. Checks if image is the thumbnail AND pet has other images
3. If so, throws `BusinessLogicException` (must reassign thumbnail first)
4. Deletes the file from storage disk
5. Deletes the database record

**Parameters:**
- `$pet` — Pet instance
- `$imageId` — UUID of image to delete

**Throws:** `BusinessLogicException`

---

## DeliveryService Functions

**File:** `app/Services/DeliveryService.php`

### `updateStatus(Delivery $delivery, array $data): Delivery`

Updates delivery status with automatic timestamp management.

**How it works:**
1. Builds update array with non-null values from status, scheduled_date, notes
2. If status is being changed:
   - If changing to 'dispatched' and not already dispatched, sets `dispatched_at` to now
   - If changing to 'delivered' and not already delivered:
     - Sets `delivered_at` to now
     - Also updates parent order status to 'delivered' with timestamp
3. Updates delivery record
4. Returns fresh delivery model

**Parameters:**
- `$delivery` — Delivery instance to update
- `$data` — Update data (status, scheduled_date, notes)

**Returns:** Updated `Delivery` model

---

### `getCalendar(int $year, int $month): Collection`

Returns deliveries grouped by date for calendar view.

**How it works:**
1. Calculates first and last day of the specified month
2. Queries deliveries with order, address, and items eager loaded
3. Filters by `scheduled_date` between first and last day
4. Orders by scheduled date ascending
5. Groups results by date string (Y-m-d format)
6. Returns collection keyed by date

**Parameters:**
- `$year` — Year (e.g., 2026)
- `$month` — Month (1-12)

**Returns:** `Collection` grouped by date string

---

## AnalyticsService Functions

**File:** `app/Services/AnalyticsService.php`

### `getSales(string $from, string $to): array`

Retrieves sales metrics for a date range.

**How it works:**
1. Executes raw SQL query on orders table
2. Groups orders by day using `DATE_TRUNC`
3. Counts orders and sums total (subtotal + delivery_fee)
4. Excludes cancelled orders
5. Filters by date range
6. Calculates aggregate totals from daily results
7. Returns total orders, total revenue, and daily breakdown

**Parameters:**
- `$from` — Start date (Y-m-d)
- `$to` — End date (Y-m-d)

**Returns:** `['total_orders' => int, 'total_revenue' => float, 'by_day' => array]`

---

### `getInventory(): array`

Returns current inventory counts by status.

**How it works:**
1. Executes raw SQL query on pets table
2. Groups by status, counts each group
3. Excludes soft-deleted pets
4. Maps results to status keys
5. Returns counts for available, reserved, sold

**Returns:** `['available' => int, 'reserved' => int, 'sold' => int]`

---

### `getCustomers(): array`

Returns customer-related metrics.

**How it works:**
1. Counts total users with 'customer' role
2. Counts distinct user IDs from orders (customers who purchased)
3. Counts orders where user_id is null (guest orders)
4. Returns all three metrics

**Returns:** `['total_customers' => int, 'customers_ordered' => int, 'guest_orders' => int]`

---

## GeolocationService Functions

**File:** `app/Services/GeolocationService.php`

### `detectFromIp(string $ip): ?array`

Detects geographic location from an IP address.

**How it works:**
1. Validates IP is not private or reserved range using `filter_var()`
2. If invalid, returns null immediately
3. Makes HTTP GET request to BigDataCloud IP geolocation API
4. Sets 3-second timeout to prevent blocking
5. If successful, extracts latitude, longitude, city, country from response
6. If coordinates found, returns location array
7. On any error, logs warning and returns null

**Parameters:**
- `$ip` — IP address to geolocate

**Returns:** `['lat' => float, 'lng' => float, 'city' => string, 'country' => string]` or `null`

---

### `reverseGeocode(float $lat, float $lng): ?array`

Converts coordinates to human-readable location names.

**How it works:**
1. Makes HTTP GET request to BigDataCloud reverse geocode endpoint
2. Does not require API key (client-side endpoint)
3. Sets 3-second timeout
4. If successful, extracts city (or locality), locality, country name
5. On any error, logs warning and returns null

**Parameters:**
- `$lat` — Latitude coordinate
- `$lng` — Longitude coordinate

**Returns:** `['city' => string, 'locality' => string, 'country' => string]` or `null`

---

## SettingsService Functions

**File:** `app/Services/SettingsService.php`

### `all(): array`

Returns all system settings as a key-value map.

**How it works:**
1. Retrieves all `SystemSetting` records from database
2. Uses `mapWithKeys` to transform to associative array
3. Calls `typedValue()` on each to cast to declared type
4. Returns key-value array

**Returns:** Associative array of setting key => typed value

---

### `get(string $key): mixed`

Retrieves a single setting value.

**How it works:**
1. Queries `SystemSetting` by key
2. Throws 404 if not found via `firstOrFail()`
3. Returns the typed value via `typedValue()` method

**Parameters:**
- `$key` — Setting key string

**Returns:** Typed value (string, int, bool, or decoded JSON)

---

### `set(string $key, string $rawValue, string $updatedBy): SystemSetting`

Updates a setting value with type validation.

**How it works:**
1. Finds setting by key (throws 404 if not found)
2. Validates value against declared type using match expression:
   - `integer` — checks `is_numeric()`
   - `boolean` — checks against true/false/1/0
   - `json` — validates JSON parses successfully
   - `string` — no validation needed
3. Throws `ValidationException` if type mismatch
4. Updates setting with new value, updater ID, and timestamp
5. Returns fresh setting model

**Parameters:**
- `$key` — Setting key
- `$rawValue` — New value as string
- `$updatedBy` — Admin user ID making the change

**Returns:** Updated `SystemSetting` model

**Throws:** `ValidationException`

---

## NotificationService Functions

**File:** `app/Services/NotificationService.php`

### `sendOrderConfirmation(Order $order): void`

Sends order confirmation email to the buyer.

**How it works:**
1. Determines recipient email:
   - Uses `$order->user->email` for authenticated orders
   - Falls back to `$order->guestContact->email` for guest orders
2. If no email found, returns early (no-op)
3. Creates `OrderConfirmationMail` mailable with order
4. Sends email synchronously via `Mail::to()->send()`

**Parameters:**
- `$order` — Order with user/guestContact relations loaded

---

## Model Functions

### User Model (`app/Models/User.php`)

#### `pets(): HasMany`
Returns relationship to pets created by this user (admin feature).

#### `cartItems(): HasMany`
Returns relationship to user's cart items.

#### `orders(): HasMany`
Returns relationship to user's orders.

#### `orderStatusChanges(): HasMany`
Returns relationship to order status changes made by this user (admin audit).

#### `isAdmin(): bool`
**How it works:** Compares `$this->role` to string `'admin'`, returns boolean.

#### `isLocked(): bool`
**How it works:** Checks if `locked_until` is set AND is in the future using Carbon's `isFuture()`.

---

### Pet Model (`app/Models/Pet.php`)

#### `creator(): BelongsTo`
Returns relationship to the admin user who created this pet.

#### `behaviours(): HasMany`
Returns relationship to pet's behaviour tags.

#### `images(): HasMany`
Returns relationship to pet's images, ordered by `sort_order`.

#### `thumbnail(): HasOne`
Returns single image where `is_thumbnail` is true.

#### `cartItem(): HasOne`
Returns relationship to cart item (if pet is currently in a cart).

#### `orderItems(): HasMany`
Returns relationship to order items containing this pet.

#### `isAvailable(): bool`
**How it works:** Compares `$this->status` to string `'available'`, returns boolean.

#### `syncGeoPoint(): void`
**How it works:**
1. Checks if both latitude and longitude are not null
2. Executes raw SQL UPDATE using PostGIS functions
3. Creates point from longitude/latitude with SRID 4326
4. Casts to geography type for distance calculations

---

### Order Model (`app/Models/Order.php`)

#### `user(): BelongsTo`
Returns relationship to the ordering user (null for guests).

#### `guestContact(): BelongsTo`
Returns relationship to guest contact info.

#### `deliveryAddress(): BelongsTo`
Returns relationship to the delivery address.

#### `items(): HasMany`
Returns relationship to order line items.

#### `statusHistory(): HasMany`
Returns relationship to status change history, ordered by date descending.

#### `delivery(): HasOne`
Returns relationship to delivery record.

---

### CartItem Model (`app/Models/CartItem.php`)

#### `user(): BelongsTo`
Returns relationship to authenticated user (null for guests).

#### `pet(): BelongsTo`
Returns relationship to the reserved pet.

#### `isExpired(): bool`
**How it works:** Uses Carbon's `isPast()` on `locked_until` datetime.

---

### PetImage Model (`app/Models/PetImage.php`)

#### `pet(): BelongsTo`
Returns relationship to parent pet.

#### `getUrlAttribute(): string`
**How it works:** Accessor that returns `asset('storage/' . $this->file_path)` for public URL.

---

### SystemSetting Model (`app/Models/SystemSetting.php`)

#### `typedValue(): mixed`
**How it works:** Uses PHP match expression to cast string value:
- `'integer'` → casts to int
- `'boolean'` → uses `filter_var()` with FILTER_VALIDATE_BOOLEAN
- `'json'` → decodes to array via `json_decode()`
- default → returns string as-is

#### `updatedBy(): BelongsTo`
Returns relationship to admin who last updated this setting.

---

## Controller Functions

### AuthController (`app/Http/Controllers/AuthController.php`)

#### `register(RegisterRequest $request): JsonResponse`
Validates input, calls `AuthService::register()`, returns 201 with user and token.

#### `login(LoginRequest $request): JsonResponse`
Validates input, calls `AuthService::login()`, returns 200 with user and token.

#### `logout(Request $request): JsonResponse`
Calls `AuthService::logout()` with authenticated user, returns success message.

#### `me(Request $request): JsonResponse`
Returns authenticated user from `$request->user()`.

---

### PetController (`app/Http/Controllers/PetController.php`)

#### `index(Request $request): JsonResponse`
**How it works:**
1. Extracts filter parameters from query string
2. If no coordinates provided, attempts IP detection via `GeolocationService`
3. Calls `PetService::list()` with filters
4. Returns paginated response

#### `show(string $id): JsonResponse`
Calls `PetService::findOrFail()`, returns pet with images and behaviours.

---

### CartController (`app/Http/Controllers/CartController.php`)

#### `index(Request $request): JsonResponse`
Gets user ID from auth and session ID from header, calls `CartService::getCart()`.

#### `add(AddToCartRequest $request): JsonResponse`
Validates pet_id, calls `CartService::addItem()`, returns 201 with cart item.

#### `remove(Request $request, string $id): JsonResponse`
Calls `CartService::removeItemById()`, returns success message.

#### `sync(Request $request): JsonResponse`
Validates session_id, calls `CartService::mergeGuestCart()`, returns updated cart.

---

### OrderController (`app/Http/Controllers/OrderController.php`)

#### `place(PlaceOrderRequest $request): JsonResponse`
Validates order data, calls `OrderService::create()`, returns 201 with order.

#### `history(Request $request): JsonResponse`
Queries orders for authenticated user, returns paginated results.

#### `track(Request $request, string $orderNumber): JsonResponse`
**How it works:**
1. Optionally validates email parameter
2. Queries order by order_number
3. If email provided, filters to match user or guest contact email
4. Returns order status, history, delivery, and items

---

### Admin\PetController (`app/Http/Controllers/Admin/PetController.php`)

#### `index(Request $request): JsonResponse`
Calls `PetService::adminList()` with filters, returns paginated response.

#### `show(string $id): JsonResponse`
Calls `PetService::findOrFail()`, returns pet.

#### `store(StorePetRequest $request): JsonResponse`
Validates input, extracts images, calls `PetService::create()`, returns 201.

#### `update(UpdatePetRequest $request, string $id): JsonResponse`
Finds pet, validates input, calls `PetService::update()`, returns updated pet.

#### `destroy(string $id): JsonResponse`
Finds pet, calls `PetService::delete()`, returns success message.

#### `uploadImages(Request $request, string $id): JsonResponse`
Validates images array, calls `MediaService::storeImages()`, returns 201.

#### `setThumbnail(string $petId, string $imageId): JsonResponse`
Finds pet, calls `MediaService::setThumbnail()`, returns updated image.

#### `deleteImage(string $petId, string $imageId): JsonResponse`
Finds pet, calls `MediaService::deleteImage()`, returns success message.

---

### Admin\OrderController (`app/Http/Controllers/Admin/OrderController.php`)

#### `index(Request $request): JsonResponse`
Queries all orders with relations, optionally filters by status, returns paginated.

#### `show(string $id): JsonResponse`
Finds order with all relations, returns order.

#### `updateStatus(UpdateOrderStatusRequest $request, string $id): JsonResponse`
Validates input, calls `OrderService::updateStatus()`, returns updated order.

#### `cancel(Request $request, string $id): JsonResponse`
Validates cancellation_reason, calls `updateStatus()` with 'cancelled', returns order.

---

### Admin\DeliveryController (`app/Http/Controllers/Admin/DeliveryController.php`)

#### `index(Request $request): JsonResponse`
**How it works:**
1. Validates optional month parameter
2. If month provided, returns calendar view via `DeliveryService::getCalendar()`
3. Otherwise, returns paginated list with optional status filter

#### `update(UpdateDeliveryRequest $request, string $id): JsonResponse`
Finds delivery, calls `DeliveryService::updateStatus()`, returns updated delivery.

---

### Admin\AnalyticsController (`app/Http/Controllers/Admin/AnalyticsController.php`)

#### `sales(Request $request): JsonResponse`
Validates from/to dates, calls `AnalyticsService::getSales()`, returns metrics.

#### `inventory(): JsonResponse`
Calls `AnalyticsService::getInventory()`, returns counts.

#### `customers(): JsonResponse`
Calls `AnalyticsService::getCustomers()`, returns metrics.

---

### Admin\SettingsController (`app/Http/Controllers/Admin/SettingsController.php`)

#### `index(): JsonResponse`
Calls `SettingsService::all()`, returns all settings.

#### `update(UpdateSettingRequest $request, string $key): JsonResponse`
Validates value, calls `SettingsService::set()`, returns updated setting.

#### `updateAll(Request $request): JsonResponse`
**How it works:**
1. Validates all input values are strings
2. Iterates each key-value pair
3. Calls `SettingsService::set()` for each
4. If any fails, returns validation error
5. Returns all updated settings with typed values

---

## Middleware Functions

### EnsureRole (`app/Http/Middleware/EnsureRole.php`)

#### `handle(Request $request, Closure $next, string $role): Response`

**How it works:**
1. Gets authenticated user from request
2. If no user, throws `AuthenticationException`
3. Checks if user's token has `role:{role}` ability via `tokenCan()`
4. Also verifies user's `role` column matches the required role
5. If either check fails, returns 403 JSON response
6. If valid, passes request to next middleware

---

## Job Functions

### ExpireCartLocks (`app/Jobs/ExpireCartLocks.php`)

#### `handle(CartService $cart): void`

**How it works:**
1. Receives `CartService` via dependency injection
2. Calls `releaseExpiredLocks()` to clean up stale cart items
3. Designed to run every minute via Laravel scheduler

---

## Mail Functions

### OrderConfirmationMail (`app/Mail/OrderConfirmationMail.php`)

#### `envelope(): Envelope`
Returns email envelope with subject "Order Confirmation — {order_number}".

#### `content(): Content`
Returns content configuration pointing to `emails.order_confirmation` Blade view.

#### `attachments(): array`
**How it works:**
1. Loads PDF view `emails.order_receipt_pdf` with order data
2. Generates PDF output via DomPDF
3. Creates attachment from PDF data with filename `receipt-{order_number}.pdf`
4. Returns attachment array with PDF MIME type

---

## Trait Functions

### ApiResponse (`app/Http/Traits/ApiResponse.php`)

#### `success(mixed $data = null, string $message = '', int $status = 200): JsonResponse`
Returns JSON response with `success: true`, optional data and message.

#### `created(mixed $data = null, string $message = 'Resource created successfully.'): JsonResponse`
Calls `success()` with 201 status code.

#### `paginated(LengthAwarePaginator $paginator, string $message = ''): JsonResponse`
Returns JSON with data array and meta (current_page, last_page, per_page, total).

#### `error(string $message, int $status = 400, array $errors = []): JsonResponse`
Returns JSON response with `success: false`, message, and optional errors array.

#### `notFound(string $message = 'Resource not found.'): JsonResponse`
Calls `error()` with 404 status.

#### `unauthorized(string $message = 'Unauthorized action.'): JsonResponse`
Calls `error()` with 401 status.

#### `forbidden(string $message = 'Access denied.'): JsonResponse`
Calls `error()` with 403 status.

#### `validationError(string $message = 'Validation failed.'): JsonResponse`
Calls `error()` with 422 status.
