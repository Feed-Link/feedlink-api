# Report / Auto-Disable Feature Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. **MANDATORY:** Load and follow the `modular-monolithic-pattern` skill before writing any PHP code in this plan.

**Goal:** Let donors/recipients report each other on a specific listing/claim; 3 reports against the same user within 30 days auto-disables the account (cancels their active listings/claims, emails admin); admin can also disable/enable manually.

**Architecture:** Follows this repo's Controller → Service → Repository → Model pattern, all new code lives in `app/Modules/Admin/` (the existing near-empty module meant for this). New `reports` table + two new columns on `users`. `EnsureAccountActive` middleware blocks disabled users on every authenticated route; the login flow also checks `is_active` explicitly so a disabled user gets a clear error at login rather than a generic one.

**Tech Stack:** Laravel 12, PHPUnit (not Pest), PostgreSQL, existing `App\Models\User` (Spatie Permission roles), existing `BaseModel`/`BaseRepository`/`BaseRequest`/`HasApiResponse` base classes.

## Global Constraints

- Never run `php artisan migrate:fresh` or use `RefreshDatabase` in tests — this repo's test suite runs against the live production database using `DatabaseTransactions` (already configured in `tests/TestCase.php`), which rolls back each test.
- New migrations ARE safe to run locally via `php artisan migrate` — they're additive only (new table, new nullable columns) and Laravel's migration tracking makes this idempotent when CI applies the same migration again on deploy.
- Every enum uses TitleCase keys (`enum X: string { case FooBar = 'foo_bar'; }` per this repo's convention is actually UPPER_SNAKE case names as seen in existing enums — match `ListingStatusEnum`/`ClaimStatusEnum` exactly: `case ACTIVE = 'active';` style, not TitleCase, despite what CLAUDE.md's generic rule says — existing code wins).
- Every controller method wraps its body in try/catch and returns `$this->handleException($exception)` on catch.
- Every Eloquent write goes through a Repository extending `BaseRepository`, never called directly from a Controller.
- Whenever a route is added, `API_DOC.md` must be updated in the same task (Task 8).
- Run `vendor/bin/pint --dirty --format agent` after editing PHP files, before committing.

---

### Task 1: Migrations — `reports` table + `users` disabled columns

**Files:**
- Create: `database/migrations/2026_07_23_000001_create_reports_table.php`
- Create: `database/migrations/2026_07_23_000002_add_disabled_fields_to_users_table.php`
- Test: `tests/Feature/Admin/ReportsMigrationTest.php`

