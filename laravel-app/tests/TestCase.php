<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $defaultConnection = (string) config('database.default');
        $databaseName = (string) config("database.connections.{$defaultConnection}.database");

        // Safety guard: tests in this repo must never hit the primary MySQL DB.
        if ($defaultConnection !== 'sqlite' || $databaseName !== ':memory:') {
            throw new RuntimeException(sprintf(
                'Unsafe test database configuration detected: connection=%s, database=%s. Expected sqlite :memory: only.',
                $defaultConnection,
                $databaseName === '' ? '(empty)' : $databaseName
            ));
        }
    }
}
