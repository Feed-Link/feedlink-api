# FeedLink – Laravel 11 API Development Plan
> **Project:** FeedLink – Food Surplus Redistribution Platform (Nepal)
> **Stack:** Laravel 11 · Laravel Passport · Spatie Permission · Clickbar Magellan Point (PostGIS)
> **Client:** iOS (SwiftUI) 


---

## 1. Project Context

FeedLink is a mobile platform that connects **food-surplus donors** (restaurants, shops, households) with **recipients** (NGOs, shelters, homeless volunteers, street-dog feeders). The backend is a RESTful Laravel 11 API consumed by an iOS app.

---

## 2. What Already Exists

The following auth routes are **already implemented** and must not be re-created:

| Route | Description |
|---|---|
| `POST /api/register` | Register user |
| `POST /api/login` | Login with Passport |
| `POST /api/verify` | Verify OTP |
| `POST /api/resend-otp` | Resend OTP |
| `POST /api/forgot-password` | Forgot password |
| `POST /api/reset-password` | Reset password |

**Packages already installed:**
- `laravel/passport` – API authentication
- `spatie/laravel-permission` – Role & permission management
- `clickbar/magellan-point` (or equivalent PostGIS package) – Geospatial queries

---

## 3. Role Architecture (Spatie)

During **registration**, the user selects one of two roles. No role switching is allowed after signup.

| Role | Slug | Description |
|---|---|---|
| Donor | `donor` | Lists surplus food, confirms/rejects claims |
| Recipient | `recipient` | Claims food, creates food requests |

> **Admin** role exists separately and is seeded manually (not part of this phase).

### 3.1 Registration Flow Change

