<?php

namespace GetCandy\Http\Requests\Api\Products;

use GetCandy\Http\Requests\Api\FormRequest;
use GetCandy\Api\Products\Models\Product;

class UpdateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $ruleset = [
            'family_id' => 'hashid_is_valid:product_families'
        ];

        $attributes = app('api')->products()->getAttributes($this->product);
        $defaultChannel = app('api')->channels()->getDefaultRecord();
        $defaultLanguage = app('api')->languages()->getDefaultRecord();

        foreach ($attributes as $attribute) {
            if ($attribute->required) {
                $rulestring = 'attributes.' . $attribute->handle . '.' . $defaultChannel->handle . '.' . $defaultLanguage->code;
                $ruleset[$rulestring] = 'required';
            }
        }

        return $ruleset;
    }
}
