# Admin Dashboard (Filament) — Design

## Purpose
Full-fledged admin dashboard at `admin.feedlink.tech` for managing users, reports, listings/claims, and food-safety records. Depends on the report-auto-disable feature's data model (see companion spec).

## Scope
- Same `feedlink-api` codebase, Filament panel added in (no separate repo/deploy).
- Auth: same `users` table, Filament's built-in panel login, gated on Spatie `admin` role via `canAccessPanel()`.
- Resources: Users, Reports, Food Listings + Claims, Illness Claims, Donor Warnings, Dashboard stats widgets.

## Panel Setup
- `composer require filament/filament`
- New `App\Providers\Filament\AdminPanelProvider` — panel id `admin`, path `/` served on the `admin.feedlink.tech` domain (via `->domain('admin.feedlink.tech')` panel config), login required, `canAccessPanel()` → `$user->hasRole('admin')`.

## Resources

### UserResource
- Table columns: name, email, role badge, `is_active`, `disabled_at`.
- Filters: role, is_active.
- Actions: "Disable"/"Enable" button → calls `UserModerationService::disable()/enable()`, prompts for reason on disable.
- Relation manager: reports where this user is `reporter_id` or `reported_user_id`.

### ReportResource
- Table columns: reporter, reported user, category, status, created_at.
- Filters: status, category.
- View page: shows linked listing/claim.
- Actions: mark reviewed / dismissed; "Disable reported user" action.

### FoodListingResource (+ ClaimResource as relation manager)
- Read-mostly browse: donor, status, expires_at, pickup_before, location.
- Action: force-cancel (sets status → cancelled).

### IllnessClaimResource
- CRUD/view over `illness_claims` — currently zero admin UI exists for this.

### DonorWarningResource
- View over `donor_warnings`.

### Dashboard
- Widgets: total users, active listings count, claims today, pending reports count.

## Deployment
- Add to `.github/workflows/laravel.yml`: after `composer install`, run `php artisan filament:upgrade` (asset publish/cache).
- New nginx server block for `admin.feedlink.tech`, same `public/` root and PHP-FPM pool as the main app (no new droplet/process).
- DNS: add an A record for `admin.feedlink.tech` → droplet IP wherever `feedlink.tech` DNS is managed (user action, not something done from the server).

## Testing
- Manual: log in as admin role, verify non-admin roles get rejected at panel login.
- Manual: disable/enable action round-trips through `UserModerationService` correctly (reuses the report feature's tested service, no duplicate logic here).
