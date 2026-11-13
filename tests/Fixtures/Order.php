<?php

namespace Noop\Eupago\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A host-app model that payments are requested for; its table comes from tests/database/migrations.
 *
 * @property int $id
 */
class Order extends Model
{
    protected $table = 'test_orders';

    protected $guarded = [];
}
