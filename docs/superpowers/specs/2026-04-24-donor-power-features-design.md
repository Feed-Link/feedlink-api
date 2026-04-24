# Donor Power Features — Design Spec

**Date:** 2026-04-24  
**Project:** FeedLink API  
**Scope:** Four donor-side enhancements shipped as one phase

---

## Overview

Four features that close the most impactful gaps in the donor experience:

1. **Impact Stats** — lifetime donation totals for motivation and retention
2. **Quick Re-list** — clone a previous listing as a pre-filled template
3. **Early Listing Recovery (Reopen)** — re-open a claimed listing when a recipient no-shows
4. **Cancel When Claimed** — extend listing cancellation to work on claimed listings

---

## Feature 1 — Impact Stats

### Endpoint
`GET /donor/stats`

### Business Rules
- Scoped to the authenticated donor's own listings only
- `unique_recipients_served` = count of distinct `claimed_by` values across all completed listings
- `quantity` is free text so meal counts are not summed — listing counts are the honest metric

### Response
```json
{
  "status_code": 200,
  "message": "Stats retrieved",
  "data": {
    "listings_completed": 38,
    "listings_active": 2,
    "listings_cancelled": 5,
    "listings_expired": 3,
    "unique_recipients_served": 12
  }
}
```

### Architecture
- New `stats()` method on `DonorFoodListingController`
- `FoodListingService::getDonorStats(int $donorId): array`
- `FoodListingRepository::getDonorStats(int $donorId): array` — grouped count query + distinct `claimed_by` count
- No new files, no new table

---

## Feature 2 — Quick Re-list

### Endpoint
`POST /donor/listings/{id}/relist`

### Business Rules
- Does **not** create a new listing — returns a sanitized template only
- iOS uses the response to pre-fill the create form; donor sets fresh `expires_at` / `pickup_before` and submits via `POST /donor/listings`
- Valid for any listing status (donor can relist expired, completed, cancelled listings)
- Donor must own the listing

### Stripped Fields
`id`, `status`, `expires_at`, `pickup_before`, `claimed_by`, `confirmed_at`, `created_at`, `distance_km`, `donor`

### Response
```json
{
  "status_code": 200,
  "message": "Listing template retrieved",
  "data": {
    "title": "Leftover Dal Bhat",
    "description": "Freshly cooked, enough for 15 people",
    "quantity": "15 portions",
    "tags": ["for_humans", "cooked"],
    "photos": ["https://res.cloudinary.com/.../abc.jpg"],
    "pickup_instructions": "Call before coming",
    "address": "Thamel, Kathmandu",
    "latitude": 27.7172,
    "longitude": 85.3240
  }
}
```

### Error Cases
- `403` Not the owner
- `404` Listing not found

### Architecture
- New `relist()` method on `DonorFoodListingController`
- `FoodListingService::getRelistTemplate(string $id, int $donorId): array` — fetch + strip ephemeral fields
- No new repository method needed (uses existing `fetch()`)

---

## Feature 3 — Early Listing Recovery (Reopen)

### Endpoint
`POST /donor/listings/{id}/reopen`

### Business Rules
- Listing must be in `claimed` status
- Donor must own the listing
- Restores the full claim pool so donor can re-pick from all original candidates

### State Transitions
| Entity | Field | Before | After |
|---|---|---|---|
| `food_listings` | `status` | `claimed` | `active` |
| `food_listings` | `claimed_by` | recipient UUID | `null` |
| `food_listings` | `confirmed_at` | timestamp | `null` |
| `listing_claims` (all on this listing) | `status` | `rejected` / `confirmed` | `pending` |

### Notification
- Type: `listing_reopened` (new `NotificationTypeEnum` case)
- Recipient: the previously confirmed recipient
- Body: `"[Donor name] has reopened '[Listing title]' — your claim is back in the queue."`

### Response
Full listing shape (same as `GET /donor/listings` item), `status: "active"`.

### Error Cases
- `400` Listing is not in claimed status
- `403` Not the owner
- `404` Listing not found

### Architecture
- New `reopen()` method on `DonorFoodListingController`
- `FoodListingService::reopenListing(string $id, int $donorId): FoodListing`
  - Fetches listing, guards status + ownership
  - Updates listing fields
  - Bulk-updates all claims on this listing to `pending`
  - Dispatches `SendNotificationJob` to previously confirmed recipient
- `FoodListingRepository` uses existing `update()` for listing; `ListingClaimRepository` needs a `resetClaimsForListing(string $listingId): void` method
- New `NotificationTypeEnum::LISTING_REOPENED`

---

## Feature 4 — Cancel When Claimed

### Endpoint
`DELETE /donor/listings/{id}` *(existing, extended)*

### Business Rules
- Previously only worked on `active` listings — now also accepts `claimed`
- `active` path: existing behaviour unchanged
- `claimed` path: additional claim rejection + notification

### State Transitions (claimed path only)
| Entity | Field | Before | After |
|---|---|---|---|
| `food_listings` | `status` | `claimed` | `cancelled` |
| `listing_claims` (all on this listing) | `status` | any | `rejected` |

### Notification (claimed path only)
- Type: `listing_cancelled` (new `NotificationTypeEnum` case)
- Recipient: the previously confirmed recipient
- Body: `"'[Listing title]' has been cancelled by the donor. Your pickup is no longer available."`

### Error Cases
- `400` Can only cancel active or claimed listings *(error message updated)*
- `403` Not the owner
- `404` Listing not found

### Architecture
- Extend `FoodListingService::cancelListing(string $id, int $donorId): void`
  - Add branch: if `claimed`, bulk-reject all claims + dispatch `SendNotificationJob`
- `ListingClaimRepository` uses a new `rejectAllClaimsForListing(string $listingId): void` method (distinct from Feature 3's `resetClaimsForListing` which sets to `pending`)
- New `NotificationTypeEnum::LISTING_CANCELLED`
- No new controller method, no new route

---

## New Notification Types Summary

| Type | Sent To | Trigger |
|---|---|---|
| `listing_reopened` | confirmed recipient | donor calls `POST /donor/listings/{id}/reopen` |
| `listing_cancelled` | confirmed recipient | donor calls `DELETE /donor/listings/{id}` on a `claimed` listing |

---

## New Routes Summary

| Method | Path | Auth | Role |
|---|---|---|---|
| GET | `/donor/stats` | Yes | donor |
| POST | `/donor/listings/{id}/relist` | Yes | donor |
| POST | `/donor/listings/{id}/reopen` | Yes | donor |

`DELETE /donor/listings/{id}` extended — no new route.

---

## Files Changed / Created

| File | Action |
|---|---|
| `DonorFoodListingController` | Add `stats()`, `relist()`, `reopen()` methods |
| `FoodListingService` | Add `getDonorStats()`, `getRelistTemplate()`, `reopenListing()`; extend `cancelListing()` |
| `FoodListingRepository` | Add `getDonorStats()` |
| `ListingClaimRepository` | Add `resetClaimsForListing()`, `rejectAllClaimsForListing()` |
| `NotificationTypeEnum` | Add `LISTING_REOPENED`, `LISTING_CANCELLED` cases |
| `routes/api.php` | Register 3 new donor routes |
| `API_DOC.md` | Document all 4 features |

No new migrations required.
