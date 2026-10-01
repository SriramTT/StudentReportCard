<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CaseInsensitiveUniqueEmail implements ValidationRule
{
    /**
     * @param  int|null  $ignoreUserId  User ID to ignore when checking uniqueness (for updates)
     */
    public function __construct(
        protected ?int $ignoreUserId = null
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $emailLower = strtolower(trim($value));

        // Check uniqueness across ALL accounts (active and inactive), ignoring case
        $query = User::query()->whereRaw('LOWER(email) = ?', [$emailLower]);

        if ($this->ignoreUserId !== null) {
            $query->where('id', '!=', $this->ignoreUserId);
        }

        if ($query->exists()) {
            $fail('The email address has already been taken.');
        }
    }
}
