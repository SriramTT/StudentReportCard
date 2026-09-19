<?php

namespace App\Enums;

enum StudentPlacementStatus: string
{
    case ACTIVE = 'active';
    case INTERNAL_TRANSFER = 'internal_transfer';
    case WITHDRAWN = 'withdrawn';
    case TRANSFERRED_OUT = 'transferred_out';
}
