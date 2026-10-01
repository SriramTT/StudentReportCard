<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $activeDb = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
        if ($activeDb === 'school_report_card') {
            throw new \RuntimeException(
                "CRITICAL TEST ISOLATION BREACH: Automated tests must NEVER run against persistent application database 'school_report_card'. " .
                "Check phpunit.xml to ensure tests target dedicated test database 'school_report_card_audit'."
            );
        }
    }
}
