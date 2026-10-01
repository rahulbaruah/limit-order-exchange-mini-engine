<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\Symbol;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DemoUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'balance' => ['required', 'numeric', 'gte:0', 'decimal:0,2'],
            'locked_balance' => ['required', 'numeric', 'gte:0', 'decimal:0,2'],
            'assets' => ['required', 'array'],
            'assets.*.symbol' => ['required', Rule::enum(Symbol::class), 'distinct'],
            'assets.*.amount' => ['required', 'numeric', 'gte:0', 'decimal:0,8'],
            'assets.*.locked_amount' => ['required', 'numeric', 'gte:0', 'decimal:0,8'],
        ];
    }
}
