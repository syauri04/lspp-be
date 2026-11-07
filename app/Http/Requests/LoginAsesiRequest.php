<?php

namespace App\Http\Requests;

use App\Rules\CaptchaValid;
use Illuminate\Foundation\Http\FormRequest;

class LoginAsesiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            ...CaptchaValid::aturan($this->input('captcha_id')),
        ];
    }
}
