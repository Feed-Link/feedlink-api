# FeedLink API Documentation

> **Base URL:** `https://api.feedlink.tech`  
> **Version:** 1.0  
> **Authentication:** Laravel Passport Bearer Token  
> **Content Type:** `application/json`

---

## Table of Contents

1. [Authentication Flow](#1-authentication-flow)
2. [Auth Endpoints](#2-auth-endpoints)
3. [Donor Endpoints](#3-donor-endpoints)
4. [Recipient Endpoints](#4-recipient-endpoints)
5. [Shared Endpoints](#5-shared-endpoints)
6. [Error Responses](#6-error-responses)
7. [Role Access Matrix](#7-role-access-matrix)
8. [Available Tags Reference](#8-available-tags-reference)
9. [iOS Quick Start](#9-ios-quick-start)

---

## 1. Authentication Flow

```
1. POST /api/auth/register   →  Create account (role: donor|recipient)
2. POST /api/auth/login      →  Get access_token + refresh_token
3. POST /api/auth/verify-otp →  Verify email OTP
4. All subsequent requests:  Authorization: Bearer {access_token}
5. When access_token expires: POST /api/auth/refresh-token
```

### Response Envelope

Every API response follows this format:

```json
{
  "status_code": 200,
  "message": "Success message",
  "data": { ... or [...] }
}
```

---

## 2. Auth Endpoints

### POST /api/auth/register

Create a new user account.

**Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "securepassword",
  "phone": "9841000000",
  "role": "donor"
}
```

| Field | Type | Required |
|---|---|---|
| `name` | string | Yes |
| `email` | string, email | Yes |
| `password` | string, min:8 | Yes |
| `phone` | string | Yes |
| `role` | string: `donor` \| `recipient` | Yes |

**Response (201):**
```json
{
  "status_code": 201,
  "message": "Registered Successfully",
  "data": {
    "user": {
      "id": "...",
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "9841000000"
    },
    "access_token": "eyJ...",
    "token_type": "Bearer",
    "expires_in": 86400
  }
}
```

---

### POST /api/auth/login

Log in with email and password.

**Body:**
```json
{
  "email": "john@example.com",
  "password": "securepassword"
}
```

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Logged In Successfully",
  "data": {
    "user": {
      "id": "...",
      "name": "John Doe",
      "email": "john@example.com"
    },
    "access_token": "eyJ...",
    "token_type": "Bearer",
    "expires_in": 86400
  }
}
```

---

### POST /api/auth/verify-otp

Verify the 6-digit email OTP sent after registration.

**Body:**
```json
{
  "email": "john@example.com",
  "otp": "123456"
}
```

---

### POST /api/auth/resend-otp

Resend the OTP to the user's email.

**Body:**
```json
{
  "email": "john@example.com"
}
```

---

### POST /api/auth/refresh-token

Exchange a refresh token for a new access token.

**Body:**
```json
{
  "refresh_token": "def50200..."
}
```

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Token Refreshed Successfully",
  "data": {
    "access_token": "eyJ...",
    "token_type": "Bearer",
    "expires_in": 86400,
    "refresh_token": "def50200..."
  }
}
```

---

### GET /api/auth/logout

Invalidate the current token and log out.

**Headers:** `Authorization: Bearer {access_token}`

---

### POST /api/auth/forgot-password

Start password reset flow.

**Body:**
```json
{
  "email": "john@example.com"
}
```

---

### POST /api/auth/reset-password

Complete password reset with OTP.

**Body:**
```json
{
  "email": "john@example.com",
  "otp": "123456",
  "password": "newpassword"
}
```

---

## 3. Donor Endpoints

**Auth:** `Bearer {token}` + role: `donor`

### POST /api/donor/listings

Create a new food listing with tags (replaces food_type).

**Headers:** `Authorization: Bearer {access_token}`

**Body:**
```json
{
  "title": "Leftover Dal Bhat",
  "description": "Freshly cooked, enough for 15 people",
  "quantity": "15 portions",
  "tags": ["for_humans", "cooked"],
  "photos": ["https://example.com/photo1.jpg"],
  "expires_at": "2026-04-06T20:00:00Z",
  "pickup_before": "2026-04-06T22:00:00Z",
  "pickup_instructions": "Call before coming",
  "latitude": 27.7172,
  "longitude": 85.3240,
  "address": "Thamel, Kathmandu"
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `title` | string, max:255 | Yes | e.g., "Leftover Dal Bhat" |
| `quantity` | string, max:100 | Yes | e.g., "15 portions", "5 kg" |
| `tags` | array of strings | Yes | **Min 1 tag**, valid: `for_humans`, `for_animals`, `for_both`, `cooked`, `raw_ingredients`, `packaged` |
| `expires_at` | datetime, after:now | Yes | When food expires (ISO 8601) |
| `pickup_before` | datetime, after:expires_at | Yes | Latest pickup time (ISO 8601) |
| `latitude` | decimal, -90 to 90 | Yes | e.g., 27.7172 |
| `longitude` | decimal, -180 to 180 | Yes | e.g., 85.3240 |
| `address` | string, max:500 | Yes | e.g., "Thamel, Kathmandu" |
| `description` | text | No | Additional details |
| `photos` | array of string | No | Image URLs |
| `pickup_instructions` | text | No | e.g., "Call before coming" |

**Response (201):**
```json
{
  "status_code": 201,
  "message": "Food listing created successfully",
  "data": {
    "id": "a17812b9-0969-46a4-bc4e-1f6b81c643e5",
    "title": "Leftover Dal Bhat",
    "description": "Freshly cooked, enough for 15 people",
    "quantity": "15 portions",
    "tags": [
      {
        "slug": "for_humans",
        "name": "For Humans",
        "category": "audience"
      },
      {
        "slug": "cooked",
        "name": "Cooked",
        "category": "state"
      }
    ],
    "photos": ["https://example.com/photo1.jpg"],
    "expires_at": "2026-04-06T20:00:00.000000Z",
    "pickup_before": "2026-04-06T22:00:00.000000Z",
    "pickup_instructions": "Call before coming",
    "status": "active",
    "latitude": 27.7172,
    "longitude": 85.324,
    "location": {
      "lat": 27.7172,
      "lng": 85.324
    },
    "address": "Thamel, Kathmandu",
    "distance_km": null,
    "donor": {
      "id": "a1780397-425a-4e2e-83f0-dc18b724cf72",
      "name": "Samaya Mahate",
      "is_verified": false
    },
    "confirmed_at": null,
    "created_at": "2026-04-05T06:33:42.000000Z"
  }
}
```

---

### GET /api/donor/listings

View this donor's listings with optional filters.

**Query Params:**
| Param | Default | Description |
|---|---|---|
| `status` | all | Filter: `active`, `claimed`, `completed`, `expired`, `cancelled` |
| `sort_by` | `created_at` | Sort field |
| `sort_order` | `desc` | `asc` or `desc` |
| `per_page` | 25 | Items per page |
| `page` | 1 | Page number |
| `no_paginate` | — | Include to return all results |

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Listings retrieved",
  "data": [
    { "id": "abc", "title": "...", "status": "active", ... }
  ]
}
```

---

### GET /api/donor/listings/{id}

View a single listing.

---

### PUT /api/donor/listings/{id}

Update a listing. Only allowed if listing `status` is `active`.

**Body:** Any subset of create listing fields (all optional).

---

### DELETE /api/donor/listings/{id}

Cancel a listing (sets status to `cancelled`).

---

### GET /api/donor/listings/{listingId}/claims

View all claims on a listing.

**Response:**
```json
{
  "status_code": 200,
  "message": "Claims retrieved",
  "data": [
    {
      "id": "claim-uuid",
      "food_listing_id": "listing-uuid",
      "note": "Picking up on behalf of 20 residents",
      "status": "pending",
      "recipient": {
        "id": "...",
        "name": "Asha Shelter",
        "is_verified": true
      },
      "created_at": "2026-04-02T16:00:00Z"
    }
  ]
}
```

---

### POST /api/donor/listings/{listingId}/claims/{claimId}/confirm

Confirm a recipient's claim on a listing. This sets the listing status to `claimed`.

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Claim confirmed successfully",
  "data": { ... }
}
```

---

### POST /api/donor/listings/{listingId}/claims/{claimId}/reject

Reject a recipient's claim.

---

## 4. Recipient Endpoints

**Auth:** `Bearer {token}` + role: `recipient`

### GET /api/recipient/listings

Browse all active listings by recipients.

**Query Params:** Same pagination/sort fields as donor listings.

---

### GET /api/recipient/listings/{id}

View a single listing detail.

---

### POST /api/recipient/listings/{listingId}/claim

Claim a food listing. Only available when listing `status` is `active`.

**Body:**
```json
{
  "note": "We are picking up on behalf of Asha Shelter"
}
```

| Field | Type | Required |
|---|---|---|
| `note` | text, max:500 | No |

**Response (201):**
```json
{
  "status_code": 201,
  "message": "Claim submitted successfully",
  "data": {
    "id": "claim-uuid",
    "food_listing_id": "listing-uuid",
    "note": "We are picking up on behalf of Asha Shelter",
    "listing": {
      "id": "listing-uuid",
      "title": "Dal Bhat leftovers"
    },
    "claimed_by": null,
    "recipient": {
      "id": "...",
      "name": "Asha Shelter",
      "is_verified": true
    },
    "status": "pending",
    "created_at": "2026-04-02T16:00:00Z"
  }
}
```

---

### DELETE /api/recipient/listings/{listingId}/claim

Cancel the authenticated user's claim for a listing. Only allowed when claim status is `pending`.

---

### GET /api/recipient/claims

View all claims by the authenticated recipient.

**Query Params:**
| Param | Default | Description |
|---|---|---|
| `status` | all | Filter: `pending`, `confirmed`, `rejected` |

---

### POST /api/recipient/requests

Create a new food request.

**Headers:** `Authorization: Bearer {access_token}`

**Body:**
```json
{
  "title": "Need cooked food for shelter",
  "description": "Feeding 25 people, prefer cooked meals",
  "quantity_needed": "10 kg",
  "tags": ["for_humans", "cooked"],
  "needed_by": "2026-04-06T18:00:00Z",
  "latitude": 27.7180,
  "longitude": 85.3250,
  "address": "Lazimpat, Kathmandu"
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `title` | string, max:255 | Yes | e.g., "Need food for 20 people" |
| `quantity_needed` | string, max:100 | Yes | e.g., "10 kg", "20 portions" |
| `tags` | array of strings | Yes | **Min 1 tag**, see [Available Tags Reference](#8-available-tags-reference) |
| `needed_by` | datetime, after:now | Yes | When food is needed (ISO 8601) |
| `latitude` | decimal, -90 to 90 | Yes | e.g., 27.7172 |
| `longitude` | decimal, -180 to 180 | Yes | e.g., 85.3240 |
| `address` | string, max:500 | Yes | e.g., "Lazimpat, Kathmandu" |
| `description` | text | No | Additional details |

**Response (201):**
```json
{
  "status_code": 201,
  "message": "Food request created successfully",
  "data": {
    "id": "req-uuid-123",
    "title": "Need cooked food for shelter",
    "description": "Feeding 25 people, prefer cooked meals",
    "quantity_needed": "10 kg",
    "tags": [
      {
        "slug": "for_humans",
        "name": "For Humans",
        "category": "audience"
      },
      {
        "slug": "cooked",
        "name": "Cooked",
        "category": "state"
      }
    ],
    "needed_by": "2026-04-06T18:00:00Z",
    "status": "open",
    "latitude": 27.7180,
    "longitude": 85.3250,
    "location": {
      "lat": 27.7180,
      "lng": 85.3250
    },
    "address": "Lazimpat, Kathmandu",
    "distance_km": null,
    "recipient": {
      "id": "rec-uuid-456",
      "name": "Red Cross Nepal",
      "is_verified": true
    },
    "created_at": "2026-04-05T14:30:00Z"
  }
}
```

---

### GET /api/recipient/requests

View all food requests created by the authenticated recipient.

**Query Params:** Same pagination/sort fields as donor listings.

---

### GET /api/recipient/requests/{id}

View a single food request detail.

---

### PUT /api/recipient/requests/{id}

Update a food request. Only allowed if request `status` is `open`.

**Body:** Any subset of create request fields (all optional, at least one required).

---

### DELETE /api/recipient/requests/{id}

Cancel a food request. Only allowed if `status` is `open`.

---

## 5. Shared Endpoints

**Auth:** `Bearer {token}` (any authenticated user)

### GET /api/listings/nearby

Browse nearby food listings using lat/lng coordinates.

**Query Params:**
| Param | Required | Default | Validation |
|---|---|---|---|
| `lat` | Yes | — | -90 to 90 |
| `lng` | Yes | — | -180 to 180 |
| `radius` | No | 5 | 1–50 km |
| `food_type` | No | — | `human`, `animal`, `both` |
| `status` | No | `active` | Any valid status |

**Example:** `GET /api/listings/nearby?lat=27.7172&lng=85.3240&radius=5&food_type=human`

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Nearby listings retrieved successfully",
  "data": [
    {
      "id": "a17812b9-0969-46a4-bc4e-1f6b81c643e5",
      "title": "Dal Bhat for 10",
      "description": "Freshly cooked dal bhat",
      "quantity": "10 portions",
      "tags": [
        {
          "slug": "for_humans",
          "name": "For Humans",
          "category": "audience"
        },
        {
          "slug": "cooked",
          "name": "Cooked",
          "category": "state"
        }
      ],
      "photos": [],
      "expires_at": "2026-04-06T20:00:00Z",
      "pickup_before": "2026-04-06T22:00:00Z",
      "pickup_instructions": "Call before coming",
      "status": "active",
      "latitude": 27.7182,
      "longitude": 85.3250,
      "location": {
        "lat": 27.7182,
        "lng": 85.3250
      },
      "address": "Thamel, Kathmandu",
      "distance_km": 0.18,
      "donor": {
        "id": "xyz-123",
        "name": "Momo House",
        "is_verified": true
      },
      "created_at": "2026-04-05T17:00:00Z"
    }
  ]
}
```

---

### GET /api/requests/nearby

Browse nearby food requests.

**Query Params:** Same as `/api/listings/nearby`. `status` default: `open`.

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Nearby requests retrieved successfully",
  "data": [
    {
      "id": "req-uuid-123",
      "title": "Need food for shelter",
      "description": "Feeding 25 people",
      "quantity_needed": "10 kg",
      "tags": [
        {
          "slug": "for_humans",
          "name": "For Humans",
          "category": "audience"
        },
        {
          "slug": "cooked",
          "name": "Cooked",
          "category": "state"
        }
      ],
      "needed_by": "2026-04-06T18:00:00Z",
      "status": "open",
      "latitude": 27.7180,
      "longitude": 85.3250,
      "location": {
        "lat": 27.7180,
        "lng": 85.3250
      },
      "address": "Lazimpat, Kathmandu",
      "distance_km": 0.15,
      "recipient": {
        "id": "rec-uuid-456",
        "name": "Red Cross Nepal",
        "is_verified": true
      },
      "created_at": "2026-04-05T14:00:00Z"
    }
  ]
}
```

---

### PUT /api/user/location

Update the authenticated user's current location.

**Body:**
```json
{
  "latitude": 27.7172,
  "longitude": 85.3240
}
```

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Location updated successfully",
  "data": null
}
```

---

### GET /api/user/profile

Get the authenticated user's profile.

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Profile retrieved successfully",
  "data": {
    "id": "...",
    "name": "John Doe",
    "email": "john@example.com",
    "contact": "9841000000",
    "is_verified": false,
    "profile_photo": null,
    "roles": ["donor"]
  }
}
```

---

### PUT /api/user/profile

Update the authenticated user's profile.

**Body:** (all fields optional)
```json
{
  "name": "John Updated Name",
  "contact": "9841122334",
  "profile_photo": "https://example.com/photos/profile.jpg"
}
```

---

## 6. Error Responses

| Status Code | Meaning | Examples |
|---|---|---|
| 400 | Bad Request | Claim on inactive listing, updating non-active listing |
| 401 | Unauthorized | Missing or expired token |
| 403 | Forbidden | Wrong role (e.g. donor accessing recipient routes) |
| 404 | Not Found | Listing/claim/user doesn't exist |
| 422 | Validation Error | Invalid payload format |
| 500 | Server Error | Internal exception |

**Error format:**
```json
{
  "status_code": 404,
  "message": "Listing not found",
  "data": null
}
```

**Validation errors (422):**
```json
{
  "message": "The title field is required.",
  "errors": {
    "title": ["The title field is required."]
  }
}
```

---

## 7. Role Access Matrix

| Endpoint | Donor | Recipient | Any Auth User |
|---|---|---|---|
| `POST /api/auth/register` | — | — | Public |
| `POST /api/auth/login` | — | — | Public |
| `POST /api/auth/verify-otp` | — | — | Public |
| `POST /api/auth/refresh-token` | — | — | Any |
| `GET /api/auth/logout` | — | — | Any Auth |
| `POST /api/auth/forgot-password` | — | — | Public |
| `POST /api/auth/reset-password` | — | — | Public |
| `POST /api/donor/listings` | Yes | — | — |
| `GET /api/donor/listings` | Yes | — | — |
| `GET /api/donor/listings/{id}` | Yes | — | — |
| `PUT /api/donor/listings/{id}` | Yes | — | — |
| `DELETE /api/donor/listings/{id}` | Yes | — | — |
| `GET /api/donor/listings/{listingId}/claims` | Yes | — | — |
| `POST /api/donor/listings/{listingId}/claims/{claimId}/confirm` | Yes | — | — |
| `POST /api/donor/listings/{listingId}/claims/{claimId}/reject` | Yes | — | — |
| `GET /api/recipient/listings` | — | Yes | — |
| `GET /api/recipient/listings/{id}` | — | Yes | — |
| `POST /api/recipient/listings/{listingId}/claim` | — | Yes | — |
| `DELETE /api/recipient/listings/{listingId}/claim` | — | Yes | — |
| `GET /api/recipient/claims` | — | Yes | — |
| `POST /api/recipient/requests` | — | Yes | — |
| `GET /api/recipient/requests` | — | Yes | — |
| `GET /api/recipient/requests/{id}` | — | Yes | — |
| `PUT /api/recipient/requests/{id}` | — | Yes | — |
| `DELETE /api/recipient/requests/{id}` | — | Yes | — |
| `GET /api/listings/nearby` | — | — | Any Auth |
| `GET /api/requests/nearby` | — | — | Any Auth |
| `PUT /api/user/location` | — | — | Any Auth |
| `GET /api/user/profile` | — | — | Any Auth |
| `PUT /api/user/profile` | — | — | Any Auth |

---

## 8. Available Tags Reference

Tags replace the old `food_type` field. Use these slugs when creating/updating listings or requests.

### Tag Categories

#### Audience (Choose at least one)
| Slug | Name | Description |
|---|---|---|
| `for_humans` | For Humans | Food suitable for human consumption |
| `for_animals` | For Animals | Food suitable for animals (pets, strays) |
| `for_both` | For Both | Food suitable for both humans and animals |

#### Food State (Choose at least one)
| Slug | Name | Description |
|---|---|---|
| `cooked` | Cooked | Ready-to-eat, fully cooked food |
| `raw_ingredients` | Raw Ingredients | Uncooked, raw food items |
| `packaged` | Packaged | Sealed/commercially packaged items |

### Example Tag Combinations
```json
// Cooked meal for humans
"tags": ["for_humans", "cooked"]

// Raw vegetables for animals
"tags": ["for_animals", "raw_ingredients"]

// Packaged snacks for both
"tags": ["for_both", "packaged"]

// Multiple tags (audience + state)
"tags": ["for_humans", "cooked"]
```

---

## 9. iOS Quick Start

### Setup

Add to your networking layer:

```swift
let baseURL = URL(string: "https://api.feedlink.tech/api")!
let authToken = "eyJ..." // Store in Keychain after login

func request(_ url: String, method: HttpMethod, body: Encodable? = nil) async throws -> Response {
    guard let endpoint = URL(string: url, relativeTo: baseURL) else {
        throw FeedLinkError.invalidURL
    }
    
    var request = URLRequest(url: endpoint)
    request.httpMethod = method.rawValue
    request.setValue("application/json", forHTTPHeaderField: "Content-Type")
    request.setValue("Bearer \(authToken)", forHTTPHeaderField: "Authorization")
    
    if let body = body {
        request.httpBody = try JSONEncoder().encode(body)
    }
    
    let (data, response) = try await URLSession.shared.data(for: request)
    
    guard let httpResponse = response as? HTTPURLResponse else {
        throw FeedLinkError.invalidResponse
    }
    
    if httpResponse.statusCode == 401 {
        try await refreshAccessToken()
        return try await request(url, method: method, body: body)
    }
    
    if !(200...299).contains(httpResponse.statusCode) {
        let error = try JSONDecoder().decode(APIError.self, from: data)
        throw FeedLinkError.api(status: httpResponse.statusCode, message: error.message)
    }
    
    return try JSONDecoder().decode(Response.self, from: data)
}
```

### Key Endpoints for iOS

```swift
// Nearby listings (map view)
GET /api/listings/nearby?lat={userLat}&lng={userLng}&radius=5

// User location (update from GPS)
PUT /api/user/location  { "latitude": ..., "longitude": ... }

// Create listing (camera → upload photo URLs)
POST /api/donor/listings {
  "title": "Leftover Dal Bhat",
  "quantity": "15 portions",
  "tags": ["for_humans", "cooked"],
  "latitude": 27.7172,
  "longitude": 85.3240,
  "address": "Thamel, Kathmandu",
  "expires_at": "2026-04-06T20:00:00Z",
  "pickup_before": "2026-04-06T22:00:00Z"
}

// Claim listing
POST /api/recipient/listings/{id}/claim  { "note": "..." }

// Refresh token
POST /api/auth/refresh-token  { "refresh_token": "..." }
```
