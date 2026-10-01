<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\Symbol;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOrdersRequest extends FormRequest
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
            'symbol' => ['required', Rule::enum(Symbol::class)],
            'side' => ['sometimes', Rule::enum(OrderSide::class)],
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
        ];
    }
}
