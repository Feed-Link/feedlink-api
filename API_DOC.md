# FeedLink API Documentation (Implementation-Accurate)

> **Base URL:** `https://api.feedlink.tech/api`  
> **Authentication:** `Authorization: Bearer {access_token}` (Laravel Passport)  
> **Primary source:** `routes/api.php` + controller logic + request validation rules

---

## 1. Global Response Format

All controller success responses use:

```json
{
  "status_code": 200,
  "message": "Success message",
  "data": {}
}
```

For paginated responses, `meta` and `links` are also returned.

Error responses use:

```json
{
  "status_code": 400,
  "message": "Error message",
  "data": null
}
```

Validation failures (`422`) may return Laravel validation error format.

---

## 2. Route List (Current)

| Method | Path | Auth | Role |
|---|---|---|---|
| GET | `/` | No | Public |
| POST | `/auth/register` | No | Public |
| POST | `/auth/login` | No | Public |
| GET | `/auth/logout` | Yes | Any |
| POST | `/auth/verify-otp` | No | Public |
| POST | `/auth/resend-otp` | No | Public |
| POST | `/auth/refresh-token` | No | Public |
| POST | `/auth/forgot-password` | No | Public |
| POST | `/auth/reset-password` | No | Public |
| GET | `/donor/listings` | Yes | donor |
| POST | `/donor/listings` | Yes | donor |
| GET | `/donor/listings/{id}` | Yes | donor |
| PUT | `/donor/listings/{id}` | Yes | donor |
| DELETE | `/donor/listings/{id}` | Yes | donor |
| GET | `/donor/listings/{listingId}/claims` | Yes | donor |
| POST | `/donor/listings/{listingId}/claims/{claimId}/confirm` | Yes | donor |
| POST | `/donor/listings/{listingId}/claims/{claimId}/reject` | Yes | donor |
| GET | `/recipient/listings` | Yes | recipient |
| GET | `/recipient/listings/{id}` | Yes | recipient |
| POST | `/recipient/listings/{listingId}/claim` | Yes | recipient |
| DELETE | `/recipient/listings/{listingId}/claim` | Yes | recipient |
| GET | `/recipient/claims` | Yes | recipient |
| GET | `/listings/nearby` | Yes | Any |
| GET | `/requests/nearby` | Yes | Any |
| PUT | `/user/location` | Yes | Any |
| GET | `/user/profile` | Yes | Any |
| PUT | `/user/profile` | Yes | Any |

---

## 3. Auth + Public Endpoints

### GET `/`
Health endpoint.

**Response (200):**
```json
{
  "message": "Application is running"
}
```

### POST `/auth/register`
Register user.

**Request body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "contact": "9841000000",
  "password": "secret123",
  "role": "donor",
  "location": {
    "lat": 27.7172,
    "long": 85.3240
  },
  "terms_accepted": true
}
```

**Validation:**
- `name`: required|string|max:255
- `email`: required|email|unique:users,email
- `contact`: required|string|max:10
- `password`: required|string|min:6
- `role`: required|in:donor,recipient
- `location`: required|array
- `location.lat`: required_with:location|numeric|between:-90,90
- `location.long`: required_with:location|numeric|between:-180,180
- `terms_accepted`: required|accepted

**Response (201):**
```json
{
  "status_code": 201,
  "message": "Registered Successfully",
  "data": "john@example.com"
}
```

### POST `/auth/login`
Login user.

**Request body:**
```json
{
  "email": "john@example.com",
  "password": "secret123"
}
```

**Validation:**
- `email`: required|email
- `password`: required|string|min:6

**Response (202):**
```json
{
  "status_code": 202,
  "message": "Logged In Successfully",
  "data": {
    "access_token": "eyJ...",
    "refresh_token": "random64chars...",
    "expires_in": 1800
  }
}
```

### GET `/auth/logout`
Logout current access token. Optional query/body input: `refresh_token`.

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Logged Out Successfully",
  "data": null
}
```

### POST `/auth/verify-otp`
Verify email OTP.

**Request body:**
```json
{
  "email": "john@example.com",
  "otp": "123456"
}
```

**Validation:**
- `email`: required|email|exists:users,email
- `otp`: required|digits:6

