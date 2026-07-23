# Admin Dashboard (Filament) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. **MANDATORY:** Load and follow the `modular-monolithic-pattern` skill before writing any PHP code in this plan. **DEPENDS ON:** `docs/superpowers/plans/2026-07-23-report-auto-disable.md` must be implemented first — this plan's `UserResource`/`ReportResource` call `UserModerationService` and read the `reports` table from that plan.

**Goal:** Full Filament v4 admin panel at `admin.feedlink.tech`, in the same `feedlink-api` codebase, managing Users, Reports, Food Listings/Claims, Food Safety records, with dashboard stats.

**Architecture:** One new Filament panel (`admin`), gated on the Spatie `admin` role. One Filament Resource per entity, thin — resources call existing Services (`UserModerationService` from the report-auto-disable plan) rather than touching models/repositories directly, keeping the Controller→Service→Repository layering intact even though Filament isn't a traditional controller.

**Tech Stack:** `filament/filament` ^4.0 (Laravel 12 + Livewire 4 compatible, and this repo already has `livewire/livewire ^4.2` installed), existing `UserModerationService`, `ReportRepository`, `FoodListingRepository`, `ListingClaimRepository`.

## Global Constraints

- Same codebase, same deploy pipeline, same droplet — no new repo, no new server process.
- Panel login uses the existing `users` table + Spatie `admin` role, not a separate credential store.
- Every Filament Resource that mutates data (disable/enable/force-cancel) must call the existing Service layer (`UserModerationService`, etc.), never write to a model directly from a Resource class.
- Run `vendor/bin/pint --dirty --format agent` after editing PHP files, before committing.
- If a Filament API signature in this plan doesn't match the installed version (check with `composer show filament/filament`), use `mcp__plugin_context7_context7__query-docs` to look up the correct signature for that exact version before adjusting the code — don't guess.

---

### Task 1: Install Filament, create the `admin` panel, gate access on the `admin` role

**Files:**
- Modify: `composer.json` (adds `filament/filament`)
- Create: `app/Providers/Filament/AdminPanelProvider.php`
- Modify: `app/Models/User.php` (add `FilamentUser` contract + `canAccessPanel()`)
- Modify: `bootstrap/providers.php` (register the panel provider)
- Test: `tests/Feature/Admin/AdminPanelAccessTest.php`