Update the existing `POST /api/register` to accept a `role` field:

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "secret",
  "contact": "9841000000",
  "role": "donor",   // or "recipient"
  "location": {
    "lat": 27.7172,
    "long": 85.3240
  }
}
```

After successful registration, call `$user->assignRole($request->role)` using Spatie.

---

## 4. Database Schema Overview

### 4.1 `users` (existing – extend as needed)
Add columns:
- `latitude` (decimal 10,8) – nullable, updated when user sets location
- `longitude` (decimal 11,8) – nullable
- `location` (geography/point) – PostGIS point via Magellan
- `is_verified` (boolean) – for NGO/organisation badge
- `profile_photo` (string) – nullable

### 4.2 `food_listings`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `donor_id` | FK → users | |
| `title` | string | e.g., "Leftover Dal Bhat" |
| `description` | text | nullable |
| `quantity` | string | e.g., "5 kg", "20 portions" |
| `food_type` | enum | `human`, `animal`, `both` |
| `photos` | json | array of image paths |
| `expires_at` | timestamp | food expiry – triggers auto-expire |
| `pickup_before` | timestamp | latest pickup window |
| `pickup_instructions` | text | nullable |
| `status` | enum | `active`, `claimed`, `completed`, `expired`, `cancelled` |
| `latitude` | decimal(10,8) | |
| `longitude` | decimal(11,8) | |
| `location` | geography(Point) | PostGIS via Magellan |
| `address` | string | human-readable address |
| `claimed_by` | FK → users | nullable, recipient who claimed |
| `confirmed_at` | timestamp | nullable, when donor confirmed pickup |
| `created_at` / `updated_at` | timestamps | |

### 4.3 `food_requests`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `recipient_id` | FK → users | |
| `title` | string | e.g., "Need cooked rice for 10 people" |
| `description` | text | nullable |
| `quantity_needed` | string | |
| `food_type` | enum | `human`, `animal`, `both` |
| `needed_by` | timestamp | deadline |
| `status` | enum | `open`, `accepted`, `fulfilled`, `expired`, `cancelled` |
| `latitude` | decimal(10,8) | |
| `longitude` | decimal(11,8) | |
| `location` | geography(Point) | PostGIS via Magellan |
| `address` | string | |
| `accepted_by` | FK → users | nullable, donor who accepted |
| `accepted_at` | timestamp | nullable |
| `expires_at` | timestamp | auto-expire if not accepted |
| `created_at` / `updated_at` | timestamps | |

### 4.4 `listing_claims` (pivot / claim log)
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `food_listing_id` | FK | |
| `recipient_id` | FK → users | |
| `status` | enum | `pending`, `confirmed`, `rejected` |
| `note` | text | nullable, claim message |
| `created_at` / `updated_at` | timestamps | |

### 4.5 `request_acceptances` (pivot / acceptance log)
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `food_request_id` | FK | |
| `donor_id` | FK → users | |
| `status` | enum | `pending`, `confirmed`, `rejected` |
| `note` | text | nullable |
| `created_at` / `updated_at` | timestamps | |

---

## 5. API Routes to Build

All routes below are under the `api` prefix and protected by `auth:api` (Passport) unless stated. Role middleware uses Spatie's `role` middleware.

### 5.1 Donor Routes

```
Middleware: auth:api + role:donor
```

#### Food Listing CRUD

| Method | URI | Description |
|---|---|---|
| `GET` | `/api/donor/listings` | All listings by this donor (with filters: status, page) |
| `POST` | `/api/donor/listings` | Create a new food listing |
| `GET` | `/api/donor/listings/{id}` | Get single listing detail |
| `PUT` | `/api/donor/listings/{id}` | Update listing (only if `active`) |
| `DELETE` | `/api/donor/listings/{id}` | Cancel/soft-delete listing |

**POST/PUT payload:**
```json
{
  "title": "Leftover Dal Bhat",
  "description": "Freshly cooked, enough for 15 people",
  "quantity": "15 portions",
  "food_type": "human",
  "photos": ["base64_or_multipart"],
  "expires_at": "2026-04-01T20:00:00Z",
  "pickup_before": "2026-04-01T22:00:00Z",
  "pickup_instructions": "Call before coming",
  "latitude": 27.7172,
  "longitude": 85.3240,
  "address": "Thamel, Kathmandu"
}
```

#### Listing Status Views (filtered)

| Method | URI | Description |
|---|---|---|
| `GET` | `/api/donor/listings?status=active` | Active listings |
| `GET` | `/api/donor/listings?status=claimed` | Claimed (awaiting donor confirm) |
| `GET` | `/api/donor/listings?status=completed` | Completed pickups |
| `GET` | `/api/donor/listings?status=expired` | Expired listings |
| `GET` | `/api/donor/listings?status=cancelled` | Cancelled listings |

> Use a single `status` query param on the index route – no separate routes needed.

#### Claim Management (Donor side)

| Method | URI | Description |
|---|---|---|
| `GET` | `/api/donor/listings/{id}/claims` | View all claims on a listing |
| `POST` | `/api/donor/listings/{id}/claims/{claim_id}/confirm` | Confirm a recipient's claim |
| `POST` | `/api/donor/listings/{id}/claims/{claim_id}/reject` | Reject a recipient's claim |

#### Food Request Acceptance (Donor side)

| Method | URI | Description |
|---|---|---|
| `GET` | `/api/donor/requests` | Browse open food requests (location filter) |
| `POST` | `/api/donor/requests/{id}/accept` | Accept a recipient's food request |
| `POST` | `/api/donor/requests/{id}/cancel-acceptance` | Cancel accepted request (before confirmed) |

---

### 5.2 Recipient Routes

```
Middleware: auth:api + role:recipient
```

#### Browse & Claim Food Listings

| Method | URI | Description |
|---|---|---|
| `GET` | `/api/recipient/listings` | Browse available listings near recipient (location filter) |
| `GET` | `/api/recipient/listings/{id}` | View listing detail |
| `POST` | `/api/recipient/listings/{id}/claim` | Claim a food listing |
| `DELETE` | `/api/recipient/listings/{id}/claim` | Cancel own claim |

**Claim payload:**
```json
{
  "note": "We are picking up on behalf of Asha Shelter"
}
```

#### My Claims

| Method | URI | Description |
|---|---|---|
| `GET` | `/api/recipient/claims` | All claims made by this recipient |
| `GET` | `/api/recipient/claims?status=pending` | Pending claims |
| `GET` | `/api/recipient/claims?status=confirmed` | Confirmed pickups |
| `GET` | `/api/recipient/claims?status=rejected` | Rejected claims |

#### Food Request CRUD (Recipient side)

| Method | URI | Description |
|---|---|---|
| `GET` | `/api/recipient/requests` | All requests created by this recipient |
| `POST` | `/api/recipient/requests` | Create a new food request |
| `GET` | `/api/recipient/requests/{id}` | View request detail |
| `PUT` | `/api/recipient/requests/{id}` | Update request (only if `open`) |
| `DELETE` | `/api/recipient/requests/{id}` | Cancel request |

**POST/PUT payload:**
```json
{
  "title": "Need cooked food for street dogs",
  "description": "Feeding ~20 dogs near Pashupatinath",
  "quantity_needed": "5 kg",
  "food_type": "animal",
  "needed_by": "2026-04-02T18:00:00Z",
  "latitude": 27.7105,
  "longitude": 85.3487,
  "address": "Pashupatinath Area, Kathmandu"
}
```

---

### 5.3 Shared / Public Routes (auth required but no role restriction)

| Method | URI | Description |
|---|---|---|
| `GET` | `/api/listings/nearby` | Browse nearby listings (lat/lng + radius filter) |
| `GET` | `/api/requests/nearby` | Browse nearby food requests (lat/lng + radius filter) |
| `PUT` | `/api/user/location` | Update own current location |
| `GET` | `/api/user/profile` | Get own profile |
| `PUT` | `/api/user/profile` | Update own profile |

**Nearby query params:**
```
GET /api/listings/nearby?lat=27.7172&lng=85.3240&radius=5&food_type=human&page=1
```
- `lat` – required
- `lng` – required
- `radius` – km, default `5`, max `50`
- `food_type` – optional: `human`, `animal`, `both`
- `status` – default `active`

---

## 6. Geospatial Implementation (Magellan / PostGIS)

Use the **Clickbar Magellan** package for point storage and distance queries.

### 6.1 Model Setup

```php
// FoodListing model
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Eloquent\HasPostgisColumns;

