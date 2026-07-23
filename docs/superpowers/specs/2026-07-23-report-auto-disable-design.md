# Report / Auto-Disable Feature — Design

## Purpose
Let donors and recipients report each other on a specific listing/claim. Repeated reports against the same user auto-disable their account; admin can also disable/enable manually.

## Scope
- Reporting: both directions (donor↔recipient), always tied to a `food_listing_id` and/or `claim_id`.
- Category dropdown + free-text description.
- Auto-disable: 3 reports against the same user within 30 days.
- One report per reporter per listing/claim (unique constraint, prevents spam-triggered disable).
- Email on disable: admin(s) only, not the disabled user.
- Admin can also manually disable/enable any account, independent of the report count.
- Disabling a donor auto-cancels their active listings; disabling a recipient auto-cancels their pending claims.
- Disabled accounts are blocked from login entirely (not just write actions).

## Data Model

### New table: `reports`
| column | type | notes |
|---|---|---|
| id | uuid pk | |
| reporter_id | uuid, FK users | who filed it |
| reported_user_id | uuid, FK users | who's being reported |
| food_listing_id | uuid, FK food_listings, nullable | |
| claim_id | uuid, FK listing_claims, nullable | |
| category | enum | `no_show`, `food_quality_safety`, `rude_behavior`, `fake_listing`, `other` |
| description | text | |
| status | enum | `pending`, `reviewed`, `dismissed`, `actioned` |
| created_at / updated_at | timestamps | |

Unique constraint: `(reporter_id, food_listing_id, claim_id)`.

### `users` table additions
- `disabled_at` (nullable timestamp)
- `disabled_reason` (nullable string)

(existing `is_active` boolean stays as the actual enforcement flag; these two are audit/display fields for admin UI)

## Flow
1. `POST /reports` (new endpoint, either role) → `ReportController::store()` → `ReportService::file()`.
2. `ReportService::file()`:
   - Validates unique constraint (reject duplicate report on same listing/claim from same reporter with a clear 409/422).
   - Creates the `Report` row, status `pending`.
   - Counts reports against `reported_user_id` created in the last 30 days.
   - If count `>= 3` → calls `UserModerationService::disable($reportedUser, reason: 'auto: 3+ reports in 30 days')`.
3. `UserModerationService::disable(User $user, string $reason)`:
   - Sets `is_active = false`, `disabled_at = now()`, `disabled_reason = $reason`.
   - If user has `donor` role: cancels their `active`/`claimed` listings (status → `cancelled`).
   - If user has `recipient` role: cancels their `pending`/`accepted` claims.
   - Dispatches `AccountDisabledMail` to configured admin email(s) (not the disabled user).
4. `UserModerationService::enable(User $user)` — admin-only, called from Filament. Clears `is_active`, `disabled_at`, `disabled_reason`.

## Enforcement
New middleware `EnsureAccountActive`, applied to `auth:api` route group and explicitly inside the login flow (so a disabled user gets a clear `403 {error: 'account_disabled', reason}` response rather than being let in and failing later, or getting a generic auth error at login).

## Error Handling
- Duplicate report on same listing/claim → 409 with message "You've already reported this."
- Reporting yourself → 422 (validate `reported_user_id !== auth user id`).
- Reporting a listing/claim you have no relationship to (not the donor or recipient on it) → 403.

## Testing
- Unit: `ReportService::file()` triggers disable at exactly 3 reports within window, not at 2; reports older than 30 days don't count.
- Unit: `UserModerationService::disable()` cancels correct listings/claims per role.
- Feature: duplicate report rejected; self-report rejected; disabled user blocked at login.