**Interfaces:**
- Consumes: `App\Models\User::hasRole()` (Spatie, existing).
- Produces: panel accessible at `/admin` locally (domain-routed to `admin.feedlink.tech` in production per Task 8); `User::canAccessPanel(Panel $panel): bool`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    public function test_admin_role_can_access_panel()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue($admin->canAccessPanel(filament()->getPanel('admin')));
    }

    public function test_non_admin_role_cannot_access_panel()
    {
        $donor = User::factory()->create();
        $donor->assignRole('donor');

        $this->assertFalse($donor->canAccessPanel(filament()->getPanel('admin')));
    }

    public function test_admin_login_page_loads()
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/AdminPanelAccessTest.php`
Expected: FAIL — `filament()` helper / panel don't exist yet.

- [ ] **Step 3: Install Filament and wire up the panel**

Run:
```bash
composer require filament/filament:"^4.0"
php artisan filament:install --panels --no-interaction
```
This creates `app/Providers/Filament/AdminPanelProvider.php` and registers it in `bootstrap/providers.php` automatically — verify both happened, then edit the generated provider to match:

`app/Providers/Filament/AdminPanelProvider.php`:
```php
<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('/')
            ->domain(config('app.env') === 'production' ? 'admin.feedlink.tech' : null)
            ->login()
            ->discoverResources(in: app_path('Modules/Admin/Filament/Resources'), for: 'App\\Modules\\Admin\\Filament\\Resources')
            ->discoverWidgets(in: app_path('Modules/Admin/Filament/Widgets'), for: 'App\\Modules\\Admin\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AuthenticateSession::class,
            ]);
    }
}
```

In `app/Models/User.php`, add the import and interface:
```php
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
```
Change the class declaration to implement it: `class User extends Authenticatable implements MustVerifyEmail, FilamentUser`

Add the method:
```php
public function canAccessPanel(Panel $panel): bool
{
    return $this->hasRole('admin');
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Admin/AdminPanelAccessTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock app/Providers/Filament/AdminPanelProvider.php bootstrap/providers.php app/Models/User.php tests/Feature/Admin/AdminPanelAccessTest.php
git commit -m "feat: install Filament and add admin-gated panel"
```

---

### Task 2: `UserResource` — list/view users, disable/enable action, report history

**Files:**
- Create: `app/Modules/Admin/Filament/Resources/UserResource.php`
- Create: `app/Modules/Admin/Filament/Resources/UserResource/Pages/ListUsers.php`
- Create: `app/Modules/Admin/Filament/Resources/UserResource/Pages/ViewUser.php`
- Create: `app/Modules/Admin/Filament/Resources/UserResource/RelationManagers/ReportsRelationManager.php`
- Test: `tests/Feature/Admin/UserResourceTest.php`

**Interfaces:**
- Consumes: `UserModerationService::disable()`/`enable()` (from report-auto-disable plan, already implemented).
- Produces: `/admin/users` list page with a working disable/enable row action.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Admin\Filament\Resources\UserResource\Pages\ListUsers;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        return $admin;
    }

    public function test_admin_can_see_users_list()
    {
        $this->actingAsAdmin();
        $donor = User::factory()->create();
        $donor->assignRole('donor');

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$donor]);
    }

    public function test_disable_action_disables_the_user()
    {
        $this->actingAsAdmin();
        $donor = User::factory()->create(['is_active' => true]);
        $donor->assignRole('donor');

        Livewire::test(ListUsers::class)
            ->callTableAction('disable', $donor, data: ['reason' => 'manual test disable']);

        $donor->refresh();
        $this->assertFalse((bool) $donor->is_active);
        $this->assertEquals('manual test disable', $donor->disabled_reason);
    }

    public function test_enable_action_reenables_the_user()
    {
        $this->actingAsAdmin();
        $donor = User::factory()->create(['is_active' => false, 'disabled_at' => now(), 'disabled_reason' => 'x']);
        $donor->assignRole('donor');

        Livewire::test(ListUsers::class)
            ->callTableAction('enable', $donor);

        $donor->refresh();
        $this->assertTrue((bool) $donor->is_active);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/UserResourceTest.php`
Expected: FAIL — `UserResource`/`ListUsers` don't exist.

- [ ] **Step 3: Write the resource**

`app/Modules/Admin/Filament/Resources/UserResource.php`:
```php
<?php

namespace App\Modules\Admin\Filament\Resources;

use App\Models\User;
use App\Modules\Admin\Filament\Resources\UserResource\Pages\ListUsers;
use App\Modules\Admin\Filament\Resources\UserResource\Pages\ViewUser;
use App\Modules\Admin\Filament\Resources\UserResource\RelationManagers\ReportsRelationManager;
use App\Modules\Admin\Services\UserModerationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('roles.name')->label('Role')->badge(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('disabled_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('roles')->relationship('roles', 'name'),
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                Action::make('disable')
                    ->visible(fn (User $record) => $record->is_active)
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')->required(),
                    ])
                    ->action(function (User $record, array $data) {
                        app(UserModerationService::class)->disable($record, $data['reason']);
                    }),
                Action::make('enable')
                    ->visible(fn (User $record) => ! $record->is_active)
                    ->requiresConfirmation()
                    ->action(fn (User $record) => app(UserModerationService::class)->enable($record)),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ReportsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
```

`app/Modules/Admin/Filament/Resources/UserResource/Pages/ListUsers.php`:
```php
<?php

namespace App\Modules\Admin\Filament\Resources\UserResource\Pages;

use App\Modules\Admin\Filament\Resources\UserResource;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;
}
```

`app/Modules/Admin/Filament/Resources/UserResource/Pages/ViewUser.php`:
```php
<?php

namespace App\Modules\Admin\Filament\Resources\UserResource\Pages;

use App\Modules\Admin\Filament\Resources\UserResource;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;
}
```

`app/Modules/Admin/Filament/Resources/UserResource/RelationManagers/ReportsRelationManager.php`:
```php
<?php

namespace App\Modules\Admin\Filament\Resources\UserResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'reportsAgainstMe';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category'),
                TextColumn::make('status'),
                TextColumn::make('description')->limit(50),
                TextColumn::make('created_at')->dateTime(),
            ]);
    }
}
```

Add the corresponding relationship to `app/Modules/Admin/Entities/Report.php`'s referenced side — in `app/Models/User.php`, add:
```php
public function reportsAgainstMe(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(\App\Modules\Admin\Entities\Report::class, 'reported_user_id');
}
```

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Admin/UserResourceTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Admin/Filament/Resources/UserResource.php app/Modules/Admin/Filament/Resources/UserResource app/Models/User.php tests/Feature/Admin/UserResourceTest.php
git commit -m "feat: add Filament UserResource with disable/enable actions"
```

---

### Task 3: `ReportResource` — review queue

**Files:**
- Create: `app/Modules/Admin/Filament/Resources/ReportResource.php`
- Create: `app/Modules/Admin/Filament/Resources/ReportResource/Pages/ListReports.php`
- Test: `tests/Feature/Admin/ReportResourceTest.php`

**Interfaces:**
- Consumes: `App\Modules\Admin\Entities\Report` (existing), `UserModerationService::disable()` (existing).
- Produces: `/admin/reports` list with status/category filters and mark-reviewed/dismissed/disable-user actions.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Admin\Entities\Report;
use App\Modules\Admin\Filament\Resources\ReportResource\Pages\ListReports;
use Livewire\Livewire;
use Tests\TestCase;

class ReportResourceTest extends TestCase
{
    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        return $admin;
    }

    public function test_admin_can_see_reports_list()
    {
        $this->actingAsAdmin();
        $report = Report::create([
            'reporter_id' => User::factory()->create()->id,
            'reported_user_id' => User::factory()->create()->id,
            'category' => 'no_show',
            'description' => 'test',
            'status' => 'pending',
        ]);

        Livewire::test(ListReports::class)
            ->assertCanSeeTableRecords([$report]);
    }

    public function test_dismiss_action_updates_status()
    {
        $this->actingAsAdmin();
        $report = Report::create([
            'reporter_id' => User::factory()->create()->id,
            'reported_user_id' => User::factory()->create()->id,
            'category' => 'other',
            'description' => 'test',
            'status' => 'pending',
        ]);

        Livewire::test(ListReports::class)
            ->callTableAction('dismiss', $report);

        $report->refresh();
        $this->assertEquals('dismissed', $report->status);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/ReportResourceTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the resource**

`app/Modules/Admin/Filament/Resources/ReportResource.php`:
```php
<?php

namespace App\Modules\Admin\Filament\Resources;

use App\Modules\Admin\Entities\Report;
use App\Modules\Admin\Filament\Resources\ReportResource\Pages\ListReports;
use App\Modules\Admin\Services\UserModerationService;
use App\Modules\Core\Enums\ReportCategoryEnum;
use App\Modules\Core\Enums\ReportStatusEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reporter.name')->label('Reporter'),
                TextColumn::make('reportedUser.name')->label('Reported'),
                TextColumn::make('category')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('description')->limit(50),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine(ReportStatusEnum::getAllValues(), ReportStatusEnum::getAllValues())),
                SelectFilter::make('category')->options(array_combine(ReportCategoryEnum::getAllValues(), ReportCategoryEnum::getAllValues())),
            ])
            ->recordActions([
                Action::make('review')
                    ->visible(fn (Report $record) => $record->status === 'pending')
                    ->action(fn (Report $record) => $record->update(['status' => 'reviewed'])),
                Action::make('dismiss')
                    ->visible(fn (Report $record) => $record->status !== 'dismissed')
                    ->action(fn (Report $record) => $record->update(['status' => 'dismissed'])),
                Action::make('disable_reported_user')
                    ->label('Disable reported user')
                    ->requiresConfirmation()
                    ->action(function (Report $record) {
                        app(UserModerationService::class)->disable(
                            $record->reportedUser,
                            "manual: actioned from report {$record->id}"
                        );
                        $record->update(['status' => 'actioned']);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReports::route('/'),
        ];
    }
}
```

`app/Modules/Admin/Filament/Resources/ReportResource/Pages/ListReports.php`:
```php
<?php

namespace App\Modules\Admin\Filament\Resources\ReportResource\Pages;

use App\Modules\Admin\Filament\Resources\ReportResource;
use Filament\Resources\Pages\ListRecords;

class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;
}
```

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Admin/ReportResourceTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Admin/Filament/Resources/ReportResource.php app/Modules/Admin/Filament/Resources/ReportResource tests/Feature/Admin/ReportResourceTest.php
git commit -m "feat: add Filament ReportResource with review/dismiss/disable actions"
```

