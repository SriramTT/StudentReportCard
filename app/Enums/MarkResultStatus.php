<?php

namespace App\Enums;

enum MarkResultStatus: string
{
    case BLANK = 'blank';
    case NUMERIC = 'numeric';
    case ABSENT = 'absent';
}
