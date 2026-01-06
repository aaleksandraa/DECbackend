<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Review;
use App\Models\Salon;
use App\Models\SalonImage;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\LocationSeeder;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
         // 1. Osnovni podaci (lokacije, gradovi, države...)
        $this->call([
            LocationSeeder::class,
        ]);



          // Create admin user (idempotent)
        User::updateOrCreate(
            ['email' => 'admin@salonbooking.ba'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // Create salon owner (idempotent)
        $salonOwner = User::updateOrCreate(
            ['email' => 'salon@example.com'],
            [
                'name' => 'Salon Owner',
                'password' => Hash::make('password'),
                'phone' => '+387 61 123 456',
                'role' => 'salon',
                'email_verified_at' => now(),
            ]
        );

        // Create salon (idempotent)
        $salon = Salon::updateOrCreate(
            ['email' => 'info@beautystudio.ba'],
            [
                'name' => 'Beauty Studio Marija',
                'description' => 'Moderni frizerski salon u centru Sarajeva sa iskusnim frizerima i najnovijim trendovima.',
                'address' => 'Ferhadija 15',
                'city' => 'Sarajevo',
                'postal_code' => '71000',
                'country' => 'Bosna i Hercegovina',
                'phone' => '+387 33 123 456',
                'website' => 'https://beautystudio.ba',
                'working_hours' => [
                    'monday' => ['open' => '09:00', 'close' => '19:00', 'is_open' => true],
                    'tuesday' => ['open' => '09:00', 'close' => '19:00', 'is_open' => true],
                    'wednesday' => ['open' => '09:00', 'close' => '19:00', 'is_open' => true],
                    'thursday' => ['open' => '09:00', 'close' => '19:00', 'is_open' => true],
                    'friday' => ['open' => '09:00', 'close' => '20:00', 'is_open' => true],
                    'saturday' => ['open' => '08:00', 'close' => '16:00', 'is_open' => true],
                    'sunday' => ['open' => '10:00', 'close' => '15:00', 'is_open' => false],
                ],
                'location' => [
                    'lat' => 43.8563,
                    'lng' => 18.4131,
                ],
                'target_audience' => [
                    'women' => true,
                    'men' => true,
                    'children' => true,
                ],
                'amenities' => ['WiFi', 'Klima uređaj', 'Parking', 'Kartično plaćanje'],
                'social_media' => [
                    'facebook' => 'https://facebook.com/beautystudiomarija',
                    'instagram' => 'https://instagram.com/beautystudiomarija',
                ],
                'owner_id' => $salonOwner->id,
                'status' => 'approved'
            ]
        );

        // Create salon images - skip for now due to PostgreSQL boolean casting issue
        // Will be added manually or through admin panel

        // Create staff user (idempotent)
        $staffUser = User::updateOrCreate(
            ['email' => 'marija@example.com'],
            [
                'name' => 'Marija Petrović',
                'password' => Hash::make('password'),
                'phone' => '+387 61 234 567',
                'role' => 'frizer',
                'email_verified_at' => now(),
            ]
        );

        // Create staff (idempotent)
        $staff = Staff::updateOrCreate(
            ['user_id' => $staffUser->id, 'salon_id' => $salon->id],
            [
                'name' => 'Marija Petrović',
                'role' => 'Senior stilista',
                'bio' => 'Iskusna stilista sa preko 10 godina rada u beauty industriji.',
                'working_hours' => [
                    'monday' => ['start' => '09:00', 'end' => '17:00', 'is_working' => true],
                    'tuesday' => ['start' => '09:00', 'end' => '17:00', 'is_working' => true],
                    'wednesday' => ['start' => '09:00', 'end' => '17:00', 'is_working' => true],
                    'thursday' => ['start' => '09:00', 'end' => '17:00', 'is_working' => true],
                    'friday' => ['start' => '09:00', 'end' => '19:00', 'is_working' => true],
                    'saturday' => ['start' => '08:00', 'end' => '14:00', 'is_working' => true],
                    'sunday' => ['start' => '10:00', 'end' => '15:00', 'is_working' => false],
                ],
                'specialties' => ['Balayage', 'Kreativno šišanje', 'Styling'],
                'rating' => 4.9,
                'review_count' => 1,
            ]
        );

        // Create services (idempotent)
        $service1 = Service::updateOrCreate(
            ['salon_id' => $salon->id, 'name' => 'Šišanje i feniranje'],
            [
                'description' => 'Profesionalno šišanje sa stilizovanjem',
                'duration' => 45,
                'price' => 25.00,
                'category' => 'Šišanje',
            ]
        );

        $service2 = Service::updateOrCreate(
            ['salon_id' => $salon->id, 'name' => 'Farbanje kose'],
            [
                'description' => 'Kompletno farbanje sa najkvalitetnijim bojama',
                'duration' => 120,
                'price' => 45.00,
                'category' => 'Farbanje',
            ]
        );

        // Assign services to staff (idempotent - sync instead of attach)
        $staff->services()->sync([$service1->id, $service2->id]);

        // Create client user (idempotent)
        $clientUser = User::updateOrCreate(
            ['email' => 'ana@example.com'],
            [
                'name' => 'Ana Nikolić',
                'password' => Hash::make('password'),
                'phone' => '+387 61 345 678',
                'role' => 'klijent',
                'email_verified_at' => now(),
            ]
        );

        // Create appointment (idempotent)
        $appointment = Appointment::updateOrCreate(
            [
                'staff_id' => $staff->id,
                'date' => now()->addDays(2)->format('Y-m-d'),
                'time' => '14:00',
            ],
            [
                'client_id' => $clientUser->id,
                'client_name' => $clientUser->name,
                'client_email' => $clientUser->email,
                'client_phone' => $clientUser->phone,
                'salon_id' => $salon->id,
                'service_id' => $service1->id,
                'end_time' => '14:45',
                'status' => 'confirmed',
                'total_price' => $service1->price,
                'payment_status' => 'pending',
            ]
        );

        // Create completed appointment (idempotent)
        $completedAppointment = Appointment::updateOrCreate(
            [
                'staff_id' => $staff->id,
                'date' => now()->subDays(5)->format('Y-m-d'),
                'time' => '10:00',
            ],
            [
                'client_id' => $clientUser->id,
                'client_name' => $clientUser->name,
                'client_email' => $clientUser->email,
                'client_phone' => $clientUser->phone,
                'salon_id' => $salon->id,
                'service_id' => $service1->id,
                'end_time' => '10:45',
                'status' => 'completed',
                'total_price' => $service1->price,
                'payment_status' => 'paid',
            ]
        );

        // Create review (idempotent)
        Review::updateOrCreate(
            ['appointment_id' => $completedAppointment->id],
            [
                'client_id' => $clientUser->id,
                'client_name' => $clientUser->name,
                'salon_id' => $salon->id,
                'staff_id' => $staff->id,
                'rating' => 5,
                'comment' => 'Odličan pristup i rezultat! Marija je vrhunski profesionalac.',
                'date' => now()->format('Y-m-d'),
            ]
        );
    }
}