---

### Task 4: `FoodListingResource` with claims relation manager and force-cancel

**Files:**
- Create: `app/Modules/Admin/Filament/Resources/FoodListingResource.php`
- Create: `app/Modules/Admin/Filament/Resources/FoodListingResource/Pages/ListFoodListings.php`
- Create: `app/Modules/Admin/Filament/Resources/FoodListingResource/RelationManagers/ClaimsRelationManager.php`
- Test: `tests/Feature/Admin/FoodListingResourceTest.php`

**Interfaces:**
- Consumes: `App\Modules\FoodListings\Entities\FoodListing`, `ListingClaim` (existing, read-only here).
- Produces: `/admin/food-listings` browse page, force-cancel row action.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Admin\Filament\Resources\FoodListingResource\Pages\ListFoodListings;
use App\Modules\FoodListings\Entities\FoodListing;
use Livewire\Livewire;
use Tests\TestCase;

class FoodListingResourceTest extends TestCase
{
    public function test_admin_can_see_listings_and_force_cancel()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $listing = FoodListing::factory()->create(['status' => 'active']);

        Livewire::test(ListFoodListings::class)
            ->assertCanSeeTableRecords([$listing])
            ->callTableAction('force_cancel', $listing);

        $listing->refresh();
        $this->assertEquals('cancelled', $listing->status);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/FoodListingResourceTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the resource**

`app/Modules/Admin/Filament/Resources/FoodListingResource.php`:
```php
<?php

namespace App\Modules\Admin\Filament\Resources;

use App\Modules\Admin\Filament\Resources\FoodListingResource\Pages\ListFoodListings;
use App\Modules\Admin\Filament\Resources\FoodListingResource\RelationManagers\ClaimsRelationManager;
use App\Modules\Core\Enums\ListingStatusEnum;
use App\Modules\FoodListings\Entities\FoodListing;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FoodListingResource extends Resource
{
    protected static ?string $model = FoodListing::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('donor.name')->label('Donor'),
                TextColumn::make('status')->badge(),
                TextColumn::make('expires_at')->dateTime(),
                TextColumn::make('pickup_before')->dateTime(),
                TextColumn::make('address')->limit(40),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine(ListingStatusEnum::getAllValues(), ListingStatusEnum::getAllValues())),
            ])
            ->recordActions([
                Action::make('force_cancel')
                    ->visible(fn (FoodListing $record) => ! in_array($record->status, ['cancelled', 'completed']))
                    ->requiresConfirmation()
                    ->action(fn (FoodListing $record) => $record->update([
                        'status' => ListingStatusEnum::CANCELLED->value,
                        'cancelled_by' => $record->donor_id,
                    ])),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ClaimsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFoodListings::route('/'),
        ];
    }
}
```

`app/Modules/Admin/Filament/Resources/FoodListingResource/Pages/ListFoodListings.php`:
```php
<?php

namespace App\Modules\Admin\Filament\Resources\FoodListingResource\Pages;

use App\Modules\Admin\Filament\Resources\FoodListingResource;
use Filament\Resources\Pages\ListRecords;

class ListFoodListings extends ListRecords
{
    protected static string $resource = FoodListingResource::class;
}
```

`app/Modules/Admin/Filament/Resources/FoodListingResource/RelationManagers/ClaimsRelationManager.php`:
```php
<?php

namespace App\Modules\Admin\Filament\Resources\FoodListingResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClaimsRelationManager extends RelationManager
{
    protected static string $relationship = 'claims';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recipient.name')->label('Recipient'),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime(),
            ]);
    }
}
```

**Note:** this relation manager assumes `FoodListing` has a `claims()` relationship. Check `app/Modules/FoodListings/Entities/FoodListing.php` — if it doesn't already exist, add it:
```php
public function claims(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(\App\Modules\FoodListings\Entities\ListingClaim::class);
}
```

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Admin/FoodListingResourceTest.php`
Expected: PASS (1 test)

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Admin/Filament/Resources/FoodListingResource.php app/Modules/Admin/Filament/Resources/FoodListingResource app/Modules/FoodListings/Entities/FoodListing.php tests/Feature/Admin/FoodListingResourceTest.php
git commit -m "feat: add Filament FoodListingResource with claims and force-cancel"
```

---

### Task 5: `IllnessClaimResource` + `DonorWarningResource`

**Files:**
- Create: `app/Modules/Admin/Filament/Resources/IllnessClaimResource.php`
- Create: `app/Modules/Admin/Filament/Resources/IllnessClaimResource/Pages/ListIllnessClaims.php`
- Create: `app/Modules/Admin/Filament/Resources/DonorWarningResource.php`
- Create: `app/Modules/Admin/Filament/Resources/DonorWarningResource/Pages/ListDonorWarnings.php`
- Test: `tests/Feature/Admin/FoodSafetyResourcesTest.php`

**Interfaces:**
- Consumes: `App\Modules\FoodSafety\Entities\IllnessClaim`, `App\Modules\FoodSafety\Entities\DonorWarning` (existing, currently no admin UI at all).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Admin\Filament\Resources\DonorWarningResource\Pages\ListDonorWarnings;
use App\Modules\Admin\Filament\Resources\IllnessClaimResource\Pages\ListIllnessClaims;
use App\Modules\FoodSafety\Entities\DonorWarning;
use App\Modules\FoodSafety\Entities\IllnessClaim;
use Livewire\Livewire;
use Tests\TestCase;

class FoodSafetyResourcesTest extends TestCase
{
    public function test_admin_can_see_illness_claims()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $claim = IllnessClaim::create([
            'reporter_id' => User::factory()->create()->id,
            'donor_id' => User::factory()->create()->id,
            'description' => 'Got sick',
            'reported_at' => now(),
            'status' => 'pending',
        ]);

