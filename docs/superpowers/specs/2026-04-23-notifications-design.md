# FeedLink Notifications Design Spec

**Date:** 2026-04-23
**Scope:** Push notifications (Firebase FCM) + in-app notification center — Donor POV, claim trigger only

---

## 1. Overview

When a recipient claims a donor's food listing, the donor receives:
1. A **Firebase push notification** to their iPhone
2. An **in-app notification** stored in the DB, visible in a notification center (bell icon) with read/unread state

No deep-linking for now — tapping the push notification simply opens the app.

---

## 2. Database Schema

### 2.1 `users` table — add column
```
fcm_token   string, nullable
```
iOS registers the token on login via `POST /api/user/device-token`. Nullable because users without a registered device have no token.

### 2.2 New `notifications` table
```
id          UUID PK
user_id     FK → users (the recipient of the notification)
type        enum: claim_received
title       string
body        string
data        JSON  — { listing_id, claim_id, listing_title }
read_at     timestamp nullable  (null = unread)
created_at
updated_at
```

`data` carries context for future deep-linking. `read_at` doubles as unread flag and timestamp.

---

## 3. Module Structure

```
app/Modules/Notifications/
├── Controllers/
│   └── NotificationController.php
├── Entities/
│   └── Notification.php                extends BaseModel
├── Repositories/
│   └── NotificationRepository.php      extends BaseRepository
├── Services/
│   ├── NotificationService.php         in-app record creation & queries
│   └── PushNotificationService.php     FCM via kreait/firebase-php
├── Jobs/
│   └── SendClaimNotificationJob.php    queued, orchestrates both services
├── Requests/
│   └── DeviceTokenRequest.php
└── Resources/
    └── NotificationResource.php
```

**Package:** `kreait/firebase-php` (official Firebase Admin PHP SDK)

---

## 4. API Endpoints

### Device Token Registration
```
POST /api/user/device-token
Auth: auth:api (any role)
Body: { "fcm_token": "string" }
Response 200: { "message": "Device token registered" }
```

### Notification Center
```
GET  /api/notifications             paginated, newest first
PUT  /api/notifications/{id}/read   mark one notification as read
PUT  /api/notifications/read-all    mark all as read for current user
```

### `GET /api/notifications` Response Shape
```json
{
  "status_code": 200,
  "message": "Notifications retrieved",
  "data": [
    {
      "id": "uuid",
      "type": "claim_received",
      "title": "New claim on your listing",
      "body": "Asha Shelter wants to claim Dal Bhat",
      "data": {
        "listing_id": "uuid",
        "claim_id": "uuid",
        "listing_title": "Dal Bhat"
      },
      "read_at": null,
      "created_at": "2026-04-23T10:00:00.000000Z"
    }
  ],
  "meta": { "unread_count": 3 }
}
```

`unread_count` in `meta` lets iOS update the bell badge without a separate request.

---

## 5. Trigger Flow

**Trigger point:** `ListingClaimService::claim()` — after the claim record is stored.

```
ListingClaimService::claim()
    → claim stored in DB
    → SendClaimNotificationJob::dispatch($claim->load(['listing.donor']))
    → returns claim (job is fire-and-forget, non-blocking)

SendClaimNotificationJob::handle()   (queued, background)
    1. NotificationService::create()
           writes notification row for the donor
    2. PushNotificationService::send()
           checks donor has fcm_token
           sends FCM push to donor's device
```

The claim eagerly loads `listing.donor` before dispatch to avoid extra DB queries inside the job.

---

## 6. Error Handling

| Failure | Behaviour |
|---|---|
| FCM send fails | Log error, swallow exception — in-app record already written |
| Donor has no fcm_token | Skip push silently — in-app notification still created |
| Job fails entirely | Laravel retries via queue (max 3 attempts) |
| Claim submission fails | Job never dispatched — no phantom notifications |

In-app notification is always written first. Push is best-effort.

---

## 7. Notification Types Enum

Only one type for now, extensible later:

```php
enum NotificationTypeEnum: string
{
    case CLAIM_RECEIVED = 'claim_received';
}
```

Future types to add: `claim_confirmed`, `claim_rejected`, `listing_expiring_soon`, `listing_expired`.

---

## 8. Push Notification Payload

```
Title: "New claim on your listing"
Body:  "{recipient_name} wants to claim {listing_title}"
Data:  { listing_id, claim_id }   ← for future deep-link use
```

---

## 9. Environment Variables Required

```
FIREBASE_CREDENTIALS=path/to/firebase-service-account.json
```

Or via environment JSON string (preferred for production):
```
FIREBASE_CREDENTIALS_JSON={"type":"service_account",...}
```

The service account JSON is downloaded from the Firebase console (Project Settings → Service Accounts → Generate new private key).

---

## 10. Routes to Add

```
POST  /api/user/device-token           — register FCM token
GET   /api/notifications               — list notifications
PUT   /api/notifications/{id}/read     — mark one read
PUT   /api/notifications/read-all      — mark all read
```

---

## 11. Success Criteria

- Recipient submits a claim → donor's iPhone receives a push notification
- Donor opens app → notification center shows unread notification with correct listing title and recipient name
- Tapping "mark read" updates `read_at` and the bell badge count decreases
- FCM failure never blocks or rolls back the claim submission
- Donor with no registered device still gets the in-app notification

---

## 12. Out of Scope (Future)

- Deep-linking (tap notification → go to specific listing)
- Recipient notifications (claim confirmed/rejected)
- Listing expiry warnings
- Notification preferences / mute settings
- Multiple device support
