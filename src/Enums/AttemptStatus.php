<?php

namespace Noop\Eupago\Enums;

enum AttemptStatus: string
{
    case Creating = 'creating';
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
