<?php

namespace Tests;

use App\Models\ProviderType;
use App\Models\Setting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Les réglages sont mémorisés pour la requête : chaque test repart des valeurs par défaut.
        Setting::flush();
        ProviderType::flushCache();
    }
}
