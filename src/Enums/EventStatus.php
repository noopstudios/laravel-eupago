<?php

namespace Noop\Eupago\Enums;

enum EventStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Rejected = 'rejected';
    case Orphaned = 'orphaned';
}
