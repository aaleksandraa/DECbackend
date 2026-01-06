<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'recipient_id' => $this->recipient_id,
            'related_id' => $this->related_id,
            'is_read' => $this->is_read,
            'created_at' => $this->created_at->format('d.m.Y H:i'),
            'created_at_diff' => $this->created_at->diffForHumans(),
        ];

        // For appointment notifications, include the appointment date
        $appointmentTypes = [
            'new_appointment',
            'appointment_confirmed',
            'appointment_cancelled',
            'appointment_completed',
            'appointment_reminder'
        ];

        if (in_array($this->type, $appointmentTypes) && $this->related_id) {
            $appointment = Appointment::find($this->related_id);
            if ($appointment) {
                $data['appointment_date'] = $appointment->date;
                $data['appointment_id'] = $appointment->id;
            }
        }

        return $data;
    }
}
