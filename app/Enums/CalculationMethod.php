<?php

namespace App\Enums;

enum CalculationMethod: string
{
    case AVERAGE_PERCENTAGE = 'average_percentage';
    case COMBINED_MARKS = 'combined_marks';
}
