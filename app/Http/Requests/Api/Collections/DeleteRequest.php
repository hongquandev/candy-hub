<?php

namespace GetCandy\Http\Requests\Api\Collections;

use GetCandy\Http\Requests\Api\FormRequest;
use GetCandy\Api\Collections\Models\Collection;

class DeleteRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
        ];
    }
}
