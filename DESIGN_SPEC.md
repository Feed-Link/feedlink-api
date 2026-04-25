# FeedLink iOS — Design Specification
> Hand this document to Claude.ai Design (or any designer) to generate screen designs.  
> Stack: SwiftUI · iOS 17+ · MapKit  
> API base: `https://api.feedlink.tech/api`

---

## Brand & Visual Identity

**Name:** FeedLink  
**Tagline:** Connecting surplus food with those who need it  
**Personality:** Warm, trustworthy, community-driven. Not clinical. Not startup-flashy.  

**Suggested palette:**
- Primary: Deep saffron / amber `#F59E0B` — food, warmth, generosity
- Secondary: Forest green `#16A34A` — freshness, sustainability
- Surface: Off-white `#FAFAF9`
- Text primary: `#1C1917`
- Text secondary: `#78716C`
- Destructive: `#DC2626`
- Success: `#16A34A`
- Warning: `#D97706`

**Typography:** SF Pro (system default). Titles bold, body regular.  
**Corner radius:** 12pt cards, 8pt buttons, 20pt bottom sheets.  
**Icons:** SF Symbols throughout.

---

## Navigation Architecture

### Role: Donor
Tab bar (4 tabs):
1. **Home** (house.fill) — stats + quick actions
2. **My Listings** (list.bullet) — own listings
3. **Requests** (mappin.and.ellipse) — browse nearby food requests
4. **Profile** (person.fill)

### Role: Recipient
Tab bar (4 tabs):
1. **Home** (house.fill) — browse nearby listings
2. **My Claims** (checkmark.circle.fill) — active claims
3. **My Requests** (doc.text.fill) — own food requests
4. **Profile** (person.fill)

---

## Auth Screens

### 1. Splash Screen
- Full bleed background: amber gradient top-to-bottom
- Centered logo: stylised wheat sheaf or bowl icon in white
- App name "FeedLink" below in white, large bold
- Tagline in white, small
- Auto-navigates after 1.5s → Login if token exists, else Onboarding

### 2. Onboarding / Role Select
- Clean white screen
- Headline: "I want to…"
- Two large cards (full width, tappable):
  - **Donate Food** — icon: gift.fill, amber accent, description: "I have surplus food to share"
  - **Receive Food** — icon: hands.sparkles.fill, green accent, description: "I'm looking for food for my community"
- Selecting a card highlights it with a border and checkmark
- "Continue" primary button (amber, full width) at bottom
- "Already have an account? Log in" text link below button

### 3. Register Screen
- Back arrow top-left
- Title: "Create Account"
- Role badge at top (e.g. amber pill "Donor" or green pill "Recipient") — read-only, from previous screen
- Form fields (stacked, rounded inputs):
  - Full Name
  - Email
  - Phone Number
  - Password (eye toggle)
- Checkbox: "I agree to the Terms & Conditions" (required)
- "Create Account" primary button
- On submit → OTP Verification screen
- API: `POST /auth/register` with `{ name, email, contact, password, role, location: { lat, long }, terms_accepted: true }`
- Location is requested from device at this point (CoreLocation)

### 4. OTP Verification Screen
- Title: "Verify your email"
- Subtitle: "We sent a 6-digit code to {email}"
- 6 individual digit boxes (auto-advance on input)
- "Verify" button (disabled until 6 digits entered)
- "Resend Code" text link (countdown timer: resend available after 60s)
- API: `POST /auth/verify-otp` → on success → Home screen (token stored in Keychain)
- Resend: `POST /auth/resend-otp`

### 5. Login Screen
- Logo mark top-center (small)
- Title: "Welcome back"
- Fields: Email, Password (eye toggle)
- "Forgot password?" link right-aligned below password
- "Log In" primary button
- "Don't have an account? Sign up" text link
- API: `POST /auth/login` → stores access_token + refresh_token in Keychain
- On 401 → show inline error "Invalid email or password"

### 6. Forgot Password Screen
- Title: "Reset Password"
- Subtitle: "Enter your email and we'll send a reset code"
- Email field
- "Send Reset Code" button
- API: `POST /auth/forgot-password`
- On success → Reset Password screen

### 7. Reset Password Screen
- Title: "Create New Password"
- Fields: OTP (6 digits), New Password, Confirm Password
- "Reset Password" button
- API: `POST /auth/reset-password`
- On success → Login screen with success toast

---

## Donor Screens

### D1. Donor Home
- Top: Greeting "Good morning, {name}" + notification bell (badge count from API)
- Stats strip (horizontal scroll, 4 cards):
  - Completed (green) `listings_completed`
  - Active (amber) `listings_active`
  - Recipients Helped (purple) `unique_recipients_served`
  - Expired (grey) `listings_expired`
