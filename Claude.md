# FeedLink – Laravel API
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
| `POST /api/register` | Register user (accepts `role`: donor/recipient) |
| `POST /api/login` | Login with Passport |
| `POST /api/verify` | Verify OTP |
| `POST /api/resend-otp` | Resend OTP |
| `POST /api/forgot-password` | Forgot password |
| `POST /api/reset-password` | Reset password |

**Packages already installed:**
- `laravel/passport` – API authentication
- `spatie/laravel-permission` – Role & permission management
- `clickbar/magellan-point` – Geospatial queries (PostGIS)

**All donor, recipient, and shared routes are implemented.** See `API_DOC.md` for the full current route list.

---

## 3. Role Architecture (Spatie)

During **registration**, the user selects one of two roles. No role switching is allowed after signup.

| Role | Slug | Description |
|---|---|---|
| Donor | `donor` | Lists surplus food, confirms/rejects claims |
| Recipient | `recipient` | Claims food, creates food requests |

> **Admin** role exists separately and is seeded manually.

Registration accepts a `role` field (`donor` or `recipient`) and assigns it via `$user->assignRole($request->role)`.

---

## 4. Database Schema Overview

### 4.1 `users`
Columns: `name`, `email`, `password`, `contact`, `latitude` (decimal 10,8), `longitude` (decimal 11,8), `location` (geography/point), `is_verified` (boolean), `profile_photo` (string nullable).

### 4.2 `food_listings`
| Column | Type | Notes |
|---|---|---|
| `donor_id` | FK → users | |
| `title` | string | |
| `description` | text | nullable |
| `quantity` | string | |
| `food_type` | enum | `human`, `animal`, `both` |
| `photos` | json | array of image paths |
| `expires_at` | timestamp | triggers auto-expire |
| `pickup_before` | timestamp | latest pickup window |
| `pickup_instructions` | text | nullable |
| `status` | enum | `active`, `claimed`, `completed`, `expired`, `cancelled` |
| `latitude` / `longitude` | decimal | raw coords |
| `location` | geography(Point) | PostGIS via Magellan |
| `address` | string | |
| `claimed_by` | FK → users | nullable |
| `confirmed_at` | timestamp | nullable |

### 4.3 `food_requests`
| Column | Type | Notes |
|---|---|---|
| `recipient_id` | FK → users | |
| `title` | string | |
| `description` | text | nullable |
| `quantity_needed` | string | |
| `food_type` | enum | `human`, `animal`, `both` |
| `needed_by` | timestamp | deadline |
| `status` | enum | `open`, `accepted`, `fulfilled`, `expired`, `cancelled` |
| `latitude` / `longitude` | decimal | |
| `location` | geography(Point) | |
| `address` | string | |
| `accepted_by` | FK → users | nullable |
| `accepted_at` | timestamp | nullable |
| `expires_at` | timestamp | |

### 4.4 `listing_claims`
| Column | Type | Notes |
|---|---|---|
| `food_listing_id` | FK | |
| `recipient_id` | FK → users | |
| `status` | enum | `pending`, `confirmed`, `rejected` |
| `note` | text | nullable |

### 4.5 `request_acceptances`
| Column | Type | Notes |
|---|---|---|
| `food_request_id` | FK | |
| `donor_id` | FK → users | |
| `status` | enum | `pending`, `confirmed`, `rejected` |
| `note` | text | nullable |

---

## 5. Geospatial Implementation (Magellan / PostGIS)

Use `geography` (SRID 4326) for distance accuracy. Always store both raw `latitude`/`longitude` columns **and** the PostGIS `location` point.

**Model setup:**
```php
use Clickbar\Magellan\Eloquent\HasPostgisColumns;

protected array $postgisColumns = [
    'location' => ['type' => 'geography', 'srid' => 4326],
];
```

**Storing a point:**
```php
$data['location'] = Point::makeGeodetic($request->latitude, $request->longitude);
```

**Distance query:**
```php
$point = Point::makeGeodetic($lat, $lng);
FoodListing::query()
    ->withinDistance('location', $point, $radiusKm * 1000)
    ->orderByDistance('location', $point)
    ->get();
```

**Every listing/request response must include `distance_km` when a location filter is applied.**

---

## 6. Scheduled Commands (Auto-Expiry)

Expire listings and requests run every 5 minutes via `routes/console.php` (Laravel 11 style):

```php
Schedule::command('feedlink:expire-listings')->everyFiveMinutes();
Schedule::command('feedlink:expire-requests')->everyFiveMinutes();
```

---

## 7. Coding Rules & Conventions

> **MANDATORY:** Before writing any PHP code (controllers, services, repositories, models, requests), you **MUST** load and follow the `modular-monolithic-pattern` skill. No exceptions.

### 7.1 Architecture

All code follows **Controller → Service → Repository → Model**. Never skip a layer.

```
Controller   thin HTTP layer — validate, call service, return response
Service      all business logic
Repository   all data access, extends BaseRepository
Model        Eloquent entity, extends BaseModel
```

### 7.2 Core Base Classes

