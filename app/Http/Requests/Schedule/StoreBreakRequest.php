<?php

namespace App\Http\Requests\Schedule;

use App\Http\Requests\BaseRequest;
use Carbon\Carbon;

class StoreBreakRequest extends BaseRequest
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
            'type' => 'required|string|in:daily,weekly,specific_date,date_range',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'days' => 'required_if:type,weekly|array',
            'days.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'date' => 'required_if:type,specific_date|date_format:d.m.Y',
            'start_date' => 'required_if:type,date_range|date_format:d.m.Y',
            'end_date' => 'required_if:type,date_range|date_format:d.m.Y|after_or_equal:start_date',
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
        if (isset($validated['date']) && $validated['date']) {
            $validated['date'] = Carbon::createFromFormat('d.m.Y', $validated['date'])->format('Y-m-d');
        }

        if (isset($validated['start_date']) && $validated['start_date']) {
            $validated['start_date'] = Carbon::createFromFormat('d.m.Y', $validated['start_date'])->format('Y-m-d');
        }

        if (isset($validated['end_date']) && $validated['end_date']) {
            $validated['end_date'] = Carbon::createFromFormat('d.m.Y', $validated['end_date'])->format('Y-m-d');
        }

        return $validated;
    }
}