class FoodListing extends Model
{
    use HasPostgisColumns;

    protected array $postgisColumns = [
        'location' => [
            'type' => 'geography',
            'srid' => 4326,
        ],
    ];
}
```

### 6.2 Storing a Point

```php
$listing->location = Point::makeGeodetic($request->latitude, $request->longitude);
$listing->save();
```

### 6.3 Distance Query (Within X km)

```php
use Clickbar\Magellan\Expressions\Postgis;

$listings = FoodListing::query()
    ->whereStatus('active')
    ->orderByDistance('location', $userPoint)
    ->withinDistance('location', $userPoint, $radiusInMeters)
    ->get();
```

> Always store radius in **metres** in the query: `$km * 1000`.

### 6.4 Response Format (map-ready)

Every listing/request response should include:

```json
{
  "id": 1,
  "title": "Dal Bhat for 10",
  "latitude": 27.7172,
  "longitude": 85.3240,
  "distance_km": 1.2,
  "address": "Thamel, Kathmandu",
  "status": "active",
  ...
}
```

---

## 7. Scheduled Commands (Auto-Expiry)

### 7.1 Expire Food Listings

```php
// app/Console/Commands/ExpireFoodListings.php
FoodListing::query()
    ->whereIn('status', ['active', 'claimed'])
    ->where('expires_at', '<=', now())
    ->update(['status' => 'expired']);
```

### 7.2 Expire Food Requests

```php
FoodRequest::query()
    ->whereIn('status', ['open', 'accepted'])
    ->where('needed_by', '<=', now())
    ->update(['status' => 'expired']);
