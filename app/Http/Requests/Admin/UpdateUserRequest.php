<?php

namespace App\Http\Requests\Admin;

class UpdateUserRequest extends StoreUserRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['email'] = ['required', 'email', 'max:255', 'unique:users,email,'.$this->route('user')?->id];
        $rules['password'] = ['nullable', 'string', 'min:8'];

        return $rules;
    }
}
