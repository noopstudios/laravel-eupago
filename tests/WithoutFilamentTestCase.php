<?php

namespace Noop\Eupago\Tests;

use Noop\Eupago\EupagoServiceProvider;

/**
 * A host app without Filament: only the package's own provider is registered.
 */
abstract class WithoutFilamentTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [EupagoServiceProvider::class];
    }
}
