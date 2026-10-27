<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body `{checkoutId}` of the snapshot and unlock routes. Ids are ULIDs (26 characters).
 */
final class CheckoutIdRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'checkoutId' => ['required', 'string', 'max:26'],
        ];
    }

    public function checkoutId(): string
    {
        return $this->string('checkoutId')->toString();
    }
}
