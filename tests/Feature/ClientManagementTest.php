<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_endpoint_paginates_without_returning_every_client(): void
    {
        [$owner, $salon, $staff, $service] = $this->createSalonContext();

        for ($i = 1; $i <= 30; $i++) {
            $client = User::factory()->create([
                'role' => 'klijent',
                'name' => sprintf('Client %02d', $i),
            ]);

            Appointment::factory()->create([
                'salon_id' => $salon->id,
                'staff_id' => $staff->id,
                'service_id' => $service->id,
                'client_id' => $client->id,
                'date' => now()->subDays($i)->format('Y-m-d'),
                'status' => 'confirmed',
                'total_price' => 25,
            ]);
        }

        $response = $this->actingAs($owner)->getJson('/api/v1/clients?per_page=10&page=2');

        $response->assertOk()
            ->assertJson([
                'total' => 30,
                'per_page' => 10,
                'current_page' => 2,
                'last_page' => 3,
            ]);

        $this->assertCount(10, $response->json('clients'));
    }

    public function test_clients_export_downloads_all_matching_clients_as_csv(): void
    {
        [$owner, $salon, $staff, $service] = $this->createSalonContext();

        foreach (['Amar Export', 'Ema Export'] as $name) {
            $client = User::factory()->create([
                'role' => 'klijent',
                'name' => $name,
            ]);

            Appointment::factory()->create([
                'salon_id' => $salon->id,
                'staff_id' => $staff->id,
                'service_id' => $service->id,
                'client_id' => $client->id,
                'date' => now()->subDay()->format('Y-m-d'),
                'status' => 'confirmed',
                'total_price' => 30,
            ]);
        }

        $response = $this->actingAs($owner)->get('/api/v1/clients/export');

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('Amar Export', $content);
        $this->assertStringContainsString('Ema Export', $content);
    }

    private function createSalonContext(): array
    {
        $owner = User::factory()->create(['role' => 'salon']);
        $salon = Salon::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'approved',
        ]);
        $staff = Staff::factory()->create(['salon_id' => $salon->id]);
        $service = Service::factory()->create([
            'salon_id' => $salon->id,
            'price' => 25,
        ]);

        return [$owner, $salon, $staff, $service];
    }
}
