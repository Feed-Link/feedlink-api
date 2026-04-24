<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // DatabaseTransactions wraps each test in a rolled-back transaction.
    // NEVER use RefreshDatabase — the DB connection points to production.
    // Roles/permissions are NOT re-seeded here — they already exist in the production DB
    // and are never rolled back by DatabaseTransactions.
    use DatabaseTransactions;
}
