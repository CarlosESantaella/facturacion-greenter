<?php

namespace App\Rules;

use Closure;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueRucRule implements ValidationRule
{

    public function __construct(protected mixed $company_id = null) {}
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $company = Company::where('ruc', $value)
            ->where('user_id', Auth::user()->id)
            ->when($this->company_id, function ($query) {
                $query->where('id', '!=', $this->company_id);
            })
            ->first();

        if ($company) {
            $fail('The RUC has already been taken for this user.');
        }
    }
}
