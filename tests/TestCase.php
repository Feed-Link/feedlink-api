<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Re-seed roles after RefreshDatabase refreshes all tables
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }
}
