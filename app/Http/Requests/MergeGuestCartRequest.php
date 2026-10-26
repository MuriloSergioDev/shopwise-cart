<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MergeGuestCartRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'guestCartId' => ['required', 'string', 'max:64'],
        ];
    }

    public function guestCartId(): string
    {
        return $this->string('guestCartId')->toString();
    }
}
