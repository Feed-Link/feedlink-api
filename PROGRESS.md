# FeedLink — Project Progress

> **Stack:** Laravel 12 · Passport · Spatie Permission · PostGIS (Magellan 2.x)  
> **Client:** iOS (SwiftUI) — design in Claude, build in Claude Code  
> **Last updated:** 2026-04-25

---

## Backend Status

### Phase 0 — Audit & Critical Fixes ✅ COMPLETE
All routes implemented and connected. Critical bugs resolved.

**Fixed in this session:**
| # | Issue | File(s) |
|---|---|---|
| 1 | Missing `ExpireFoodRequests` command (scheduler was crashing) | `app/Console/Commands/ExpireFoodRequests.php` |
| 2+3 | `food_type` column was explicitly dropped from both `food_listings` and `food_requests` tables (migrations confirmed). Tags are the only audience mechanism. Nearby `food_type` filter now maps to `whereHas('tags', ...)` for both listings and requests. Removed `food_type` from all fillable/validation/resources. | `FoodListing`, `FoodRequest`, all request classes, both resources, `NearbyListingService`, `NearbyRequestService`, `FoodListingRepository`, `FoodRequestRepository` |
| 4 | `Point::make()` → `Point::makeGeodetic()` in FoodListing + FoodRequest (wrong SRID for PostGIS geography) | `FoodListing::location()`, `FoodRequest::location()` |
| 5 | `contact` missing from `formatListingResponse()` donor shape (was in `FoodListingResource` but not the manual formatter used by recipient/nearby endpoints) | `FoodListingService::formatListingResponse()` |
| 6 | Haversine SQL replaced with Magellan `ST::distance()` (PostGIS); dead `fetchNearby()` in repository fixed and wired; both nearby services now delegate to repositories | `FoodListingRepository`, `FoodRequestRepository`, `NearbyListingService`, `NearbyRequestService` |
| 7 | `RecipientFoodListingController::complete()` was missing DB transaction | `RecipientFoodListingController` |
| 9 | `GET /donor/requests` completely undocumented; also fixed to use stored profile location as fallback when lat/lng not supplied | `API_DOC.md`, `DonorFoodRequestController` |
| + | `rejectClaim` error message inconsistency fixed (`'Claim cannot be rejected'` → `'Claim is not pending'`) | `FoodListingService` |
| + | `FoodRequest` model missing `HasFactory` trait | `FoodRequest` |
| + | `CompleteListingService` missing UUID guard — invalid IDs hit PostgreSQL and returned 500 instead of 404 | `CompleteListingService` |
| + | `DonorFoodListingTest` fixture missing `tags` (now required); fixed test to not spread request payload into model create | `DonorFoodListingTest` |

**Test suite:** 43 tests, 77 assertions — all passing ✅

---

## Known Remaining Technical Debt

| # | Issue | Severity | Notes |
|---|---|---|---|
| T1 | Inline `$request->validate()` in 8+ controller methods (violates BaseRequest convention) | Medium | `NearbyListingController`, `NearbyRequestController`, `DonorFoodRequestController::index()`, `UserController::verifyOTP/resendOTP/updateProfile/refreshToken`, `RecipientFoodListingController::claim()` |
| T2 | `FoodSafety` module is entities-only stub | Medium | `DonorWarning`, `IllnessClaim`, `UserAcceptance` exist with no routes/controllers/services |
| T3 | `claim_user_id` in `ListingClaim::$fillable` is orphaned (not in schema, never set, `claimUser()` always null) | Medium | Should be removed or wired up |
| T4 | `ListingClaimService::getStatuses()` is broken (`array_slice` on a class name string) | Medium | Unreachable, but present |
| T5 | Empty test directories: `Claims/`, `Requests/`, `Listings/`, `RateLimiting/`, `Geospatial/` | Medium | Test coverage gaps |
| T6 | `DonorFoodListingTest` very sparse (3 tests, no negative paths) | Low | Extend coverage |

---

## Route Map (All Implemented)