- Section: "Your Active Listings" — horizontal scroll of listing cards (max 3, "See all" link)
- Section: "Nearby Food Requests" — 2 request preview cards with "Browse All" button
- Empty state (no listings yet): Illustration of food bowl + "Start sharing surplus food" + "Create Listing" button
- API: `GET /donor/stats`, `GET /donor/listings?status=active&per_page=3`, `GET /donor/requests?per_page=2`

### D2. My Listings Screen
- Title: "My Listings"
- Filter bar (horizontal scroll tabs): All · Active · Claimed · Completed · Expired · Cancelled
- List of **Listing Cards**:
  - Photo thumbnail (left, 80×80 rounded)
  - Title (bold)
  - Status pill (color-coded)
  - Quantity + expiry "Expires in 3h"
  - Claim count badge (e.g. "2 claims") if active
  - Right chevron
- FAB bottom-right: amber "+" button → Create Listing
- Pull-to-refresh
- API: `GET /donor/listings?status={filter}`

### D3. Create Listing Screen (Modal / Full screen push)
- Title: "New Listing"
- Form sections:
  **Food Details**
  - Title (text field, required)
  - Description (multiline, optional)
  - Quantity (text, e.g. "15 portions", required)
  - Tags (multi-select pill row): For Humans · For Animals · For Both · Cooked · Raw Ingredients · Packaged
  - Photos (horizontal scroll, tap to add via camera/library, max 5, upload to `POST /upload/photo` before submit)
  
  **Pickup Window**
  - Expires at (date+time picker, "Food available until")
  - Pickup before (date+time picker, must be after expires_at)
  - Pickup instructions (multiline, optional)
  
  **Location**
  - Address (text field, autofilled from device location)
  - Latitude / Longitude (hidden, from CoreLocation or map pin drag)
  - Mini map preview (MapKit, non-interactive, shows pin at selected coords)
  - "Use current location" button
  
- "Post Listing" primary button (amber, full width)
- API: `POST /donor/listings`

### D4. Listing Detail Screen (Donor view)
- Full bleed photo carousel at top (or placeholder if no photos)
- Back button top-left, "…" menu top-right (Edit / Cancel listing)
- Status pill below photo: Active / Claimed / Completed / Expired / Cancelled
- Title (large bold)
- Quantity · Tags (pills) · Expiry countdown
- Pickup instructions (if set)
- Address + small map (MapKit, pin at listing coords)
- Section: "Claims" (visible when active or claimed)
  - List of claim cards:
    - Avatar initial + Recipient name + "Verified" badge if is_verified
    - Note (if provided)
    - Time since submitted
    - If pending: "Confirm" (green) + "Reject" (red outline) buttons
    - If confirmed: green "Confirmed" badge
    - If rejected: grey "Rejected"
  - Confirming shows confirmation alert: "Confirm this claim? All other pending claims will be rejected."
- Relist button (amber outline) at bottom — visible for completed/expired/cancelled listings
- Reopen button (orange outline) — visible only when status = claimed
- API: `GET /donor/listings/{id}`, `GET /donor/listings/{listingId}/claims`, `POST .../confirm`, `POST .../reject`, `POST .../reopen`, `POST .../relist`

### D5. Browse Requests Screen (Donor)
- Title: "Nearby Requests"
- Segmented control top: **List** | **Map**
- Filter bar: radius slider (1–20 km) + status filter tabs (Open · Accepted)
- **List view**: Request cards
  - Recipient name + verified badge
  - Title (bold)
  - Quantity needed + needed by date
  - Tags (audience pills)
  - Distance badge (e.g. "1.2 km")
  - "Offer Help" button (green outline)
- **Map view**: MapKit map, pins for each request. Tap pin → bottom sheet with request preview + "Offer Help" button
- API: `GET /donor/requests?lat=&lng=&radius=&status=`

### D6. Request Detail Screen (Donor view)
- Header: Recipient name + verified badge + location distance
- Title (bold large)
- Description
- Quantity needed
- Needed by (date with countdown)
- Tags (audience pills)
- Address + mini map
- If donor has already offered: "Withdraw Offer" red outline button
- If not offered: "Offer Help" green button (tapping opens note input sheet before confirming)
- API: `POST /donor/requests/{id}/accept`, `DELETE /donor/requests/{id}/accept`

---

## Recipient Screens

### R1. Recipient Home
- Top: Greeting + notification bell
- Search bar (triggers radius filter)
- Radius pill selector: 1 km · 2 km · 5 km · 10 km
- Segmented control: **List** | **Map**
- **List view**: Vertical scroll of Listing Cards
  - Photo thumbnail
  - Title (bold)
  - Quantity + tags (pills)
  - Donor name + distance badge
  - Expiry countdown ("Expires in 2h" — amber if < 3h, red if < 1h)
  - "Claim" button (green outline, right side)
