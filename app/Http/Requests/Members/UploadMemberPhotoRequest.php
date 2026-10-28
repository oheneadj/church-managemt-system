<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Validates a member photo upload: JPG or PNG, up to 5 MB and 4096 pixels on a side (docs/08).
 * The file type is read from the file's contents, not from its name or the Content-Type header.
 * The pixel limit also bounds the memory needed to decode it.
 */
class UploadMemberPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                File::image()
                    ->max(5 * 1024)
                    ->dimensions(Rule::dimensions()->maxWidth(4096)->maxHeight(4096)),
                'mimes:jpg,jpeg,png',
            ],
        ];
    }
}