| Domain | Endpoints | Status |
|---|---|---|
| Auth | register, login, logout, verify-otp, resend-otp, refresh-token, forgot-password, reset-password | ✅ |
| Donor — Listings | CRUD, relist, reopen, stats | ✅ |
| Donor — Claims | list, confirm, reject | ✅ |
| Donor — Requests | browse nearby, accept, withdraw | ✅ |
| Recipient — Listings | browse, show, claim, cancel-claim, complete | ✅ |
| Recipient — Claims | my-claims | ✅ |
| Recipient — Requests | CRUD, list acceptances, confirm/reject acceptance, complete | ✅ |
| Shared | listings/nearby, requests/nearby, user location, user profile, device-token | ✅ |
| Notifications | list (paginated), mark-read, mark-all-read | ✅ |
| Upload | photo (Cloudinary) | ✅ |
| Scheduler | expire-listings (every 5 min), expire-requests (every 5 min) | ✅ |

---

## iOS App — Product Decisions

| Decision | Choice |
|---|---|
| App model | Single app; role chosen at signup (donor OR recipient); role-based UI shown after login |
| Browse pattern | List-first home screen; search by radius (km slider); tap item → detail with embedded mini-map pin; separate full-map screen showing all nearby pins |
| Notifications | Simple list — title + body + timestamp; no deep-linking in v1 |
| Extra screens | None beyond the planned list |
| Donor requests location | `lat/lng` optional — API now falls back to stored profile location automatically |

---

## iOS App — Design & Build Plan

> Design in Claude (claude.ai) using API_DOC.md → hand off to Claude Code (Swift/SwiftUI)

### Screens to Design & Build

#### Auth Flow
- [ ] Splash / Onboarding
- [ ] Register (role selector: Donor / Recipient)
- [ ] Login
- [ ] OTP Verification
- [ ] Forgot Password → Reset Password

#### Donor Flow
- [ ] Dashboard / Home (stats + quick actions)
- [ ] My Listings (list, filter by status)
- [ ] Create Listing form
- [ ] Listing Detail (status, claims list, confirm/reject)
- [ ] Nearby Requests (map + list, accept action)
- [ ] Stats screen

#### Recipient Flow
- [ ] Browse Listings (map + list, nearby filter)
- [ ] Listing Detail (claim action, donor contact on confirmed)
- [ ] My Claims (status tabs)
- [ ] My Requests (list)
- [ ] Create Request form
- [ ] Request Detail (acceptances list, confirm/reject)

#### Shared
- [ ] Notifications center (badge count from API)
- [ ] Profile (view + edit, photo upload)
- [ ] Settings / Logout

### iOS Integration Notes (from API)
- All responses wrapped: `{ status_code, message, data }`
- Tokens: `access_token` (30 min) + `refresh_token` (rotate on use)
- Refresh on 401 via `POST /auth/refresh-token`
- Register → OTP → token (not direct token on register)
- Login returns `202 Accepted`
- Donor contact exposed in listing `donor.contact` for confirmed-claim screen
- `POST /user/device-token` on every app launch after login (FCM)
- `GET /notifications?per_page=15` — `unread_count` drives bell badge without extra request
- Photo upload: `POST /upload/photo` first, collect URL, include in listing payload
- Nearby endpoints use `lat/lng/radius` (PostGIS-backed, returns `distance_km`)

---

## Next Session Checklist

Resume with:
1. `cat PROGRESS.md` — full context
2. `php artisan test --compact` — confirm green
3. Decide: tackle T1–T6 debt OR proceed straight to iOS design phase

---

## Decisions Log

| Date | Decision | Reason |
|---|---|---|
| 2026-04-25 | Tags (`for_humans`, `for_animals`, `for_both`) replaced `food_type` for listings; nearby filter uses tag join instead of food_type column | Tags are richer and already the primary mechanism; food_type column kept for requests only |
| 2026-04-25 | Nearby uses `ST::distance()` (PostGIS geography, returns meters) not `ST_DistanceSphere` (geometry only) | Geography column requires distance-in-meters functions; Haversine was architecturally incorrect |
| 2026-04-25 | `ExpireFoodRequests` expires both `open` and `accepted` requests past `expires_at` | Accepted requests that never get fulfilled should also expire |