        Livewire::test(ListIllnessClaims::class)
            ->assertCanSeeTableRecords([$claim]);
    }

    public function test_admin_can_see_donor_warnings()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $warning = DonorWarning::create([
            'donor_id' => User::factory()->create()->id,
            'claim_count' => 2,
            'warning_active' => true,
        ]);

        Livewire::test(ListDonorWarnings::class)
            ->assertCanSeeTableRecords([$warning]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/FoodSafetyResourcesTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Write the resources**

`app/Modules/Admin/Filament/Resources/IllnessClaimResource.php`:
```php
<?php

namespace App\Modules\Admin\Filament\Resources;

use App\Modules\Admin\Filament\Resources\IllnessClaimResource\Pages\ListIllnessClaims;
use App\Modules\FoodSafety\Entities\IllnessClaim;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IllnessClaimResource extends Resource
{
    protected static ?string $model = IllnessClaim::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reporter.name')->label('Reporter'),
                TextColumn::make('donor.name')->label('Donor'),
                TextColumn::make('description')->limit(50),
                TextColumn::make('status')->badge(),
                TextColumn::make('reported_at')->dateTime(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIllnessClaims::route('/'),
        ];
    }
}
```

`app/Modules/Admin/Filament/Resources/IllnessClaimResource/Pages/ListIllnessClaims.php`:
```php
<?php

namespace App\Modules\Admin\Filament\Resources\IllnessClaimResource\Pages;

use App\Modules\Admin\Filament\Resources\IllnessClaimResource;
use Filament\Resources\Pages\ListRecords;

class ListIllnessClaims extends ListRecords
{
    protected static string $resource = IllnessClaimResource::class;
}
```

`app/Modules/Admin/Filament/Resources/DonorWarningResource.php`:
```php
<?php

namespace App\Modules\Admin\Filament\Resources;

use App\Modules\Admin\Filament\Resources\DonorWarningResource\Pages\ListDonorWarnings;
use App\Modules\FoodSafety\Entities\DonorWarning;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DonorWarningResource extends Resource
{
    protected static ?string $model = DonorWarning::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('donor.name')->label('Donor'),
                TextColumn::make('claim_count'),
                IconColumn::make('warning_active')->boolean(),
                TextColumn::make('last_claim_at')->dateTime(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDonorWarnings::route('/'),
        ];
    }
}
```

`app/Modules/Admin/Filament/Resources/DonorWarningResource/Pages/ListDonorWarnings.php`:
```php
<?php

namespace App\Modules\Admin\Filament\Resources\DonorWarningResource\Pages;

use App\Modules\Admin\Filament\Resources\DonorWarningResource;
use Filament\Resources\Pages\ListRecords;

class ListDonorWarnings extends ListRecords
{
    protected static string $resource = DonorWarningResource::class;
}
```

**Note:** these assume `IllnessClaim` has `reporter()`/`donor()` relations (confirmed to already exist per the earlier codebase investigation) and `DonorWarning` has a `donor()` relation — if `donor()` is missing on `DonorWarning`, add it: `public function donor(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(\App\Models\User::class, 'donor_id'); }`.

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Admin/FoodSafetyResourcesTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Admin/Filament/Resources/IllnessClaimResource.php app/Modules/Admin/Filament/Resources/IllnessClaimResource app/Modules/Admin/Filament/Resources/DonorWarningResource.php app/Modules/Admin/Filament/Resources/DonorWarningResource tests/Feature/Admin/FoodSafetyResourcesTest.php
git commit -m "feat: add Filament IllnessClaimResource and DonorWarningResource"
```

---

### Task 6: Dashboard stats widgets

**Files:**
- Create: `app/Modules/Admin/Filament/Widgets/OverviewStatsWidget.php`
- Test: `tests/Feature/Admin/OverviewStatsWidgetTest.php`

**Interfaces:**
- Consumes: `User`, `FoodListing`, `ListingClaim` (count queries), `Report` (count query).
- Produces: 4 stat cards on the panel's default dashboard page (auto-discovered via `discoverWidgets` from Task 1).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Admin\Entities\Report;
use App\Modules\Admin\Filament\Widgets\OverviewStatsWidget;
use App\Modules\FoodListings\Entities\FoodListing;
use Livewire\Livewire;
use Tests\TestCase;

class OverviewStatsWidgetTest extends TestCase
{
    public function test_widget_renders_correct_counts()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        User::factory()->count(2)->create();
        FoodListing::factory()->create(['status' => 'active']);
        Report::create([
            'reporter_id' => User::factory()->create()->id,
            'reported_user_id' => User::factory()->create()->id,
            'category' => 'other',
            'description' => 'x',
            'status' => 'pending',
        ]);

        Livewire::test(OverviewStatsWidget::class)
            ->assertSuccessful();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/OverviewStatsWidgetTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the widget**

`app/Modules/Admin/Filament/Widgets/OverviewStatsWidget.php`:
```php
<?php

namespace App\Modules\Admin\Filament\Widgets;

use App\Models\User;
use App\Modules\Admin\Entities\Report;
use App\Modules\FoodListings\Entities\FoodListing;
use App\Modules\FoodListings\Entities\ListingClaim;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverviewStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total users', User::query()->count()),
            Stat::make('Active listings', FoodListing::query()->where('status', 'active')->count()),
            Stat::make('Claims today', ListingClaim::query()->whereDate('created_at', today())->count()),
            Stat::make('Pending reports', Report::query()->where('status', 'pending')->count()),
        ];
    }
}
```

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Admin/OverviewStatsWidgetTest.php`
Expected: PASS (1 test)

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Admin/Filament/Widgets/OverviewStatsWidget.php tests/Feature/Admin/OverviewStatsWidgetTest.php
git commit -m "feat: add admin dashboard overview stats widget"
```

---

### Task 7: CI pipeline — publish Filament assets on deploy

**Files:**
- Modify: `.github/workflows/laravel.yml`

**Interfaces:**
- Produces: Filament's CSS/JS assets get published/cached as part of every deploy, same as the rest of the app.

- [ ] **Step 1: Add the asset step**

In `.github/workflows/laravel.yml`, immediately after the existing `composer install` step and before the rsync/deploy step, add:
```yaml
      - name: Publish Filament assets
        run: php artisan filament:assets --no-interaction