```

### 7.3 Schedule Registration

```php
// routes/console.php or Kernel.php
Schedule::command('feedlink:expire-listings')->everyFiveMinutes();
Schedule::command('feedlink:expire-requests')->everyFiveMinutes();
```

---

## 8. Development Phases

### Phase 1 – Role & Registration Update *(1–2 days)*
- [ ] Add `role` field to register endpoint
- [ ] Seed Spatie roles: `donor`, `recipient`, `admin`
- [ ] Assign role on registration
- [ ] Add role middleware to route groups
- [ ] Extend `users` table with `latitude`, `longitude`, `location`, `is_verified`

### Phase 2 – Food Listing Module *(3–4 days)*
- [ ] Create `food_listings` migration
- [ ] Create `FoodListing` model with Magellan PostGIS trait
- [ ] Implement `DonorListingController` (CRUD + status filters)
- [ ] Implement photo upload (Laravel Storage / S3)
- [ ] Write `FoodListingResource` for API response
- [ ] Implement `ExpireFoodListings` scheduled command

### Phase 3 – Claim Module *(2–3 days)*
- [ ] Create `listing_claims` migration
- [ ] Implement `RecipientClaimController` (claim / cancel)
- [ ] Implement `DonorClaimController` (confirm / reject)
- [ ] Update listing status on claim confirmation
- [ ] Prevent multiple active claims per listing

### Phase 4 – Food Request Module *(3–4 days)*
- [ ] Create `food_requests` migration
- [ ] Create `FoodRequest` model with Magellan
- [ ] Implement `RecipientRequestController` (CRUD)
- [ ] Implement `DonorRequestController` (browse + accept)
- [ ] Create `request_acceptances` migration + controller
- [ ] Implement `ExpireFoodRequests` scheduled command

### Phase 5 – Geospatial / Nearby Endpoints *(2 days)*
- [ ] Implement `GET /api/listings/nearby` with lat/lng/radius
- [ ] Implement `GET /api/requests/nearby` with lat/lng/radius
- [ ] Implement `PUT /api/user/location`
- [ ] Ensure `distance_km` appears in all map-bound responses

### Phase 6 – Testing & Polish *(2–3 days)*
- [ ] Write Feature tests for each controller
- [ ] Validate food safety tags (human/animal/both)
- [ ] Rate limiting on claim/request creation
- [ ] API documentation (Postman collection or Scribe)

---

## 9. Coding Conventions

> **MANDATORY FOR ALL CODING TASKS:** Before writing any PHP code in this project (new modules, controllers, services, repositories, models, requests), you **MUST** load and follow the `modular-monolithic-pattern` skill. No exceptions.
>
> This skill is registered in `.ai/guidelines.md` as part of the Laravel Boost AI assistance framework.



### 9.1 Request Classes

All Form Request classes **must** extend `App\Modules\Core\Requests\BaseRequest`, never `Illuminate\Foundation\Http\FormRequest` directly.

`BaseRequest` automatically routes validation to `store()` (POST) or `update()` (PUT/PATCH) based on the HTTP method. Override these methods instead of `rules()`:

```php
use App\Modules\Core\Requests\BaseRequest;

class CreateListingRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function store(): array
    {
        return [
            'title' => 'required|string|max:255',
            // ...
        ];
    }

    public function update(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            // ...
        ];
    }
}
```

> Do not override `rules()` — override `store()` and `update()` instead.

### 9.2 API Documentation Sync Rule (Mandatory)

Whenever any API route is **added, removed, renamed, or updated** in `routes/api.php` (or route files loaded by the API), you **must** update `API_DOC.md` in the same task/PR.

Documentation updates must include:
- Exact HTTP method and path
- Auth requirements and role restrictions
- Request payload fields with validation rules
- Query parameters (if any)
- Success response shape and status code
- Known error response cases/messages relevant to the endpoint

`API_DOC.md` should always reflect the current implementation in controllers/services/requests, not planned routes.

---

## 10. Prompt Template for Claude

Use the block below as your prompt when asking Claude to implement a specific phase or feature:

```
You are a senior Laravel 11 developer building the backend for FeedLink,
a food redistribution platform in Nepal.

## Tech Stack
- Laravel 11
- Laravel Passport (auth:api)
- Spatie Laravel Permission (roles: donor, recipient, admin)
- Clickbar Magellan (PostGIS geospatial)
- MySQL / PostgreSQL
- iOS client (SwiftUI)

## Already Built
- Auth routes: register, login, verify OTP, resend OTP, forgot password, reset password
- Registration now accepts a `role` field (donor / recipient) and assigns it via Spatie

