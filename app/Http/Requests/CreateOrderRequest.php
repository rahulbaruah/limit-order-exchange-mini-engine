<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderSide;
use App\Enums\Symbol;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:255'],
            'symbol' => ['required', Rule::enum(Symbol::class)],
            'side' => ['required', Rule::enum(OrderSide::class)],
            'price' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,8'],
        ];
    }
}
