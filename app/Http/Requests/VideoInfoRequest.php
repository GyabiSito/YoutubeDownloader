<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\YoutubeUrl;
use Illuminate\Foundation\Http\FormRequest;

final class VideoInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048', new YoutubeUrl],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'url.required' => 'Pegá primero un enlace de YouTube.',
            'url.max' => 'El enlace es demasiado largo.',
        ];
    }
}
