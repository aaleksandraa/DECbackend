<?php

namespace App\Http\Requests\Schedule;

use App\Http\Requests\BaseRequest;
use Carbon\Carbon;

class StoreVacationRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->route('salon')) {
            return $this->user()->id === $this->route('salon')->owner_id || $this->user()->role === 'admin';
        }

        if ($this->route('staff')) {
            $staff = $this->route('staff');
            if ($staff->user_id === $this->user()->id) {
                return true;
            }
            return $this->user()->id === $staff->salon->owner_id || $this->user()->role === 'admin';
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'start_date' => 'required|date_format:d.m.Y',
            'end_date' => 'required|date_format:d.m.Y|after_or_equal:start_date',
            'type' => 'required|string|in:vacation,sick_leave,personal,other',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * Get the validated data with dates converted to ISO format.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        // Convert dates from European format (d.m.Y) to ISO format (Y-m-d) for database storage
        if (isset($validated['start_date']) && $validated['start_date']) {
            $validated['start_date'] = Carbon::createFromFormat('d.m.Y', $validated['start_date'])->format('Y-m-d');
        }

        if (isset($validated['end_date']) && $validated['end_date']) {
            $validated['end_date'] = Carbon::createFromFormat('d.m.Y', $validated['end_date'])->format('Y-m-d');
        }

        return $validated;
    }
}
