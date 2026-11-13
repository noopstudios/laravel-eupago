<?php

use Noop\Eupago\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');
// Real calls to the EuPago sandbox: run only with --group=sandbox (see tests/Integration/SandboxTest.php).
pest()->extend(TestCase::class)->group('sandbox')->in('Integration');
