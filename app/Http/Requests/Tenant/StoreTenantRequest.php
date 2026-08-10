<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'unique:domains,domain'],
            'name' => ['required', 'string'],
            'user_name' => ['required', 'string'],
            'user_email' => ['required', 'email'],
            'user_password' => ['required', 'string', 'min:8'],
            'admin_name' => ['required', 'string'],
            'admin_email' => ['required', 'email'],
            'admin_password' => ['required', 'string', 'min:8'],
        ];
    }
}
