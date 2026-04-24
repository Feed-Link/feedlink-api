# Pickup Completion Flow — Design Spec

**Date:** 2026-04-24
**Scope:** Claim status notifications (confirmed/rejected) to recipient + pickup completion endpoint + donor pickup_completed notification
**Approach:** Generic `SendNotificationJob` replaces `SendClaimNotificationJob`; recipient drives completion

---

## 1. Problem

After a donor confirms a claim:
- Recipient receives zero notification — they don't know their claim was accepted or rejected
- The listing sits in `claimed` status indefinitely until the scheduler auto-expires it past `pickup_before`
- There is no endpoint for marking a listing as `completed`

---

## 2. Decisions

| Decision | Choice | Reason |
|---|---|---|
| Who marks pickup complete | Recipient | They're the one physically collecting the food; low friction for busy donor |
| Completion UX | One-tap, no confirmation dialog | Fast and frictionless |
| Post-completion screen | Brief success screen → auto-navigate to claims list | Acknowledges the action, returns user somewhere useful |
| Notification job architecture | One generic `SendNotificationJob` replacing `SendClaimNotificationJob` | Eliminates duplication; all notification dispatching in one place |

---

## 3. Notification Map

| Trigger | Method | Notified user | Type |
|---|---|---|---|
| Recipient submits claim | `ListingClaimService::claim()` | Donor | `claim_received` ✅ existing |
| Donor confirms a claim | `ListingClaimService::confirmClaim()` | Confirmed recipient | `claim_confirmed` 🔴 new |
| Donor confirms (auto-rejects others) | `ListingClaimService::confirmClaim()` loop | Each rejected recipient | `claim_rejected` 🔴 new |
| Donor manually rejects a claim | `ListingClaimService::rejectClaim()` | That recipient | `claim_rejected` 🔴 new |
| Recipient marks pickup done | `CompleteListingService::complete()` | Donor | `pickup_completed` 🔴 new |

### Notification copy

| Type | Title | Body |
|---|---|---|
| `claim_confirmed` | "Your claim was accepted!" | "Get ready to pick up {listing_title}" |
| `claim_rejected` | "Claim not accepted" | "Your claim on {listing_title} was not accepted" |
| `pickup_completed` | "Food collected!" | "{recipient_name} picked up {listing_title}" |

### Notification `data` payloads (for deep linking)

| Type | data keys |
|---|---|
| `claim_confirmed` | `listing_id`, `claim_id`, `listing_title` |
| `claim_rejected` | `listing_id`, `claim_id`, `listing_title` |
| `pickup_completed` | `listing_id`, `listing_title` |

### Notification tap destinations (iOS deep links)

| Type | Destination |
|---|---|
| `claim_received` | Donor → listing's claims list |
| `claim_confirmed` | Recipient → listing detail (with "Mark as Picked Up" button) |
| `claim_rejected` | Recipient → browse listings screen |
| `pickup_completed` | Donor → listing detail (showing `completed`) |

---

## 4. New Endpoint

### `POST /recipient/listings/{listingId}/complete`

**Auth:** `auth:api` + role `recipient`

**Validation:**
- Listing exists → 404 if not
- Listing status is `claimed` → 400 if not
- Authenticated user has a `confirmed` claim on this listing → 403 if not

**Success flow:**
1. Set `listing.status = completed`
2. Dispatch `SendNotificationJob` to donor: type `pickup_completed`

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Pickup marked as complete",
  "data": { ...full listing shape... }
}
```

**Error cases:**
- `404` Listing not found
- `403` You don't have a confirmed claim on this listing
- `400` Listing is not in claimed status

---

## 5. API Resource Change

Add `contact` to the donor shape inside `FoodListingResource`:

**Before:**
```json
"donor": { "id": "uuid", "name": "Asha Shelter", "is_verified": true }
```

**After:**
```json
"donor": { "id": "uuid", "name": "Asha Shelter", "is_verified": true, "contact": "9841000000" }
```

Recipients need the donor's contact number to call before arriving for pickup.

---

## 6. Screen Flow

### Recipient — happy path (claim confirmed)

```
Submits claim → status: pending
      ↓
Receives claim_confirmed push notification
      ↓
Taps notification → Listing Detail Screen
      ┌─────────────────────────────────┐
      │  Dal Bhat                       │
      │  Status: Claimed (yours)        │
      │  Quantity: 15 portions          │
      │  Pickup before: 10 PM tonight   │
      │  📍 Thamel, Kathmandu  [Map]    │
      │  📞 Rahul Sharma  9841000000    │
      │  📋 "Call before coming"        │
      │                                 │
      │  [ Mark as Picked Up ]          │
      └─────────────────────────────────┘
      ↓ one tap, no dialog
Success screen (2–3 sec): "Pickup complete! Thanks for reducing food waste"
      ↓ auto-navigate
Claims list screen
```

**"Mark as Picked Up" button visibility rules:**
- Listing status is `claimed`
- AND authenticated recipient has a `confirmed` claim on it
- Hidden in all other states (expired, completed, pending/rejected claim)

### Recipient — rejected path

```
Receives claim_rejected push notification
      ↓
Taps → Browse listings screen (find another listing)
```

### Donor — post-confirmation

```
Confirms claim → listing: claimed
      ↓ (queued)
Confirmed recipient receives claim_confirmed
Other claimants receive claim_rejected
      ↓ (later, after recipient picks up)
Receives pickup_completed push notification
      ↓
Taps → Listing Detail Screen (status: completed)
```

---

## 7. File Map

### Create
- `app/Modules/Notifications/Jobs/SendNotificationJob.php` — generic job (userId, type, title, body, data)
- `app/Modules/FoodListings/Services/CompleteListingService.php`
- `app/Modules/FoodListings/Controllers/RecipientCompleteController.php`
- `tests/Feature/FoodListings/CompleteListingTest.php`

### Modify
- `app/Modules/Core/Enums/NotificationTypeEnum.php` — add `CLAIM_CONFIRMED`, `CLAIM_REJECTED`, `PICKUP_COMPLETED`
- `app/Modules/FoodListings/Services/ListingClaimService.php` — dispatch notifications in `confirmClaim()` and `rejectClaim()`, migrate from `SendClaimNotificationJob` to `SendNotificationJob`
- `app/Modules/FoodListings/Resources/FoodListingResource.php` — add `contact` to donor shape
- `routes/api.php` — register new complete route
- `API_DOC.md` — document new endpoint and resource change

### Delete / migrate
- `app/Modules/Notifications/Jobs/SendClaimNotificationJob.php` — replaced by `SendNotificationJob`

---

## 8. Out of Scope

- Recipient `food_requests` CRUD (separate feature)
- Chat / messaging between donor and recipient
- Rating / review after completion
- Donor manually marking as complete