```
(Match this repo's existing step syntax exactly — check the surrounding steps in the same file for the correct indentation/`run:` style before adding.)

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/laravel.yml
git commit -m "ci: publish Filament assets on deploy"
```

---

### Task 8: nginx server block for `admin.feedlink.tech` + DNS note

**Files:**
- Create: `docs/deployment/admin-nginx.conf` (reference copy kept in the repo; the live file lives on the server at `/etc/nginx/sites-available/admin.feedlink.tech`)

**Interfaces:**
- Produces: a working nginx server block routing `admin.feedlink.tech` to the same `public/` document root and PHP-FPM socket the main `feedlink.tech` site already uses.

- [ ] **Step 1: Write the nginx config**

`docs/deployment/admin-nginx.conf`:
```nginx
server {
    listen 80;
    server_name admin.feedlink.tech;
    root /var/www/feedlink-api/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

**This step requires manual server action (not something an agent should do unattended):**
1. Copy this file to `/etc/nginx/sites-available/admin.feedlink.tech` on the droplet.
2. `ln -s /etc/nginx/sites-available/admin.feedlink.tech /etc/nginx/sites-enabled/`
3. `nginx -t` to validate, then `systemctl reload nginx`.
4. Add DNS: an A record for `admin.feedlink.tech` pointing at the droplet's IP, wherever `feedlink.tech`'s DNS is managed (e.g. the registrar or Cloudflare dashboard) — this is outside server access and must be done by whoever controls that DNS account.
5. Once DNS resolves, run `certbot --nginx -d admin.feedlink.tech` on the server to provision HTTPS (matches however the main site's TLS was set up — check `/etc/nginx/sites-available/feedlink.tech` for the existing pattern first).

- [ ] **Step 2: Commit the reference config**

```bash
git add docs/deployment/admin-nginx.conf
git commit -m "docs: add reference nginx config for admin.feedlink.tech"
```
