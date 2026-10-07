<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Every test runs against a fresh in-memory SQLite database (phpunit.xml),
    // migrated once and rolled back per test — never the dev database.
    use RefreshDatabase;
}
