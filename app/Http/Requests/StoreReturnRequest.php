<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order
            && $this->user() !== null
            && (int) $order->user_id === (int) $this->user()->id;
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);

        if (is_array($items) && collect($items)->contains(
            fn (mixed $item): bool => is_array($item) && array_key_exists('include', $item)
        )) {
            $items = collect($items)
                ->filter(fn (mixed $item): bool => is_array($item) && filter_var($item['include'] ?? false, FILTER_VALIDATE_BOOL))
                ->map(function (array $item): array {
                    unset($item['include']);

                    return $item;
                })
                ->values()
                ->all();
        }

        $this->merge(['items' => $items]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_contact_notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.issue_type' => ['required', 'string', Rule::in(ReturnRequest::ISSUE_TYPES)],
            'items.*.issue_description' => ['required', 'string', 'min:5', 'max:1000'],
            'items.*.preferred_resolution' => ['required', 'string', Rule::in(ReturnRequest::PREFERRED_RESOLUTIONS)],
            'evidence' => ['required', 'array', 'min:1', 'max:'.ReturnRequest::MAX_EVIDENCE_FILES],
            'evidence.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.min' => 'Select at least one affected item.',
            'items.required' => 'Select at least one affected item.',
            'evidence.required' => 'Upload at least one clear photo of the problem.',
            'evidence.max' => 'Upload no more than five photos.',
            'evidence.*.image' => 'Every evidence file must be a valid image.',
            'evidence.*.max' => 'Each evidence photo must be 5 MB or smaller.',
        ];
    }
}