**Interfaces:**
- Produces: `reports` table (columns: `id`, `reporter_id`, `reported_user_id`, `food_listing_id`, `claim_id`, `category`, `description`, `status`, `created_at`, `updated_at`); `users.disabled_at`, `users.disabled_reason`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReportsMigrationTest extends TestCase
{
    public function test_reports_table_has_expected_columns()
    {
        $this->assertTrue(Schema::hasTable('reports'));
        $this->assertTrue(Schema::hasColumns('reports', [
            'id', 'reporter_id', 'reported_user_id', 'food_listing_id',
            'claim_id', 'category', 'description', 'status', 'created_at', 'updated_at',
        ]));
    }

    public function test_users_table_has_disabled_columns()
    {
        $this->assertTrue(Schema::hasColumns('users', ['disabled_at', 'disabled_reason']));
    }

    public function test_disabled_columns_default_to_null()
    {
        $user = User::factory()->create();

        $this->assertNull($user->disabled_at);
        $this->assertNull($user->disabled_reason);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/ReportsMigrationTest.php`
Expected: FAIL — `reports` table / columns don't exist yet.

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_07_23_000001_create_reports_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reporter_id');
            $table->uuid('reported_user_id');
            $table->uuid('food_listing_id')->nullable();
            $table->uuid('claim_id')->nullable();
            $table->enum('category', ['no_show', 'food_quality_safety', 'rude_behavior', 'fake_listing', 'other']);
            $table->text('description');
            $table->enum('status', ['pending', 'reviewed', 'dismissed', 'actioned'])->default('pending');
            $table->timestamps();

            $table->foreign('reporter_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reported_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('food_listing_id')->references('id')->on('food_listings')->onDelete('cascade');
            $table->foreign('claim_id')->references('id')->on('listing_claims')->onDelete('cascade');
            $table->unique(['reporter_id', 'food_listing_id', 'claim_id'], 'reports_unique_reporter_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
```

`database/migrations/2026_07_23_000002_add_disabled_fields_to_users_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('disabled_at')->nullable()->after('is_active');
            $table->string('disabled_reason')->nullable()->after('disabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['disabled_at', 'disabled_reason']);
        });
    }
};
```

Run: `php artisan migrate`
Expected output: both new migrations listed as `DONE`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Admin/ReportsMigrationTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_23_000001_create_reports_table.php database/migrations/2026_07_23_000002_add_disabled_fields_to_users_table.php tests/Feature/Admin/ReportsMigrationTest.php
git commit -m "feat: add reports table and user disabled columns"
```

---

### Task 2: Enums — `ReportCategoryEnum`, `ReportStatusEnum`

**Files:**
- Create: `app/Modules/Core/Enums/ReportCategoryEnum.php`
- Create: `app/Modules/Core/Enums/ReportStatusEnum.php`
- Test: `tests/Unit/Enums/ReportEnumsTest.php`

**Interfaces:**
- Produces: `ReportCategoryEnum::getAllValues()` → `['no_show', 'food_quality_safety', 'rude_behavior', 'fake_listing', 'other']`; `ReportStatusEnum::getAllValues()` → `['pending', 'reviewed', 'dismissed', 'actioned']`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Enums;

use App\Modules\Core\Enums\ReportCategoryEnum;
use App\Modules\Core\Enums\ReportStatusEnum;
use PHPUnit\Framework\TestCase;

class ReportEnumsTest extends TestCase
{
    public function test_report_category_values()
    {
        $this->assertEquals(
            ['no_show', 'food_quality_safety', 'rude_behavior', 'fake_listing', 'other'],
            ReportCategoryEnum::getAllValues()
        );
    }

    public function test_report_status_values()
    {
        $this->assertEquals(
            ['pending', 'reviewed', 'dismissed', 'actioned'],
            ReportStatusEnum::getAllValues()
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Unit/Enums/ReportEnumsTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the enums**

`app/Modules/Core/Enums/ReportCategoryEnum.php`:
```php
<?php

namespace App\Modules\Core\Enums;

enum ReportCategoryEnum: string
{
    case NO_SHOW = 'no_show';
    case FOOD_QUALITY_SAFETY = 'food_quality_safety';
    case RUDE_BEHAVIOR = 'rude_behavior';
    case FAKE_LISTING = 'fake_listing';
    case OTHER = 'other';

    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

`app/Modules/Core/Enums/ReportStatusEnum.php`:
```php
<?php

namespace App\Modules\Core\Enums;

enum ReportStatusEnum: string
{
    case PENDING = 'pending';
    case REVIEWED = 'reviewed';
    case DISMISSED = 'dismissed';
    case ACTIONED = 'actioned';

    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Unit/Enums/ReportEnumsTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Core/Enums/ReportCategoryEnum.php app/Modules/Core/Enums/ReportStatusEnum.php tests/Unit/Enums/ReportEnumsTest.php
git commit -m "feat: add report category and status enums"
```

---

### Task 3: `Report` entity + `ReportRepository`

**Files:**
- Create: `app/Modules/Admin/Entities/Report.php`
- Create: `app/Modules/Admin/Repositories/ReportRepository.php`
- Test: `tests/Feature/Admin/ReportRepositoryTest.php`

**Interfaces:**
- Consumes: `App\Models\User`, `App\Modules\FoodListings\Entities\FoodListing`, `App\Modules\FoodListings\Entities\ListingClaim`, `BaseModel`, `BaseRepository` (all existing).
- Produces: `Report` model (fillable: `reporter_id`, `reported_user_id`, `food_listing_id`, `claim_id`, `category`, `description`, `status`). `ReportRepository::existsForReporterAndTarget(string $reporterId, ?string $foodListingId, ?string $claimId): bool`. `ReportRepository::countRecentAgainst(string $reportedUserId, \Illuminate\Support\Carbon $since): int`. Plus inherited `store()`, `fetchBy()`, `fetchAll()`, `update()`, `delete()` from `BaseRepository`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Admin\Entities\Report;
use App\Modules\Admin\Repositories\ReportRepository;
use Tests\TestCase;

class ReportRepositoryTest extends TestCase
{
    public function test_store_creates_a_report()
    {
        $reporter = User::factory()->create();
        $reported = User::factory()->create();
        $repository = app(ReportRepository::class);

        $report = $repository->store([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reported->id,
            'category' => 'no_show',
            'description' => 'Did not show up',
            'status' => 'pending',
        ]);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'pending']);
    }

    public function test_exists_for_reporter_and_target_detects_duplicate()
    {
        $reporter = User::factory()->create();
        $reported = User::factory()->create();
        $repository = app(ReportRepository::class);

        $repository->store([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reported->id,
            'claim_id' => $claimId = (string) \Illuminate\Support\Str::uuid(),
            'category' => 'no_show',
            'description' => 'first',
            'status' => 'pending',
        ]);

        $this->assertTrue($repository->existsForReporterAndTarget($reporter->id, null, $claimId));
        $this->assertFalse($repository->existsForReporterAndTarget($reporter->id, null, (string) \Illuminate\Support\Str::uuid()));
    }

    public function test_count_recent_against_counts_within_window()
    {
        $reported = User::factory()->create();
        $repository = app(ReportRepository::class);

        Report::create([
            'reporter_id' => User::factory()->create()->id,
            'reported_user_id' => $reported->id,
            'category' => 'other',
            'description' => 'old one',
            'status' => 'pending',
            'created_at' => now()->subDays(40),
        ]);

        Report::create([
            'reporter_id' => User::factory()->create()->id,
            'reported_user_id' => $reported->id,
            'category' => 'other',
            'description' => 'recent one',
            'status' => 'pending',
            'created_at' => now()->subDays(5),
        ]);

        $this->assertEquals(1, $repository->countRecentAgainst($reported->id, now()->subDays(30)));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/ReportRepositoryTest.php`
Expected: FAIL — `Report`/`ReportRepository` classes not found.

- [ ] **Step 3: Write the entity and repository**

`app/Modules/Admin/Entities/Report.php`:
```php
<?php

namespace App\Modules\Admin\Entities;

use App\Models\User;
use App\Modules\Core\Entities\BaseModel;
use App\Modules\FoodListings\Entities\FoodListing;
use App\Modules\FoodListings\Entities\ListingClaim;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends BaseModel
{
    protected $fillable = [
        'reporter_id',
        'reported_user_id',
        'food_listing_id',
        'claim_id',
        'category',
        'description',
        'status',
    ];

    public const SEARCHABLE = ['description'];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function foodListing(): BelongsTo
    {
        return $this->belongsTo(FoodListing::class, 'food_listing_id');
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ListingClaim::class, 'claim_id');
    }
}
```

`app/Modules/Admin/Repositories/ReportRepository.php`:
```php
<?php

namespace App\Modules\Admin\Repositories;

use App\Modules\Admin\Entities\Report;
use App\Modules\Core\Repositories\BaseRepository;
use Illuminate\Support\Carbon;

class ReportRepository extends BaseRepository
{
    public function __construct(protected Report $report)
    {
        $this->model = $report;
        parent::__construct();
    }

    public function existsForReporterAndTarget(string $reporterId, ?string $foodListingId, ?string $claimId): bool
    {
        return $this->model::query()
            ->where('reporter_id', $reporterId)
            ->when($foodListingId, fn ($query) => $query->where('food_listing_id', $foodListingId))
            ->when($claimId, fn ($query) => $query->where('claim_id', $claimId))
            ->exists();
    }

    public function countRecentAgainst(string $reportedUserId, Carbon $since): int
    {
        return $this->model::query()
            ->where('reported_user_id', $reportedUserId)
            ->where('created_at', '>=', $since)
            ->count();
    }
}
```

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Admin/ReportRepositoryTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Admin/Entities/Report.php app/Modules/Admin/Repositories/ReportRepository.php tests/Feature/Admin/ReportRepositoryTest.php
git commit -m "feat: add Report entity and ReportRepository"
```

---

### Task 4: Cancellation query methods on existing repositories

**Files:**
- Modify: `app/Modules/FoodListings/Repositories/FoodListingRepository.php`
- Modify: `app/Modules/FoodListings/Repositories/ListingClaimRepository.php`
- Test: `tests/Feature/Admin/CancellationQueriesTest.php`

**Interfaces:**
- Produces: `FoodListingRepository::fetchCancellableByDonor(string $donorId): \Illuminate\Database\Eloquent\Collection` (listings with status `active` or `claimed`). `ListingClaimRepository::fetchPendingByRecipient(string $recipientId): \Illuminate\Database\Eloquent\Collection` (claims with status `pending`).
- Consumes: `ListingStatusEnum::ACTIVE`, `ListingStatusEnum::CLAIMED`, `ClaimStatusEnum::PENDING` (existing enums).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\FoodListings\Entities\FoodListing;
use App\Modules\FoodListings\Entities\ListingClaim;
use App\Modules\FoodListings\Repositories\FoodListingRepository;
use App\Modules\FoodListings\Repositories\ListingClaimRepository;
use Tests\TestCase;

class CancellationQueriesTest extends TestCase
{
    public function test_fetch_cancellable_by_donor_returns_active_and_claimed_only()
    {
        $donor = User::factory()->create();
        $active = FoodListing::factory()->create(['donor_id' => $donor->id, 'status' => 'active']);
        $claimed = FoodListing::factory()->create(['donor_id' => $donor->id, 'status' => 'claimed']);
        FoodListing::factory()->create(['donor_id' => $donor->id, 'status' => 'completed']);

        $result = app(FoodListingRepository::class)->fetchCancellableByDonor($donor->id);

        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing([$active->id, $claimed->id], $result->pluck('id')->all());
    }

    public function test_fetch_pending_by_recipient_returns_pending_only()
    {
        $recipient = User::factory()->create();
        $listing = FoodListing::factory()->create();

        $pending = ListingClaim::create([
            'food_listing_id' => $listing->id,
            'recipient_id' => $recipient->id,
            'claim_user_id' => $recipient->id,
            'status' => 'pending',
        ]);

        ListingClaim::create([
            'food_listing_id' => $listing->id,
            'recipient_id' => $recipient->id,
            'claim_user_id' => $recipient->id,
            'status' => 'rejected',
        ]);

        $result = app(ListingClaimRepository::class)->fetchPendingByRecipient($recipient->id);

        $this->assertCount(1, $result);
        $this->assertEquals($pending->id, $result->first()->id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/CancellationQueriesTest.php`
Expected: FAIL — methods don't exist.

- [ ] **Step 3: Add the repository methods**

In `app/Modules/FoodListings/Repositories/FoodListingRepository.php`, add (keep existing methods untouched, add this one alongside them, add the two imports at the top if not already present):

```php
use App\Modules\Core\Enums\ListingStatusEnum;
use Illuminate\Database\Eloquent\Collection;
```

```php
public function fetchCancellableByDonor(string $donorId): Collection
{
    return $this->model::query()
        ->where('donor_id', $donorId)
        ->whereIn('status', [ListingStatusEnum::ACTIVE->value, ListingStatusEnum::CLAIMED->value])
        ->get();
}
```

In `app/Modules/FoodListings/Repositories/ListingClaimRepository.php`, add (add the two imports if not already present):

```php
use App\Modules\Core\Enums\ClaimStatusEnum;
use Illuminate\Database\Eloquent\Collection;
```

```php
public function fetchPendingByRecipient(string $recipientId): Collection
{
    return $this->model::query()
        ->where('recipient_id', $recipientId)
        ->where('status', ClaimStatusEnum::PENDING->value)
        ->get();
}
```

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Admin/CancellationQueriesTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Modules/FoodListings/Repositories/FoodListingRepository.php app/Modules/FoodListings/Repositories/ListingClaimRepository.php tests/Feature/Admin/CancellationQueriesTest.php
git commit -m "feat: add cancellable-listings and pending-claims repository queries"
```

---

### Task 5: Admin notification config + `AccountDisabledMail`

**Files:**
- Create: `config/feedlink.php`
- Modify: `.env.example` (add `ADMIN_NOTIFICATION_EMAILS=`)
- Create: `app/Mail/AccountDisabledMail.php`
- Create: `resources/views/emails/account-disabled.blade.php`
- Test: `tests/Feature/Admin/AccountDisabledMailTest.php`

**Interfaces:**
- Produces: `config('feedlink.admin_emails')` → array of strings (from comma-separated `ADMIN_NOTIFICATION_EMAILS` env var). `AccountDisabledMail(User $user, string $reason)` — Mailable, view `emails.account-disabled`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Mail\AccountDisabledMail;
use App\Models\User;
use Tests\TestCase;

class AccountDisabledMailTest extends TestCase
{
    public function test_mail_renders_user_and_reason()
    {
        $user = User::factory()->create(['name' => 'Test Donor']);
        $mail = new AccountDisabledMail($user, 'auto: 3+ reports in 30 days');

        $rendered = $mail->render();

        $this->assertStringContainsString('Test Donor', $rendered);
        $this->assertStringContainsString('auto: 3+ reports in 30 days', $rendered);
    }

    public function test_config_parses_admin_emails_from_env()
    {
        config(['feedlink.admin_emails' => ['a@feedlink.tech', 'b@feedlink.tech']]);

        $this->assertEquals(['a@feedlink.tech', 'b@feedlink.tech'], config('feedlink.admin_emails'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/AccountDisabledMailTest.php`
Expected: FAIL — `AccountDisabledMail` class not found.

- [ ] **Step 3: Write the config, mailable, and view**

`config/feedlink.php`:
```php
<?php

return [
    'admin_emails' => array_filter(array_map('trim', explode(',', env('ADMIN_NOTIFICATION_EMAILS', '')))),
];
```

Add to `.env.example` (append a new line, don't remove anything existing):
```
ADMIN_NOTIFICATION_EMAILS=
```

`app/Mail/AccountDisabledMail.php`:
```php
<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountDisabledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "FeedLink account disabled: {$this->user->name}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.account-disabled');
    }
}
```

`resources/views/emails/account-disabled.blade.php`:
```blade
<h2>Account disabled</h2>
<p>User: {{ $user->name }} ({{ $user->email }})</p>
<p>Reason: {{ $reason }}</p>
<p>Disabled at: {{ $user->disabled_at }}</p>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Admin/AccountDisabledMailTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add config/feedlink.php .env.example app/Mail/AccountDisabledMail.php resources/views/emails/account-disabled.blade.php tests/Feature/Admin/AccountDisabledMailTest.php
git commit -m "feat: add admin email config and account-disabled mailable"
```

---

### Task 6: `UserModerationService`

**Files:**
- Create: `app/Modules/Admin/Services/UserModerationService.php`
- Test: `tests/Feature/Admin/UserModerationServiceTest.php`

**Interfaces:**
- Consumes: `FoodListingRepository::fetchCancellableByDonor()`, `ListingClaimRepository::fetchPendingByRecipient()` (Task 4), `AccountDisabledMail` (Task 5), `RolesEnum`, `ListingStatusEnum`, `ClaimStatusEnum`.
- Produces: `UserModerationService::disable(User $user, string $reason): User`, `UserModerationService::enable(User $user): User`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Admin\Services\UserModerationService;
use App\Modules\FoodListings\Entities\FoodListing;
use App\Modules\FoodListings\Entities\ListingClaim;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserModerationServiceTest extends TestCase
{
    public function test_disable_sets_flags_and_cancels_donor_listings()
    {
        Mail::fake();
        $donor = User::factory()->create();
        $donor->assignRole('donor');
        $listing = FoodListing::factory()->create(['donor_id' => $donor->id, 'status' => 'active']);

        app(UserModerationService::class)->disable($donor, 'test reason');

        $donor->refresh();
        $listing->refresh();
        $this->assertFalse((bool) $donor->is_active);
        $this->assertNotNull($donor->disabled_at);
        $this->assertEquals('test reason', $donor->disabled_reason);
        $this->assertEquals('cancelled', $listing->status);
    }

    public function test_disable_cancels_recipient_pending_claims()
    {
        Mail::fake();
        $recipient = User::factory()->create();
        $recipient->assignRole('recipient');
        $listing = FoodListing::factory()->create();

        $claim = ListingClaim::create([
            'food_listing_id' => $listing->id,
            'recipient_id' => $recipient->id,
            'claim_user_id' => $recipient->id,
            'status' => 'pending',
        ]);

        app(UserModerationService::class)->disable($recipient, 'test reason');

        $claim->refresh();
        $this->assertEquals('rejected', $claim->status);
    }

    public function test_disable_emails_configured_admins()
    {
        Mail::fake();
        config(['feedlink.admin_emails' => ['admin@feedlink.tech']]);
        $donor = User::factory()->create();
        $donor->assignRole('donor');

        app(UserModerationService::class)->disable($donor, 'test reason');

        Mail::assertSent(\App\Mail\AccountDisabledMail::class, function ($mail) {
            return $mail->hasTo('admin@feedlink.tech');
        });
    }

    public function test_enable_clears_flags()
    {
        $donor = User::factory()->create(['is_active' => false, 'disabled_at' => now(), 'disabled_reason' => 'x']);

        app(UserModerationService::class)->enable($donor);

        $donor->refresh();
        $this->assertTrue((bool) $donor->is_active);
        $this->assertNull($donor->disabled_at);
        $this->assertNull($donor->disabled_reason);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/UserModerationServiceTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the service**

`app/Modules/Admin/Services/UserModerationService.php`:
```php
<?php

namespace App\Modules\Admin\Services;

use App\Mail\AccountDisabledMail;
use App\Models\User;
use App\Modules\Core\Enums\ClaimStatusEnum;
use App\Modules\Core\Enums\ListingStatusEnum;
use App\Modules\Core\Enums\RolesEnum;
use App\Modules\FoodListings\Repositories\FoodListingRepository;
use App\Modules\FoodListings\Repositories\ListingClaimRepository;
use Illuminate\Support\Facades\Mail;

class UserModerationService
{
    public function __construct(
        protected FoodListingRepository $foodListingRepository,
        protected ListingClaimRepository $listingClaimRepository,
    ) {}

    public function disable(User $user, string $reason): User
    {
        $user->update([
            'is_active' => false,
            'disabled_at' => now(),
            'disabled_reason' => $reason,
        ]);

        if ($user->hasRole(RolesEnum::DONOR->value)) {
            $this->cancelActiveListings($user->id);
        }

        if ($user->hasRole(RolesEnum::RECIPIENT->value)) {
            $this->cancelPendingClaims($user->id);
        }

        foreach (config('feedlink.admin_emails', []) as $email) {
            Mail::to($email)->send(new AccountDisabledMail($user, $reason));
        }

        return $user->fresh();
    }

    public function enable(User $user): User
    {
        $user->update([
            'is_active' => true,
            'disabled_at' => null,
            'disabled_reason' => null,
        ]);

        return $user->fresh();
    }

    protected function cancelActiveListings(string $donorId): void
    {
        foreach ($this->foodListingRepository->fetchCancellableByDonor($donorId) as $listing) {
            $listing->update([
                'status' => ListingStatusEnum::CANCELLED->value,
                'cancelled_by' => $donorId,
            ]);
        }
    }

    protected function cancelPendingClaims(string $recipientId): void
    {
        foreach ($this->listingClaimRepository->fetchPendingByRecipient($recipientId) as $claim) {
            $claim->update(['status' => ClaimStatusEnum::REJECTED->value]);
        }
    }
}
```

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Admin/UserModerationServiceTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Admin/Services/UserModerationService.php tests/Feature/Admin/UserModerationServiceTest.php
git commit -m "feat: add UserModerationService for account disable/enable"
```

---

### Task 7: `EnsureAccountActive` middleware + login enforcement

**Files:**
- Create: `app/Modules/Admin/Middleware/EnsureAccountActive.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/api.php:44`, `routes/api.php:69`, `routes/api.php:94`
- Modify: `app/Modules/User/Services/UserService.php:66`
- Test: `tests/Feature/Admin/EnsureAccountActiveTest.php`

**Interfaces:**
- Consumes: `Auth::user()`, existing `is_active` column.
- Produces: middleware alias `account_active`; a disabled user gets HTTP 403 both at login and on any subsequent authenticated request.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class EnsureAccountActiveTest extends TestCase
{
    public function test_disabled_user_blocked_on_authenticated_route()
    {
        $user = User::factory()->create(['is_active' => false]);
        Passport::actingAs($user, ['*']);

        $response = $this->getJson('/api/user/profile');

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'account_disabled');
    }

    public function test_active_user_not_blocked()
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        Passport::actingAs($user, ['*']);

        $response = $this->getJson('/api/user/profile');

        $response->assertStatus(200);
    }

    public function test_disabled_user_blocked_at_login()
    {
        $user = User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => bcrypt('password123'),
            'is_active' => false,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'disabled@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/EnsureAccountActiveTest.php`
Expected: FAIL — no 403s yet, `account_active` middleware doesn't exist.

- [ ] **Step 3: Write the middleware and wire it in**

`app/Modules/Admin/Middleware/EnsureAccountActive.php`:
```php
<?php

namespace App\Modules\Admin\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->is_active) {
            return response()->json([
                'status_code' => Response::HTTP_FORBIDDEN,
                'message' => 'Your account has been disabled. Contact support for details.',
                'error' => 'account_disabled',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
```

In `bootstrap/app.php`, add the import at the top alongside the existing ones:
```php
use App\Modules\Admin\Middleware\EnsureAccountActive;
```

And add `'account_active' => EnsureAccountActive::class,` inside the existing `$middleware->alias([...])` array (`bootstrap/app.php:22-27`), alongside `is_admin`.

In `routes/api.php`, change the three middleware arrays to include the new alias:
- Line 44: `->middleware(['auth:api', 'role:donor|guest'])` → `->middleware(['auth:api', 'account_active', 'role:donor|guest'])`
- Line 69: `->middleware(['auth:api', 'role:recipient'])` → `->middleware(['auth:api', 'account_active', 'role:recipient'])`
- Line 94: `Route::middleware(['auth:api'])` → `Route::middleware(['auth:api', 'account_active'])`

In `app/Modules/User/Services/UserService.php`, inside `login()` right after the `hasVerifiedEmail()` block (after line 65, before token creation at line 67):
```php
if (! $user->is_active) {
    throw new Exception('Your account has been disabled. Contact support for details.', Response::HTTP_FORBIDDEN);
}
```

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Admin/EnsureAccountActiveTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Admin/Middleware/EnsureAccountActive.php bootstrap/app.php routes/api.php app/Modules/User/Services/UserService.php tests/Feature/Admin/EnsureAccountActiveTest.php
git commit -m "feat: block disabled accounts at login and on authenticated routes"
```

---

### Task 8: Report filing endpoint — request, resource, service, controller, route, docs

**Files:**
- Create: `app/Modules/Admin/Requests/StoreReportRequest.php`
- Create: `app/Modules/Admin/Resources/ReportResource.php`
- Create: `app/Modules/Admin/Services/ReportService.php`
- Create: `app/Modules/Admin/Controllers/ReportController.php`
- Modify: `routes/api.php:104` (inside the shared `auth:api` group from Task 7)
- Modify: `API_DOC.md`
- Test: `tests/Feature/Admin/ReportFeatureTest.php`

**Interfaces:**
- Consumes: `ReportRepository` (Task 3), `UserModerationService` (Task 6), `ReportCategoryEnum`/`ReportStatusEnum` (Task 2).
- Produces: `POST /api/reports` — auth required, role `donor|recipient|guest`. `ReportService::file(array $data, string $reporterId): object`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\FoodListings\Entities\FoodListing;
use App\Modules\FoodListings\Entities\ListingClaim;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ReportFeatureTest extends TestCase
{
    private function claim(User $recipient, ?FoodListing $listing = null): ListingClaim
    {
        $listing ??= FoodListing::factory()->create();

        return ListingClaim::create([
            'food_listing_id' => $listing->id,
            'recipient_id' => $recipient->id,
            'claim_user_id' => $recipient->id,
            'status' => 'pending',
        ]);
    }

    public function test_donor_can_report_recipient_on_a_claim()
    {
        $donor = User::factory()->create(['email_verified_at' => now()]);
        $donor->assignRole('donor');
        $recipient = User::factory()->create();
        $claim = $this->claim($recipient);

        Passport::actingAs($donor, ['*']);

        $response = $this->postJson('/api/reports', [
            'reported_user_id' => $recipient->id,
            'claim_id' => $claim->id,
            'category' => 'no_show',
            'description' => 'Never showed up for pickup',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reports', [
            'reporter_id' => $donor->id,
            'reported_user_id' => $recipient->id,
            'claim_id' => $claim->id,
        ]);
    }

    public function test_duplicate_report_on_same_claim_rejected()
    {
        $donor = User::factory()->create(['email_verified_at' => now()]);
        $donor->assignRole('donor');
        $recipient = User::factory()->create();
        $claim = $this->claim($recipient);

        Passport::actingAs($donor, ['*']);
        $this->postJson('/api/reports', [
            'reported_user_id' => $recipient->id,
            'claim_id' => $claim->id,
            'category' => 'no_show',
            'description' => 'first report',
        ])->assertStatus(201);

        $response = $this->postJson('/api/reports', [
            'reported_user_id' => $recipient->id,
            'claim_id' => $claim->id,
            'category' => 'no_show',
            'description' => 'second report',
        ]);

        $response->assertStatus(409);
    }

    public function test_self_report_rejected()
    {
        $donor = User::factory()->create(['email_verified_at' => now()]);
        $donor->assignRole('donor');
        $claim = $this->claim($donor);

        Passport::actingAs($donor, ['*']);

        $response = $this->postJson('/api/reports', [
            'reported_user_id' => $donor->id,
            'claim_id' => $claim->id,
            'category' => 'other',
            'description' => 'self report',
        ]);

        $response->assertStatus(422);
    }

    public function test_third_report_in_30_days_auto_disables_reported_user()
    {
        Mail::fake();
        $recipient = User::factory()->create();
        $recipient->assignRole('recipient');

        for ($i = 0; $i < 3; $i++) {
            $donor = User::factory()->create(['email_verified_at' => now()]);
            $donor->assignRole('donor');
            $claim = $this->claim($recipient);
            Passport::actingAs($donor, ['*']);

            $this->postJson('/api/reports', [
                'reported_user_id' => $recipient->id,
                'claim_id' => $claim->id,
                'category' => 'no_show',
                'description' => "report $i",
            ])->assertStatus(201);
        }

        $recipient->refresh();
        $this->assertFalse((bool) $recipient->is_active);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/ReportFeatureTest.php`
Expected: FAIL — route `/api/reports` doesn't exist (404).

- [ ] **Step 3: Write the request, resource, service, controller, and route**

`app/Modules/Admin/Requests/StoreReportRequest.php`:
```php
<?php

namespace App\Modules\Admin\Requests;

use App\Modules\Core\Enums\ReportCategoryEnum;
use App\Modules\Core\Requests\BaseRequest;

class StoreReportRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function store(): array
    {
        return [
            'reported_user_id' => 'required|uuid|exists:users,id',
            'food_listing_id' => 'nullable|uuid|exists:food_listings,id|required_without:claim_id',
            'claim_id' => 'nullable|uuid|exists:listing_claims,id|required_without:food_listing_id',
            'category' => 'required|in:'.implode(',', ReportCategoryEnum::getAllValues()),
            'description' => 'required|string|max:1000',
        ];
    }

    public function update(): array
    {
        return [];
    }
}
```

`app/Modules/Admin/Resources/ReportResource.php`:
```php
<?php

namespace App\Modules\Admin\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'reporter_id' => $this->reporter_id,
            'reported_user_id' => $this->reported_user_id,
            'food_listing_id' => $this->food_listing_id,
            'claim_id' => $this->claim_id,
            'category' => $this->category,
            'description' => $this->description,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
```

`app/Modules/Admin/Services/ReportService.php`:
```php
<?php

namespace App\Modules\Admin\Services;

use App\Models\User;
use App\Modules\Admin\Repositories\ReportRepository;
use App\Modules\Core\Enums\ReportStatusEnum;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class ReportService
{
    private const AUTO_DISABLE_THRESHOLD = 3;

    private const AUTO_DISABLE_WINDOW_DAYS = 30;

    public function __construct(
        protected ReportRepository $reportRepository,
        protected UserModerationService $userModerationService,
    ) {}

    public function file(array $data, string $reporterId): object
    {
        if ($data['reported_user_id'] === $reporterId) {
            throw new Exception('You cannot report yourself', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->reportRepository->existsForReporterAndTarget(
            $reporterId,
            $data['food_listing_id'] ?? null,
            $data['claim_id'] ?? null
        )) {
            throw new Exception("You've already reported this", Response::HTTP_CONFLICT);
        }

        $data['reporter_id'] = $reporterId;
        $data['status'] = ReportStatusEnum::PENDING->value;

        $report = $this->reportRepository->store($data);

        $recentCount = $this->reportRepository->countRecentAgainst(
            $data['reported_user_id'],
            now()->subDays(self::AUTO_DISABLE_WINDOW_DAYS)
        );

        if ($recentCount >= self::AUTO_DISABLE_THRESHOLD) {
            $reportedUser = User::findOrFail($data['reported_user_id']);
            $this->userModerationService->disable(
                $reportedUser,
                sprintf('auto: %d+ reports in %d days', self::AUTO_DISABLE_THRESHOLD, self::AUTO_DISABLE_WINDOW_DAYS)
            );
        }

        return $report;
    }
}
```

`app/Modules/Admin/Controllers/ReportController.php`:
```php
<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Requests\StoreReportRequest;
use App\Modules\Admin\Resources\ReportResource;
use App\Modules\Admin\Services\ReportService;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    public function store(StoreReportRequest $request): JsonResponse
    {
        try {
            $report = $this->reportService->file($request->validated(), auth()->id());

            return $this->success('Report filed successfully', Response::HTTP_CREATED, new ReportResource($report));
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }
}
```

In `routes/api.php`, add the import near the other controller imports at the top, and add the route inside the shared `auth:api` group (the one modified in Task 7, currently ending around line 107):
```php
use App\Modules\Admin\Controllers\ReportController;
```
```php
Route::middleware('role:donor|recipient|guest')->post('reports', [ReportController::class, 'store']);
```
(add this line inside the `Route::middleware(['auth:api', 'account_active'])->group(function () { ... })` block from Task 7, alongside the other shared routes)

In `API_DOC.md`, add a new section documenting `POST /api/reports` (auth required, roles donor/recipient/guest) with the request payload (`reported_user_id`, `food_listing_id` or `claim_id`, `category`, `description`), the 201 success shape, and the 409/422/403 error cases — follow the existing doc's format for other endpoints in that file.

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Admin/ReportFeatureTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Run the full Admin test suite and commit**

Run: `php artisan test --compact tests/Feature/Admin`
Expected: PASS (all tests across Tasks 1, 3, 4, 5, 6, 7, 8)

```bash
git add app/Modules/Admin/Requests/StoreReportRequest.php app/Modules/Admin/Resources/ReportResource.php app/Modules/Admin/Services/ReportService.php app/Modules/Admin/Controllers/ReportController.php routes/api.php API_DOC.md tests/Feature/Admin/ReportFeatureTest.php
git commit -m "feat: add report filing endpoint with auto-disable trigger"
```