- **Map view**: MapKit, amber pins for active listings. Tap pin → bottom card with title, distance, "Claim" button
- Pull-to-refresh
- Empty state: "No listings near you right now. Try increasing your radius."
- API: `GET /listings/nearby?lat=&lng=&radius=`

### R2. Listing Detail Screen (Recipient view)
- Photo carousel
- Status pill
- Title + Quantity
- Tags (pills)
- Expiry + Pickup window
- Pickup instructions
- Donor section:
  - Donor name + verified badge
  - If recipient has a **confirmed** claim on this listing: show donor phone number with call button
- Address + mini map (MapKit pin)
- Claim button states:
  - "Claim This Food" (green, full width) — if active and no existing claim
  - "Cancel My Claim" (red outline) — if pending claim exists
  - "Pickup Confirmed — Mark as Collected" (green) — if claim confirmed
  - Greyed out — if claimed by someone else or not active
- Tapping "Claim" → note input bottom sheet → confirm → API call
- Tapping "Mark as Collected" → confirmation alert → `POST .../complete`
- API: `GET /recipient/listings/{id}`, `POST .../claim`, `DELETE .../claim`, `POST .../complete`

### R3. My Claims Screen
- Title: "My Claims"
- Status tab bar: All · Pending · Confirmed · Completed
- List of Claim cards:
  - Listing photo (small thumbnail)
  - Listing title
  - Status pill
  - Donor name + distance
  - Expiry/pickup date
  - Tap → Listing Detail
- Empty state per tab: "No {status} claims"
- API: `GET /recipient/claims?status={filter}`

### R4. My Requests Screen
- Title: "My Requests"
- Status tabs: All · Open · Accepted · Fulfilled
- List of Request cards:
  - Title
  - Quantity needed
  - Needed by date (countdown)
  - Status pill
  - Acceptance count badge (e.g. "3 offers") if open/accepted
  - Tap → Request Detail
- FAB: green "+" → Create Request
- API: `GET /recipient/requests`

### R5. Create Request Screen
- Title: "Request Food"
- Form:
  **What you need**
  - Title (e.g. "Rice for 20 families")
  - Description (optional)
  - Quantity needed (e.g. "20 kg")
  - Tags (multi-select pills): For Humans · For Animals · For Both
  - Needed by (date+time picker — sets both needed_by and expires_at)
  
  **Location**
  - Address (text field, autofilled)
  - Lat/lng (from CoreLocation)
  - Mini map preview
  - "Use current location" button

- "Post Request" green button
- API: `POST /recipient/requests`

### R6. Request Detail Screen (Recipient view)
- Header: Title + status pill
- Description, Quantity, Needed by countdown
- Tags
- Address + mini map
- Section: "Donor Offers" (if open or accepted)
  - List of acceptance cards:
    - Donor name + verified badge
    - Note (if provided)
    - Time since offered
    - If pending: "Confirm" (green) + "Decline" (red outline) buttons
    - If confirmed: green "Accepted" badge + donor contact (name + phone)
- If accepted + donor confirmed: "Mark as Fulfilled" green button at bottom
- Edit / Cancel options in "…" menu (only when open)
- API: `GET /recipient/requests/{id}`, `GET .../acceptances`, `POST .../confirm`, `POST .../reject`, `POST .../complete`

---

## Shared Screens

### S1. Full Map Screen
- MapKit full screen
- Floating back button top-left
- Pins color-coded:
  - Donor view: green pins = food requests
  - Recipient view: amber pins = available listings
- Tap pin → bottom card (300pt height, draggable):
  - Photo (if listing) or icon
  - Title, distance, key detail
  - Primary action button (Claim / Offer Help)
- "My Location" button bottom-right (centers map on user)
- Radius circle drawn around user location

### S2. Notifications Screen
- Title: "Notifications"
- "Mark all as read" top-right text button
- List (newest first):
  - Unread: white background, amber left border, bold title
  - Read: grey background, regular title
  - Each item: icon (SF Symbol based on type) + title + body + relative time
  - Swipe left → "Mark as read" action
- Empty state: "You're all caught up 👍"
- API: `GET /notifications?per_page=20`, `PUT /notifications/{id}/read`, `PUT /notifications/read-all`

**Notification type → icon mapping:**
| Type | SF Symbol | Label |
|---|---|---|
| claim_received | person.fill.badge.plus | New claim |
| claim_confirmed | checkmark.seal.fill | Claim accepted |
| claim_rejected | xmark.seal.fill | Claim not accepted |
| pickup_completed | bag.fill.badge.checkmark | Pickup complete |
| listing_expired_uncollected | clock.badge.exclamationmark | Listing expired |
| request_accepted | hand.raised.fill | Donor offered help |
| acceptance_confirmed | checkmark.circle.fill | Offer confirmed |
| acceptance_rejected | xmark.circle.fill | Offer declined |
| acceptance_withdrawn | arrow.uturn.left.circle | Offer withdrawn |
| request_fulfilled | star.fill | Request fulfilled |
| listing_reopened | arrow.clockwise | Listing reopened |
| listing_cancelled | trash.fill | Listing cancelled |

