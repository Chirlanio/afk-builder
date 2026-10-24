<?php

namespace Tests;

use App\Models\SignatureItemCost;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Cache estático de custos de SI: preenchido por um teste sem o seeder,
        // vazaria custos zerados para os testes seguintes.
        SignatureItemCost::flushCumulativeCache();
    }
}
