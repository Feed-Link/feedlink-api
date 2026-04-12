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
  "phone": "9841000000",
  "role": "donor"   // or "recipient"
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

## 9. Prompt Template for Claude

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

## 10. Example Response Shapes

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

## 11. Notes & Gotchas

1. **Magellan geography vs geometry** – Use `geography` (SRID 4326) for distance accuracy in km. `geometry` uses flat-earth math.
3. **Multiple claims** – A listing can receive multiple claim requests, but only **one** can be confirmed. Rejecting others on confirmation is required.
4. **Donor accepting a request** – When a donor accepts a food request, it should set `status = accepted` and store `accepted_by`. The request is only `fulfilled` after the recipient marks it complete.
5. **Photo storage** – Use Laravel's `Storage::disk('public')` locally. In production, switch to S3. Return full URLs in the API resource.
6. **Passport scopes** – Consider adding scopes `donor` and `recipient` for finer token-level control if needed later.