**Response (200):**
```json
{
  "status_code": 200,
  "message": "OTP Verified Successfully",
  "data": {
    "access_token": "eyJ...",
    "refresh_token": "random64chars...",
    "expires_in": 1800
  }
}
```

### POST `/auth/resend-otp`
Resend OTP.

**Request body:**
```json
{
  "email": "john@example.com"
}
```

**Validation:**
- `email`: required|email|exists:users,email

**Response (200):**
```json
{
  "status_code": 200,
  "message": "OTP Resend Successfully",
  "data": null
}
```

### POST `/auth/refresh-token`
Get new access token.

**Request body:**
```json
{
  "refresh_token": "random64chars..."
}
```

**Validation:**
- `refresh_token`: required|string

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Token Refreshed Successfully",
  "data": {
    "access_token": "eyJ...",
    "refresh_token": "newRandom64chars...",
    "expires_in": 1800
  }
}
```

### POST `/auth/forgot-password`
Send reset OTP.

**Request body:**
```json
{
  "email": "john@example.com"
}
```

**Validation:**
- `email`: required|email|exists:users,email

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Password reset OTP sent successfully",
  "data": null
}
```

### POST `/auth/reset-password`
Reset password using OTP.

**Request body:**
```json
{
  "email": "john@example.com",
  "otp": "123456",
  "password": "newSecret123",
  "password_confirmation": "newSecret123"
}
```

**Validation:**
- `email`: required|email|exists:users,email
- `otp`: required|digits:6
- `password`: required|confirmed|Password::defaults()

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Password reset successfully",
  "data": {
    "access_token": "eyJ...",
    "refresh_token": "random64chars...",
    "expires_in": 1800
  }
}
```

---

## 4. Donor Endpoints

All require `auth:api` + role `donor`.

### GET `/donor/listings`
Get donor listings (supports repository-driven filtering/pagination params).

**Common query params used in project:**
- `status` (optional)
- `page`, `per_page`, `sort_by`, `sort_order` (optional)

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Listings retrieved",
  "data": [
    {
      "id": "uuid",
      "title": "Leftover Dal Bhat",
      "description": "Freshly cooked",
      "quantity": "15 portions",
      "tags": [
        { "slug": "for_humans", "name": "For Humans", "category": "audience" },
        { "slug": "cooked", "name": "Cooked", "category": "state" }
      ],
      "photos": [],
      "expires_at": "2026-04-06T20:00:00.000000Z",
      "pickup_before": "2026-04-06T22:00:00.000000Z",
      "pickup_instructions": null,
      "status": "active",
      "latitude": 27.7172,
      "longitude": 85.324,
      "location": { "lat": 27.7172, "lng": 85.324 },
      "address": "Thamel, Kathmandu",
      "distance_km": null,
      "donor": { "id": "donor-uuid", "name": "Donor Name", "is_verified": false },
      "confirmed_at": null,
      "created_at": "2026-04-05T06:33:42.000000Z"
    }
  ]
}
```

If paginated by repository, response may include `meta` and `links`.

### POST `/donor/listings`
Create listing.

**Request body:**
```json
{
  "title": "Leftover Dal Bhat",
  "description": "Freshly cooked, enough for 15 people",
  "quantity": "15 portions",
  "tags": ["for_humans", "cooked"],
  "photos": ["https://cdn.example.com/l1.jpg"],
  "expires_at": "2026-04-06T20:00:00Z",
  "pickup_before": "2026-04-06T22:00:00Z",
  "pickup_instructions": "Call before coming",
  "latitude": 27.7172,
  "longitude": 85.3240,
  "address": "Thamel, Kathmandu"
}
```

**Validation:**
- `title`: required|string|max:255
- `description`: nullable|string
- `quantity`: required|string|max:100
- `tags`: required|array|min:1
- `tags.*`: required|in:for_humans,for_animals,for_both,cooked,raw_ingredients,packaged
- `photos`: nullable|array
- `photos.*`: string
- `expires_at`: required|date|after:now
- `pickup_before`: required|date|after:expires_at
- `pickup_instructions`: nullable|string
- `latitude`: required|numeric|between:-90,90
- `longitude`: required|numeric|between:-180,180
- `address`: required|string|max:500

**Response (201):** Listing payload follows same shape as in `GET /donor/listings` item.

