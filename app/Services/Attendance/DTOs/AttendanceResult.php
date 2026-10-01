<?php

namespace App\Services\Attendance\DTOs;

readonly class AttendanceResult
{
    /**
     * @param int $daysAttended
     * @param int $totalWorkingDays
     * @param float|null $percentage Null when totalWorkingDays == 0 (0/0)
     * @param string $formattedPercentage E.g., '85.00%' or 'N/A'
     * @param bool $isAvailable True when totalWorkingDays > 0
     */
    public function __construct(
        public int $daysAttended,
        public int $totalWorkingDays,
        public ?float $percentage,
        public string $formattedPercentage,
        public bool $isAvailable
    ) {}

    /**
     * Convert DTO to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'days_attended' => $this->daysAttended,
            'total_working_days' => $this->totalWorkingDays,
            'percentage' => $this->percentage,
            'formatted_percentage' => $this->formattedPercentage,
            'is_available' => $this->isAvailable,
        ];
    }
}
