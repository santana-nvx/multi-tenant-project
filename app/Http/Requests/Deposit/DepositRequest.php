<?php

namespace App\Http\Requests\Deposit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DepositRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'currency' => ['required', 'string', 'size:3'],
            'pay_method' => ['required', 'string', 'in:online_banking,duitnowqr'],
            'bank_id' => ['required', 'string'],
            'phone_number' => ['required', 'string'],
        ];
    }
}