### GET `/donor/listings/{id}`
Get single listing by ID.

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Listing retrieved",
  "data": {
    "id": "uuid",
    "title": "Leftover Dal Bhat"
  }
}
```
Actual `data` includes full listing shape shown above.

### PUT `/donor/listings/{id}`
Update listing.

**Request body:** any subset of create fields.

**Validation (all optional):**
- `title`: sometimes|string|max:255
- `description`: nullable|string
- `quantity`: sometimes|string|max:100
- `tags`: sometimes|array|min:1
- `tags.*`: required|in:for_humans,for_animals,for_both,cooked,raw_ingredients,packaged
- `photos`: nullable|array
- `photos.*`: string
- `expires_at`: sometimes|date|after:now
- `pickup_before`: sometimes|date|after:expires_at
- `pickup_instructions`: nullable|string
- `latitude`: sometimes|numeric|between:-90,90
- `longitude`: sometimes|numeric|between:-180,180
- `address`: sometimes|string|max:500

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Food listing updated successfully",
  "data": {
    "id": "uuid"
  }
}
```
Actual `data` includes full listing shape.

### DELETE `/donor/listings/{id}`
Cancel listing.

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Listing cancelled successfully",
  "data": null
}
```

### GET `/donor/listings/{listingId}/claims`
Get all claims for listing.

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Claims retrieved",
  "data": [
    {
      "id": "claim-uuid",
      "food_listing_id": "listing-uuid",
      "note": "Picking up for shelter",
      "status": "pending",
      "recipient": {
        "id": "recipient-uuid",
        "name": "Asha Shelter",
        "is_verified": true
      },
      "created_at": "2026-04-02T16:00:00.000000Z"
    }
  ]
}
```

### POST `/donor/listings/{listingId}/claims/{claimId}/confirm`
Confirm one claim.

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Claim confirmed successfully",
  "data": {
    "id": "listing-uuid",
    "status": "claimed",
    "claimed_by": "recipient-uuid"
  }
}
```
Actual `data` includes full listing shape.

### POST `/donor/listings/{listingId}/claims/{claimId}/reject`
Reject claim.

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Claim rejected successfully",
  "data": null
}
```

---

## 5. Recipient Endpoints

All require `auth:api` + role `recipient`.

### GET `/recipient/listings`
Get active listings for recipients.

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Listings retrieved",
  "data": [
    {
      "id": "listing-uuid",
      "title": "Dal Bhat"
    }
  ]
}
```
Actual item includes full listing shape.

### GET `/recipient/listings/{id}`
Get listing detail.

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Listing retrieved",
  "data": {
    "id": "listing-uuid",
    "title": "Dal Bhat"
  }
}
```
Actual `data` includes full listing shape.

### POST `/recipient/listings/{listingId}/claim`
Claim listing.

**Request body:**
```json
{
  "note": "We are picking up at 7 PM"
}
```

**Validation:**
- `note`: nullable|string|max:500

**Response (201):**
```json
{
  "status_code": 201,
  "message": "Claim submitted successfully",
  "data": {
    "id": "claim-uuid",
    "food_listing_id": "listing-uuid",
    "note": "We are picking up at 7 PM",
    "listing": { "id": "listing-uuid", "title": "Dal Bhat" },
    "claimed_by": null,
    "status": "pending",
    "created_at": "2026-04-02T16:00:00.000000Z"
  }
}
```

### DELETE `/recipient/listings/{listingId}/claim`
Cancel own claim.

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Claim cancelled successfully",
  "data": null
}
```

### GET `/recipient/claims`
Get my claims (supports repository filtering).

**Query params:**
- `status` (optional, used through filter pipeline)

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Claims retrieved",
  "data": [
    {
      "id": "claim-uuid",
      "food_listing_id": "listing-uuid",
      "note": "Need urgently",
      "listing": { "id": "listing-uuid", "title": "Dal Bhat" },
      "claimed_by": null,
      "status": "pending",
      "created_at": "2026-04-02T16:00:00.000000Z"
    }
  ]
}
```

---

## 6. Shared Authenticated Endpoints

Require `auth:api`.

### GET `/listings/nearby`
Fetch nearby listings.

