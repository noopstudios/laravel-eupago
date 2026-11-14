<?php

namespace Noop\Eupago\Enums;

enum FailureType: string
{
    /** EuPago explicitly refused the request, or it never left (invalid amount or phone). Safe to try again. */
    case Rejected = 'rejected';

    /** The outcome is not known: a payable reference may exist. Never repeated automatically. */
    case Unknown = 'unknown';

    /** The request was not sent: another one is in progress or unresolved, or the attempt limit was reached. */
    case Blocked = 'blocked';
}
