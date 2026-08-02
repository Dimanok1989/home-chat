<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\UsesDatabaseTransactions;

abstract class TestCase extends BaseTestCase
{
    use UsesDatabaseTransactions;
}