**Query params:**
- `lat` (required, numeric, -90..90)
- `lng` (required, numeric, -180..180)
- `radius` (optional, numeric, 1..50, default 5)
- `food_type` (optional, one of `human`, `animal`, `both`)
- `status` (optional, one of `active`, `claimed`, `completed`, `expired`, `cancelled`)

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Nearby listings retrieved successfully",
  "data": [
    {
      "id": "listing-uuid",
      "title": "Dal Bhat",
      "distance_km": 0.18
    }
  ]
}
```
Actual items include full listing shape with `distance_km`.

### GET `/requests/nearby`
Fetch nearby requests.

**Query params:**
- `lat` (required, numeric, -90..90)
- `lng` (required, numeric, -180..180)
- `radius` (optional, numeric, 1..50, default 5)
- `food_type` (optional, one of `human`, `animal`, `both`)
- `status` (optional, one of `open`, `accepted`, `fulfilled`, `expired`, `cancelled`)

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Nearby requests retrieved successfully",
  "data": [
    {
      "id": "request-uuid",
      "recipient_id": "recipient-uuid",
      "title": "Need food",
      "description": "For shelter",
      "quantity_needed": "10 kg",
      "food_type": "human",
      "needed_by": "2026-04-06T18:00:00.000000Z",
      "status": "open",
      "latitude": 27.7172,
      "longitude": 85.324,
      "location": { "lat": 27.7172, "lng": 85.324 },
      "address": "Kathmandu",
      "distance_km": 0.15,
      "recipient": {
        "id": "recipient-uuid",
        "name": "Asha Shelter",
        "is_verified": true
      },
      "created_at": "2026-04-05T14:00:00.000000Z"
    }
  ]
}
```

### PUT `/user/location`
Update current user location.

**Request body:**
```json
{
  "latitude": 27.7172,
  "longitude": 85.3240
}
```

**Validation:**
- `latitude`: required|numeric|between:-90,90
- `longitude`: required|numeric|between:-180,180

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Location updated successfully",
  "data": {
    "latitude": 27.7172,
    "longitude": 85.324,
    "location": {
      "lat": 27.7172,
      "lng": 85.324
    }
  }
}
```

### GET `/user/profile`
Get current profile.

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Profile retrieved successfully",
  "data": {
    "id": "user-uuid",
    "name": "John Doe",
    "email": "john@example.com",
    "contact": "9841000000",
    "is_verified": false,
    "profile_photo": null,
    "latitude": 27.7172,
    "longitude": 85.324,
    "location": { "lat": 27.7172, "lng": 85.324 },
    "roles": ["recipient"]
  }
}
```

### PUT `/user/profile`
Update profile.

**Request body (all optional):**
```json
{
  "name": "John Updated",
  "contact": "9841122334",
  "profile_photo": "https://cdn.example.com/profile.jpg"
}
```

**Validation:**
- `name`: sometimes|string|max:255
- `contact`: sometimes|string|max:20
- `profile_photo`: sometimes|string

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Profile updated successfully",
  "data": {
    "id": "user-uuid",
    "name": "John Updated",
    "email": "john@example.com",
    "contact": "9841122334",
    "is_verified": false,
    "profile_photo": "https://cdn.example.com/profile.jpg",
    "latitude": 27.7172,
    "longitude": 85.324,
    "location": { "lat": 27.7172, "lng": 85.324 },
    "roles": ["recipient"]
  }
}
```

---

## 7. Enums Used in Payload Validation

### `tags` allowed values
- `for_humans`
- `for_animals`
- `for_both`
- `cooked`
- `raw_ingredients`
- `packaged`

### `role` allowed values
- `donor`
- `recipient`

---

## 8. Common Error Cases

Examples from current code:
- `404` Listing not found / Claim not found / User not found
- `403` Unauthorized (owner mismatch or role mismatch)
- `400` Listing is not available / Claim is not pending / Email not verified
- `401` Invalid or expired refresh token
- `422` Validation errors

---

## 9. Frontend Integration Notes

- Use `GET /auth/logout` (not POST).
- `register` currently returns email string in `data`, not token payload.
- `login` returns `202 Accepted` status.
- `reset-password` requires `password_confirmation`.
- `recipient/requests` CRUD routes are not currently registered.