| Class | Path | Purpose |
|---|---|---|
| `BaseModel` | `app/Modules/Core/Entities/BaseModel.php` | Adds `HasUuids`, defines `SEARCHABLE` constant |
| `BaseRepository` | `app/Modules/Core/Repositories/BaseRepository.php` | Provides `store()`, `fetch()`, `fetchBy()`, `fetchAll()`, `update()`, `delete()`, pessimistic locking |
| `BaseRequest` | `app/Modules/Core/Requests/BaseRequest.php` | Routes validation to `store()` (POST) or `update()` (PUT/PATCH) based on HTTP method |

**BaseRepository** uses the `Filterables` trait — `fetchAll($params)` supports `search`, `filter`, `sort_by`, `sort_order`, `per_page`, `no_paginate`, `infinite`, and comparison operators (`__gt_`, `__gte_`, `__lt_`, `__lte_`).

**BaseModel** requires a `SEARCHABLE` constant on every model:
```php
public const SEARCHABLE = ['title', 'description', 'address'];
```

### 7.3 Repository Convention

```php
class FoodListingRepository extends BaseRepository
{
    public function __construct(protected FoodListing $foodListing)
    {
        $this->model = $foodListing;  // must assign before parent::__construct()
        parent::__construct();
    }
}
```

### 7.4 Request Classes

All Form Request classes **must** extend `App\Modules\Core\Requests\BaseRequest`. Never extend `FormRequest` directly. Override `store()` and `update()` — **never** override `rules()`.

```php
class StoreFoodListingRequest extends BaseRequest
{
    public function authorize(): bool { return true; }

    public function store(): array
    {
        return ['title' => 'required|string|max:255'];
    }

    public function update(): array
    {
        return ['title' => 'sometimes|string|max:255'];
    }
}
```

Always use `$request->validated()`, never `$request->all()`.

### 7.5 Controllers (HasApiResponse)

All controllers use `HasApiResponse` trait (`app/Modules/Core/Traits/HasApiResponse.php`). Every method must wrap logic in try/catch:

```php
public function store(StoreFoodListingRequest $request): JsonResponse
{
    try {
        $listing = $this->foodListingService->createListing($request->validated());
        return $this->success('Food listing created', Response::HTTP_CREATED, new FoodListingResource($listing));
    } catch (Exception $exception) {
        return $this->handleException($exception);
    }
}
```

- Use `$this->success($message, $statusCode, $data)` — never `response()->json()` directly.
- Use `$this->handleException($exception)` in every catch block.
- Always throw exceptions with an HTTP code: `throw new Exception('Not found', 404)`.
- Always wrap model output in an API Resource.

### 7.6 Enums

All categorical fields (`status`, `food_type`, `role`) must use PHP enums from `app/Modules/Core/Enums/`. Use TitleCase for enum keys.

```php
// Existing enums:
RolesEnum, FoodTypeEnum, ListingStatusEnum, RequestStatusEnum, FoodTagEnum, FoodTagCategoryEnum
```

### 7.7 File Structure per Module

```
app/Modules/{ModuleName}/
├── Entities/       {ModelName}.php           extends BaseModel
├── Repositories/   {ModelName}Repository.php extends BaseRepository
├── Services/       {ModelName}Service.php     business logic
├── Controllers/    {Role}{ModelName}Controller.php
├── Requests/       Store{ModelName}Request.php / Update{ModelName}Request.php
└── Resources/      {ModelName}Resource.php
```

### 7.8 API Documentation Sync (Mandatory)

Whenever any route is **added, removed, renamed, or updated**, you **must** update `API_DOC.md` in the same task.

`API_DOC.md` entries must include: HTTP method + path, auth/role requirements, request payload with validation rules, query parameters, success response shape + status code, and known error cases.

---

## 8. Production Database — Critical Rules

> **WARNING:** The `.env` DB connection points to a live **production** DigitalOcean PostgreSQL database. Every command that touches the DB runs against production.

- **NEVER run `php artisan migrate:fresh`** — it drops all tables and destroys production data.
- **NEVER run `php artisan migrate`** unless explicitly deploying a new migration. Migrations are handled automatically by the CI/CD pipeline on push to `master`.
- **NEVER use `RefreshDatabase`** in tests — it calls `migrate:fresh` internally. Use `DatabaseTransactions` instead (already configured in `tests/TestCase.php`).
- Tests use `DatabaseTransactions` which wraps each test in a rolled-back transaction — safe against production.
- If you need to verify the schema, use `php artisan migrate:status` (read-only).

---

## 9. Notes & Gotchas

1. **Magellan geography vs geometry** – Use `geography` (SRID 4326). `geometry` uses flat-earth math and gives wrong distances.
2. **Multiple claims** – A listing can receive multiple claim requests, but only one can be confirmed. Confirming one must reject all others automatically.
3. **Donor accepting a request** – Sets `status = accepted` and `accepted_by`. Only `fulfilled` after recipient marks complete.
4. **Photo storage** – Use `Storage::disk('public')` locally; switch to S3 in production. Return full URLs from the API resource.
5. **`$this->model` before `parent::__construct()`** – Repository constructor reads `$this->model` to derive `tableName` and `modelName`. Always assign it first.

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
