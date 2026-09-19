<?php

namespace App\Enums;

enum ReportType: string
{
    case EXAM = 'exam';
    case TERM = 'term';
    case FINAL = 'final';
}