## What I Need You To Build
[DESCRIBE THE SPECIFIC PHASE OR FEATURE HERE]

## Rules
- All responses must be JSON (`return response()->json(...)`)
- Use Laravel Form Requests for validation
- Use API Resources for response formatting
- Use Spatie role middleware on route groups
- Store location as PostGIS point via Magellan; also store raw lat/lng columns
- Return `distance_km` in all listing/request responses when location filter is applied
- Scheduled commands go in `routes/console.php` (Laravel 11 style)
- Follow RESTful conventions
- Include migration, model, controller, request, resource, and route registration

## Output Format
Provide code file by file. Start with migration, then model, then controller,
then form request, then API resource, then route registration.
```

---

## 11. Example Response Shapes

### Food Listing (active)
```json
{
  "id": 12,
  "title": "Leftover Dal Bhat",
  "description": "Freshly cooked, enough for 15 people",
  "quantity": "15 portions",
  "food_type": "human",
  "photos": [
    "https://feedlink.app/storage/listings/photo1.jpg"
  ],
  "expires_at": "2026-04-01T20:00:00Z",
  "pickup_before": "2026-04-01T22:00:00Z",
  "pickup_instructions": "Call before coming",
  "status": "active",
  "latitude": 27.7172,
  "longitude": 85.3240,
  "address": "Thamel, Kathmandu",
  "distance_km": 1.2,
  "donor": {
    "id": 3,
    "name": "Momo House Restaurant",
    "is_verified": true
  },
  "claimed_by": null,
  "created_at": "2026-04-01T15:00:00Z"
}
```

### Claim (pending)
```json
{
  "id": 5,
  "food_listing_id": 12,
  "recipient": {
    "id": 7,
    "name": "Asha Shelter",
    "is_verified": true
  },
  "status": "pending",
  "note": "Picking up on behalf of 20 residents",
  "created_at": "2026-04-01T16:00:00Z"
}
```

### Food Request
```json
{
  "id": 4,
  "title": "Need cooked food for street dogs",
  "description": "Feeding ~20 dogs near Pashupatinath",
  "quantity_needed": "5 kg",
  "food_type": "animal",
  "needed_by": "2026-04-02T18:00:00Z",
  "status": "open",
  "latitude": 27.7105,
  "longitude": 85.3487,
  "address": "Pashupatinath Area, Kathmandu",
  "distance_km": 3.1,
  "recipient": {
    "id": 7,
    "name": "Street Animal Care Nepal",
    "is_verified": false
  },
  "accepted_by": null,
  "created_at": "2026-04-01T14:00:00Z"
}
```

---

## 12. Notes & Gotchas

1. **Magellan geography vs geometry** – Use `geography` (SRID 4326) for distance accuracy in km. `geometry` uses flat-earth math.
3. **Multiple claims** – A listing can receive multiple claim requests, but only **one** can be confirmed. Rejecting others on confirmation is required.
4. **Donor accepting a request** – When a donor accepts a food request, it should set `status = accepted` and store `accepted_by`. The request is only `fulfilled` after the recipient marks it complete.
5. **Photo storage** – Use Laravel's `Storage::disk('public')` locally. In production, switch to S3. Return full URLs in the API resource.
6. **Passport scopes** – Consider adding scopes `donor` and `recipient` for finer token-level control if needed later.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v12
- laravel/octane (OCTANE) - v2
- laravel/passport (PASSPORT) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- laravel/telescope (TELESCOPE) - v5
- phpunit/phpunit (PHPUNIT) - v11

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app\Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app\Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== octane/core rules ===

# Octane

- Octane boots the application once and reuses it across requests, so singletons persist between requests.
- The Laravel container's `scoped` method may be used as a safe alternative to `singleton`.
- Never inject the container, request, or config repository into a singleton's constructor; use a resolver closure or `bind()` instead:

```php
// Bad
$this->app->singleton(Service::class, fn (Application $app) => new Service($app['request']));

// Good
$this->app->singleton(Service::class, fn () => new Service(fn () => request()));
```

- Never append to static properties, as they accumulate in memory across requests.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