### S3. Profile Screen
- Avatar (photo or initial circle, amber background)
- Name + role pill
- Email + phone
- Edit profile button → Edit Profile Screen
- Logout (red text, bottom)
- API: `GET /user/profile`

### S4. Edit Profile Screen
- Fields: Name, Phone, Profile Photo (tap to pick from library → `POST /upload/photo`)
- Save button
- API: `PUT /user/profile` with `{ name, contact, profile_photo }`

---

## Component Library

### Listing Card
```
┌─────────────────────────────────────┐
│ [photo 80×80]  Title (bold)         │
│                Status pill          │
│                "15 portions · 2h"   │
│                "1.2 km away"        │
│                [Claim >]            │
└─────────────────────────────────────┘
```

### Request Card
```
┌─────────────────────────────────────┐
│ [icon]  Title (bold)                │
│         "20 kg · Needed by Fri"     │
│         [For Humans] tag            │
│         "0.8 km · 3 offers"         │
└─────────────────────────────────────┘
```

### Status Pills
| Status | Background | Text |
|---|---|---|
| active | amber/10 | amber |
| claimed | blue/10 | blue |
| completed | green/10 | green |
| expired | grey/10 | grey |
| cancelled | red/10 | red |
| open | green/10 | green |
| accepted | blue/10 | blue |
| fulfilled | purple/10 | purple |
| pending (claim) | amber/10 | amber |
| confirmed (claim) | green/10 | green |
| rejected (claim) | red/10 | red |

### Tag Pills
- Background: `#F5F5F4`, text: `#44403C`
- Small, rounded, horizontal scroll when many

### Empty States
- Centered illustration (SF Symbol, large, secondary color)
- Title (medium bold)
- Subtitle (secondary text, smaller)
- Optional CTA button

### Error States
- Toast (bottom, slides up): red background, white text, auto-dismiss 3s
- Inline field errors: red text below field
- Full-page error (network): retry button

---

## Key Flows (for prototyping)

### Claim flow (Recipient)
Browse → Listing Detail → "Claim This Food" → Note input sheet → "Submit Claim" → success toast → My Claims updated

### Confirm claim flow (Donor)
Listing Detail → Claims list → "Confirm" → alert confirmation → listing status → Claimed → other claims auto-rejected

### Complete pickup flow (Recipient)
Listing Detail → "Mark as Collected" → confirmation alert → status → Completed → donor notified

### Accept request flow (Donor)
Browse Requests → Request Detail → "Offer Help" → note input sheet → "Submit Offer" → success toast

---

## API Quick Reference for iOS Engineers

### Auth headers
```
Authorization: Bearer {access_token}
Content-Type: application/json
```

### Token refresh
On any 401 response: call `POST /auth/refresh-token` with stored `refresh_token`. On success store new pair. On failure → logout.

### Response envelope
```json
{ "status_code": 200, "message": "...", "data": {} }
```

### Keychain keys
- `feedlink_access_token`
- `feedlink_refresh_token`
- `feedlink_user_role` (donor | recipient)
- `feedlink_user_id`

### On every app launch after login
1. `POST /user/device-token` with FCM token
2. `GET /user/profile` to refresh user state
3. Request CoreLocation permission if not already granted

### Photo upload pattern
1. User picks photo from library / camera
2. Immediately `POST /upload/photo` (multipart, field: `photo`)
3. Store returned `url` in local state
4. Include `urls` in listing/profile create/update payload

### Pagination
Append `?page=1&per_page=15`. Response includes `meta.current_page`, `meta.last_page`. Load more when scrolled to bottom and `current_page < last_page`.

---

## Screens Checklist

### Auth
- [ ] Splash
- [ ] Onboarding / Role select
- [ ] Register
- [ ] OTP Verification
- [ ] Login
- [ ] Forgot Password
- [ ] Reset Password

### Donor
- [ ] D1 Home
- [ ] D2 My Listings
- [ ] D3 Create Listing
- [ ] D4 Listing Detail (donor view)
- [ ] D5 Browse Requests (list + map tabs)
- [ ] D6 Request Detail (donor view)

### Recipient
- [ ] R1 Home (list + map tabs)
- [ ] R2 Listing Detail (recipient view)
- [ ] R3 My Claims
- [ ] R4 My Requests
- [ ] R5 Create Request
- [ ] R6 Request Detail (recipient view)

### Shared
- [ ] S1 Full Map
- [ ] S2 Notifications
- [ ] S3 Profile
- [ ] S4 Edit Profile
