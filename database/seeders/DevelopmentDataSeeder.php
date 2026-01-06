<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\Service;
use App\Models\Appointment;
use App\Models\Review;
use App\Models\Favorite;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class DevelopmentDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Kreiranje test podataka...');

        // 1. Kreiraj klijente (50)
        $this->command->info('👥 Kreiranje klijenata...');
        $clients = User::factory()->count(50)->create([
            'role' => 'klijent',
            'email_verified_at' => now(),
        ]);
        $this->command->info("✅ Kreirano {$clients->count()} klijenata");

        // 2. Kreiraj vlasnike salona (15)
        $this->command->info('👔 Kreiranje vlasnika salona...');
        $owners = User::factory()->count(15)->create([
            'role' => 'salon',
            'email_verified_at' => now(),
        ]);
        $this->command->info("✅ Kreirano {$owners->count()} vlasnika");

        // 3. Kreiraj salone (15) - različiti gradovi BiH
        $this->command->info('💈 Kreiranje salona...');
        $cities = ['Sarajevo', 'Banja Luka', 'Tuzla', 'Zenica', 'Mostar', 'Bijeljina', 'Brčko', 'Prijedor', 'Trebinje', 'Doboj'];
        $salonNames = [
            'Salon Elegance', 'Beauty Studio', 'Hair & Style', 'Frizerski Salon Lux',
            'Studio Ana', 'Salon Prestige', 'Beauty Center', 'Hair Design',
            'Salon Glamour', 'Style Studio', 'Beauty Point', 'Hair Art',
            'Salon Exclusive', 'Beauty Lounge', 'Hair Fashion'
        ];

        $salons = collect();
        foreach ($owners as $index => $owner) {
            $city = $cities[$index % count($cities)];
            $salon = Salon::create([
                'owner_id' => $owner->id,
                'name' => $salonNames[$index],
                'description' => "Profesionalni frizerski salon u {$city}u sa dugogodišnjim iskustvom. Nudimo sve vrste usluga za muškarce, žene i djecu.",
                'address' => "Ulica {$index} broj " . rand(1, 100),
                'city' => $city,
                'postal_code' => rand(71000, 79000),
                'country' => 'Bosnia and Herzegovina',
                'phone' => '0' . rand(60, 66) . rand(100000, 999999),
                'email' => "salon{$index}@example.com",
                'website' => "https://salon{$index}.ba",
                'working_hours' => [
                    'monday' => ['start' => '08:00', 'end' => '20:00', 'is_working' => true],
                    'tuesday' => ['start' => '08:00', 'end' => '20:00', 'is_working' => true],
                    'wednesday' => ['start' => '08:00', 'end' => '20:00', 'is_working' => true],
                    'thursday' => ['start' => '08:00', 'end' => '20:00', 'is_working' => true],
                    'friday' => ['start' => '08:00', 'end' => '20:00', 'is_working' => true],
                    'saturday' => ['start' => '09:00', 'end' => '18:00', 'is_working' => true],
                    'sunday' => ['start' => '10:00', 'end' => '16:00', 'is_working' => $index % 3 === 0], // Neki rade nedjeljom
                ],
                'location' => [
                    'latitude' => 43.8563 + (rand(-100, 100) / 1000),
                    'longitude' => 18.4131 + (rand(-100, 100) / 1000),
                ],
                'target_audience' => [
                    'men' => true,
                    'women' => true,
                    'children' => $index % 2 === 0,
                ],
                'amenities' => ['parking', 'wifi', 'waiting_area', 'coffee'],
                'rating' => rand(35, 50) / 10, // 3.5 - 5.0
                'review_count' => rand(5, 50),
                'status' => 'approved',
                'is_verified' => true,

            ]);
            $salons->push($salon);
        }
        $this->command->info("✅ Kreirano {$salons->count()} salona");

        // 4. Kreiraj usluge za svaki salon
        $this->command->info('✂️ Kreiranje usluga...');
        $serviceTemplates = [
            ['name' => 'Muško šišanje', 'category' => 'Šišanje', 'duration' => 30, 'price' => 15],
            ['name' => 'Žensko šišanje', 'category' => 'Šišanje', 'duration' => 60, 'price' => 30],
            ['name' => 'Dječije šišanje', 'category' => 'Šišanje', 'duration' => 20, 'price' => 10],
            ['name' => 'Brijanje', 'category' => 'Brijanje', 'duration' => 20, 'price' => 10],
            ['name' => 'Brijanje i šišanje', 'category' => 'Brijanje', 'duration' => 45, 'price' => 20],
            ['name' => 'Farbanje kose', 'category' => 'Farbanje', 'duration' => 90, 'price' => 50],
            ['name' => 'Pramenovi', 'category' => 'Farbanje', 'duration' => 120, 'price' => 70],
            ['name' => 'Feniranje', 'category' => 'Styling', 'duration' => 30, 'price' => 15],
            ['name' => 'Svečana frizura', 'category' => 'Styling', 'duration' => 60, 'price' => 40],
            ['name' => 'Trajno oblikovanje', 'category' => 'Tretmani', 'duration' => 120, 'price' => 80],
            ['name' => 'Keratinski tretman', 'category' => 'Tretmani', 'duration' => 150, 'price' => 100],
            ['name' => 'Masaža vlasišta', 'category' => 'Tretmani', 'duration' => 30, 'price' => 20],
        ];

        $allServices = collect();
        foreach ($salons as $salon) {
            // Svaki salon ima 6-10 usluga
            $numServices = rand(6, 10);
            $selectedTemplates = collect($serviceTemplates)->random($numServices);

            foreach ($selectedTemplates as $template) {
                $service = Service::create([
                    'salon_id' => $salon->id,
                    'name' => $template['name'],
                    'description' => "Profesionalna usluga {$template['name']} sa vrhunskim proizvodima.",
                    'category' => $template['category'],
                    'duration' => $template['duration'],
                    'price' => $template['price'] + rand(-5, 10), // Varijacija u cijeni
                    'is_active' => true,
                ]);
                $allServices->push($service);
            }
        }
        $this->command->info("✅ Kreirano {$allServices->count()} usluga");

        // 5. Kreiraj osoblje (3-5 po salonu)
        $this->command->info('👨‍🦰 Kreiranje osoblja...');
        $staffNames = [
            'Marko Marković', 'Ana Anić', 'Petar Petrović', 'Jelena Jovanović',
            'Nikola Nikolić', 'Sara Sarić', 'Ivan Ivanović', 'Maja Majić',
            'Stefan Stefanović', 'Nina Ninić', 'Luka Lukić', 'Mia Mićić',
            'Filip Filipović', 'Ema Emić', 'David Davidović'
        ];

        $allStaff = collect();
        foreach ($salons as $salon) {
            $numStaff = rand(3, 5);

            for ($i = 0; $i < $numStaff; $i++) {
                $staff = Staff::create([
                    'salon_id' => $salon->id,
                    'name' => $staffNames[($salon->id * 10 + $i) % count($staffNames)],
                    'role' => $i === 0 ? 'Glavni frizer' : ($i === 1 ? 'Stilista' : 'Frizer'),
                    'bio' => "Profesionalni frizer sa " . rand(3, 15) . " godina iskustva.",
                    'working_hours' => [
                        'monday' => ['start' => '08:00', 'end' => '16:00', 'is_working' => true],
                        'tuesday' => ['start' => '08:00', 'end' => '16:00', 'is_working' => true],
                        'wednesday' => ['start' => '08:00', 'end' => '16:00', 'is_working' => true],
                        'thursday' => ['start' => '12:00', 'end' => '20:00', 'is_working' => true],
                        'friday' => ['start' => '12:00', 'end' => '20:00', 'is_working' => true],
                        'saturday' => ['start' => '09:00', 'end' => '18:00', 'is_working' => true],
                        'sunday' => ['start' => '10:00', 'end' => '16:00', 'is_working' => $i % 2 === 0],
                    ],
                    'specialties' => ['Šišanje', 'Farbanje', 'Styling'],
                    'rating' => rand(40, 50) / 10,
                    'review_count' => rand(5, 30),
                    'is_active' => true,
                    'auto_confirm' => $i % 2 === 0,
                ]);

                // Dodijeli usluge osobi (4-8 usluga)
                $salonServices = $allServices->where('salon_id', $salon->id);
                $staffServices = $salonServices->random(min(rand(4, 8), $salonServices->count()));
                $staff->services()->attach($staffServices->pluck('id'));

                $allStaff->push($staff);
            }
        }
        $this->command->info("✅ Kreirano {$allStaff->count()} članova osoblja");

        // 6. Kreiraj termine (prošli, današnji, budući)
        $this->command->info('📅 Kreiranje termina...');
        $statuses = ['confirmed', 'pending', 'completed', 'cancelled'];
        $appointments = collect();

        foreach ($salons as $salon) {
            $salonStaff = $allStaff->where('salon_id', $salon->id);
            $salonServices = $allServices->where('salon_id', $salon->id);

            // Prošli termini (30 dana unazad) - 15-25 po salonu
            $attempts = 0;
            $created = 0;
            $target = rand(15, 25);

            while ($created < $target && $attempts < $target * 3) {
                $attempts++;
                $daysAgo = rand(1, 30);
                $date = Carbon::now()->subDays($daysAgo);
                $staff = $salonStaff->random();
                $service = $salonServices->random();
                $client = $clients->random();

                // Generiši jedinstveno vrijeme
                $hour = rand(8, 17);
                $minute = rand(0, 1) * 30;
                $time = sprintf('%02d:%02d', $hour, $minute);

                // Provjeri da li već postoji termin
                $exists = Appointment::where('staff_id', $staff->id)
                    ->where('date', $date->format('Y-m-d'))
                    ->where('time', $time)
                    ->exists();

                if (!$exists) {
                    $appointment = Appointment::create([
                        'salon_id' => $salon->id,
                        'staff_id' => $staff->id,
                        'service_id' => $service->id,
                        'client_id' => $client->id,
                        'client_name' => $client->name,
                        'client_email' => $client->email,
                        'client_phone' => '0' . rand(60, 66) . rand(100000, 999999),
                        'date' => $date->format('Y-m-d'),
                        'time' => $time,
                        'end_time' => sprintf('%02d:%02d', $hour + 1, $minute),
                        'total_price' => $service->price,
                        'payment_status' => $daysAgo > 1 ? 'paid' : 'pending',
                        'status' => $daysAgo > 1 ? 'completed' : 'confirmed',
                        'notes' => rand(0, 1) ? 'Napomena klijenta: ' . ['Molim kraće', 'Kao prošli put', 'Nešto novo'][rand(0, 2)] : null,
                        'created_at' => $date->subHours(rand(1, 48)),
                    ]);
                    $appointments->push($appointment);
                    $created++;
                }
            }

            // Današnji termini - 3-6 po salonu
            for ($i = 0; $i < rand(3, 6); $i++) {
                $staff = $salonStaff->random();
                $service = $salonServices->random();
                $client = $clients->random();

                // Generiši jedinstveno vrijeme
                $hour = 8 + ($i * 2); // 8, 10, 12, 14, 16h
                $minute = 0;

                $appointment = Appointment::create([
                    'salon_id' => $salon->id,
                    'staff_id' => $staff->id,
                    'service_id' => $service->id,
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'client_email' => $client->email,
                    'client_phone' => '0' . rand(60, 66) . rand(100000, 999999),
                    'date' => Carbon::today()->format('Y-m-d'),
                    'time' => sprintf('%02d:%02d', $hour, $minute),
                    'end_time' => sprintf('%02d:%02d', $hour + 1, $minute),
                    'total_price' => $service->price,
                    'payment_status' => 'pending',
                    'status' => ['confirmed', 'pending'][rand(0, 1)],
                    'created_at' => Carbon::today()->subHours(rand(1, 24)),
                ]);
                $appointments->push($appointment);
            }

            // Budući termini (30 dana unaprijed) - 20-30 po salonu
            $attempts = 0;
            $created = 0;
            $target = rand(20, 30);

            while ($created < $target && $attempts < $target * 3) {
                $attempts++;
                $daysAhead = rand(1, 30);
                $date = Carbon::now()->addDays($daysAhead);
                $staff = $salonStaff->random();
                $service = $salonServices->random();
                $client = $clients->random();

                // Generiši jedinstveno vrijeme
                $hour = rand(8, 17);
                $minute = rand(0, 1) * 30;
                $time = sprintf('%02d:%02d', $hour, $minute);

                // Provjeri da li već postoji termin za ovog staff-a u ovo vrijeme
                $exists = Appointment::where('staff_id', $staff->id)
                    ->where('date', $date->format('Y-m-d'))
                    ->where('time', $time)
                    ->exists();

                if (!$exists) {
                    $appointment = Appointment::create([
                        'salon_id' => $salon->id,
                        'staff_id' => $staff->id,
                        'service_id' => $service->id,
                        'client_id' => $client->id,
                        'client_name' => $client->name,
                        'client_email' => $client->email,
                        'client_phone' => '0' . rand(60, 66) . rand(100000, 999999),
                        'date' => $date->format('Y-m-d'),
                        'time' => $time,
                        'end_time' => sprintf('%02d:%02d', $hour + 1, $minute),
                        'total_price' => $service->price,
                        'payment_status' => 'pending',
                        'status' => 'pending',
                        'created_at' => now()->subHours(rand(1, 72)),
                    ]);
                    $appointments->push($appointment);
                    $created++;
                }
            }
        }
        $this->command->info("✅ Kreirano {$appointments->count()} termina");

        // 7. Kreiraj recenzije (samo za završene termine)
        $this->command->info('⭐ Kreiranje recenzija...');
        $completedAppointments = $appointments->where('status', 'completed');
        $reviews = collect();

        $reviewComments = [
            'Odličan salon, preporučujem!',
            'Vrlo zadovoljan uslugom.',
            'Profesionalno osoblje i prijatna atmosfera.',
            'Uvijek se vraćam ovdje.',
            'Najbolji frizer u gradu!',
            'Brza usluga i odličan rezultat.',
            'Ljubazno osoblje i kvalitetna usluga.',
            'Zadovoljan sam, ali moglo bi biti bolje.',
            'Dobra cijena za kvalitet koji dobijete.',
            'Preporučujem svima!',
        ];

        foreach ($completedAppointments->random(min(200, $completedAppointments->count())) as $appointment) {
            $review = Review::create([
                'salon_id' => $appointment->salon_id,
                'staff_id' => $appointment->staff_id,
                'client_id' => $appointment->client_id,
                'client_name' => $appointment->client_name,
                'appointment_id' => $appointment->id,
                'rating' => rand(3, 5),
                'comment' => $reviewComments[array_rand($reviewComments)],
                'date' => Carbon::parse($appointment->date)->addDays(rand(1, 3))->format('Y-m-d'),
                'is_verified' => true,
                'created_at' => Carbon::parse($appointment->date)->addDays(rand(1, 3)),
            ]);
            $reviews->push($review);
        }
        $this->command->info("✅ Kreirano {$reviews->count()} recenzija");

        // 8. Kreiraj favorite (klijenti čuvaju omiljene salone)
        $this->command->info('❤️ Kreiranje favorita...');
        $favorites = collect();

        foreach ($clients->random(30) as $client) {
            $favoriteSalons = $salons->random(rand(1, 5));
            foreach ($favoriteSalons as $salon) {
                $favorite = Favorite::create([
                    'user_id' => $client->id,
                    'salon_id' => $salon->id,
                    'created_at' => now()->subDays(rand(1, 60)),
                ]);
                $favorites->push($favorite);
            }
        }
        $this->command->info("✅ Kreirano {$favorites->count()} favorita");

        // 9. Ažuriraj rating i review_count za salone i osoblje
        $this->command->info('📊 Ažuriranje statistika...');
        foreach ($salons as $salon) {
            $salonReviews = $reviews->where('salon_id', $salon->id);
            if ($salonReviews->count() > 0) {
                $salon->update([
                    'rating' => round($salonReviews->avg('rating'), 1),
                    'review_count' => $salonReviews->count(),
                ]);
            }
        }

        foreach ($allStaff as $staff) {
            $staffReviews = $reviews->where('staff_id', $staff->id);
            if ($staffReviews->count() > 0) {
                $staff->update([
                    'rating' => round($staffReviews->avg('rating'), 1),
                    'review_count' => $staffReviews->count(),
                ]);
            }
        }

        // Finalni izvještaj
        $this->command->info('');
        $this->command->info('✨ ========================================');
        $this->command->info('✅ USPJEŠNO KREIRANI TEST PODACI!');
        $this->command->info('========================================');
        $this->command->info("👥 Klijenti: {$clients->count()}");
        $this->command->info("👔 Vlasnici: {$owners->count()}");
        $this->command->info("💈 Saloni: {$salons->count()}");
        $this->command->info("👨‍🦰 Osoblje: {$allStaff->count()}");
        $this->command->info("✂️ Usluge: {$allServices->count()}");
        $this->command->info("📅 Termini: {$appointments->count()}");
        $this->command->info("   - Prošli: " . $appointments->where('status', 'completed')->count());
        $this->command->info("   - Današnji: " . $appointments->where('date', Carbon::today()->format('Y-m-d'))->count());
        $this->command->info("   - Budući: " . $appointments->whereIn('status', ['confirmed', 'pending'])->where('date', '>', Carbon::today()->format('Y-m-d'))->count());
        $this->command->info("⭐ Recenzije: {$reviews->count()}");
        $this->command->info("❤️ Favoriti: {$favorites->count()}");
        $this->command->info('========================================');
        $this->command->info('');
        $this->command->info('🔐 Test kredencijali:');
        $this->command->info('   Email: bilo koji od kreiranih');
        $this->command->info('   Password: password');
        $this->command->info('');
    }
}
