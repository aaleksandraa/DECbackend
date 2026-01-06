<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\BaseRequest;
use App\Rules\StrongPassword;

class UpdateStaffRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->id === $this->salon->owner_id || $this->user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'role' => 'sometimes|string|max:255',
            'bio' => 'nullable|string',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $this->staff->user_id,
            'password' => ['nullable', new StrongPassword()],
            'phone' => 'nullable|string|max:255',
            'working_hours' => 'sometimes|array',
            'working_hours.*' => 'array',
            'working_hours.*.start' => 'required_with:working_hours.*|string',
            'working_hours.*.end' => 'required_with:working_hours.*|string',
            'working_hours.*.is_working' => 'required_with:working_hours.*|boolean',
            'specialties' => 'nullable|array',
            'specialties.*' => 'string',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'exists:services,id',
            'is_active' => 'sometimes|boolean',
            'auto_confirm' => 'sometimes|boolean',
        ];
    }
}
