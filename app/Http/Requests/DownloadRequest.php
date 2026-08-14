<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\YoutubeUrl;
use Illuminate\Foundation\Http\FormRequest;

final class DownloadRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:video,audio'],
            'quality' => ['required_if:type,video', 'prohibited_if:type,audio', 'integer', 'between:144,4320'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'url.required' => 'Falta el enlace del video.',
            'url.max' => 'El enlace es demasiado largo.',
            'type.required' => 'Elegí video MP4 o audio MP3.',
            'type.in' => 'Elegí video MP4 o audio MP3.',
            'quality.required_if' => 'Elegí una calidad para el video.',
            'quality.prohibited_if' => 'El audio MP3 no usa selector de calidad.',
            'quality.integer' => 'La calidad seleccionada no es válida.',
            'quality.between' => 'La calidad seleccionada está fuera del rango permitido.',
        ];
    }
}
