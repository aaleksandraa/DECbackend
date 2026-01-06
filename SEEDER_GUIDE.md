# Development Data Seeder - Vodič

## Šta Seeder Kreira?

Ovaj seeder kreira **kompletnu test bazu podataka** sa realističnim podacima:

### 📊 Statistika Podataka

- **50 klijenata** - različita imena, svi verifikovani
- **15 vlasnika salona** - svaki ima svoj salon
- **15 salona** - raspoređeni po gradovima BiH
- **45-75 članova osoblja** - 3-5 po salonu
- **90-150 usluga** - 6-10 po salonu
- **750-1500 termina** - prošli, današnji i budući
- **~200 recenzija** - samo za završene termine
- **~100 favorita** - klijenti čuvaju omiljene salone

### 🏙️ Gradovi

Saloni su raspoređeni po gradovima:
- Sarajevo
- Banja Luka
- Tuzla
- Zenica
- Mostar
- Bijeljina
- Brčko
- Prijedor
- Trebinje
- Doboj

### 📅 Termini

**Prošli termini** (30 dana unazad):
- 20-40 po salonu
- Status: `completed`
- Koristi se za analitiku i izvještaje

**Današnji termini**:
- 5-10 po salonu
- Status: `confirmed` ili `pending`
- Testiranje dnevnog rasporeda

**Budući termini** (30 dana unaprijed):
- 30-50 po salonu
- Status: `confirmed` ili `pending`
- Testiranje kalendara i rezervacija

### ✂️ Usluge

Svaki salon ima 6-10 usluga iz kategorija:
- **Šišanje**: Muško, Žensko, Dječije
- **Brijanje**: Brijanje, Brijanje i šišanje
- **Farbanje**: Farbanje kose, Pramenovi
- **Styling**: Feniranje, Svečana frizura
- **Tretmani**: Trajno oblikovanje, Keratinski tretman, Masaža vlasišta

### 👨‍🦰 Osoblje

Svaki salon ima 3-5 članova osoblja:
- **Glavni frizer** - najiskusniji
- **Stilista** - specijalizovan za styling
- **Frizer** - standardne usluge

Svaka osoba:
- Ima svoje radno vrijeme
- Nudi 4-8 usluga
- Ima rating i broj recenzija
- Neki imaju auto-potvrdu termina

## 🚀 Kako Pokrenuti

### 1. Resetuj Bazu (OPREZ - Briše sve podatke!)

```bash
cd backend
php artisan migrate:fresh
```

### 2. Pokreni Seeder

```bash
php artisan db:seed --class=DevelopmentDataSeeder
```

### 3. Ili Sve Odjednom

```bash
php artisan migrate:fresh --seed --seeder=DevelopmentDataSeeder
```

## 🔐 Test Kredencijali

Svi korisnici imaju **istu lozinku**: `password`

### Primjeri Email Adresa

**Klijenti:**
```
user1@example.com
user2@example.com
...
user50@example.com
```

**Vlasnici salona:**
```
user51@example.com
user52@example.com
...
user65@example.com
```

**Saloni:**
```
salon0@example.com
salon1@example.com
...
salon14@example.com
```

## 📈 Testiranje Funkcionalnosti

### 1. Analitika i Izvještaji

Sa ovoliko podataka možeš testirati:
- **Dnevni izvještaji** - današnji termini
- **Sedmični izvještaji** - prošlih 7 dana
- **Mjesečni izvještaji** - prošlih 30 dana
- **Grafovi prihoda** - po danima/sedmicama
- **Najpopularnije usluge** - po broju rezervacija
- **Najbolje osoblje** - po ratingu i broju termina
- **Zauzetost salona** - procenat popunjenosti

### 2. Kalendar i Raspoređivanje

- **Dnevni raspored** - vidi današnje termine
- **Sedmični pregled** - planiranje za narednu sedmicu
- **Mjesečni kalendar** - dugoročno planiranje
- **Slobodni termini** - provjera dostupnosti
- **Preklapanje termina** - testiranje validacije

### 3. Pretraga i Filtriranje

- **Pretraga po gradu** - 10 različitih gradova
- **Filtriranje po uslugama** - 12 različitih usluga
- **Sortiranje po ratingu** - realistični ratingi 3.5-5.0
- **Filtriranje po cijeni** - različite cjenovne kategorije
- **Dostupnost** - provjera radnog vremena

### 4. Recenzije i Ocjene

- **~200 recenzija** - različiti komentari i ocjene
- **Prosječni rating** - automatski izračunat
- **Broj recenzija** - ažuriran za salone i osoblje
- **Verifikovane recenzije** - samo od klijenata sa završenim terminima

### 5. Favoriti

- **~100 favorita** - klijenti čuvaju omiljene salone
- **Testiranje liste favorita** - prikaz omiljenih
- **Dodavanje/uklanjanje** - funkcionalnost favorita

## 🎯 Korisni Upiti za Testiranje

### Provjeri Broj Podataka

```sql
-- Ukupno korisnika
SELECT COUNT(*) FROM users;

-- Saloni po gradovima
SELECT city, COUNT(*) FROM salons GROUP BY city;

-- Termini po statusu
SELECT status, COUNT(*) FROM appointments GROUP BY status;

-- Prosječan rating salona
SELECT AVG(rating) FROM salons WHERE review_count > 0;

-- Najzauzetiji salon
SELECT s.name, COUNT(a.id) as appointments_count
FROM salons s
LEFT JOIN appointments a ON s.id = a.salon_id
GROUP BY s.id, s.name
ORDER BY appointments_count DESC
LIMIT 5;
```

### Provjeri Današnje Termine

```sql
SELECT 
    s.name as salon,
    st.name as staff,
    a.time,
    a.client_name,
    a.status
FROM appointments a
JOIN salons s ON a.salon_id = s.id
JOIN staff st ON a.staff_id = st.id
WHERE a.date = CURRENT_DATE
ORDER BY a.time;
```

### Top 5 Salona po Ratingu

```sql
SELECT name, city, rating, review_count
FROM salons
WHERE review_count > 5
ORDER BY rating DESC, review_count DESC
LIMIT 5;
```

## ⚠️ Napomene

### Performanse

- Seeder kreira **750-1500 termina** - može trajati 30-60 sekundi
- Koristi `DB::transaction()` za brže izvršavanje
- Indeksi se automatski kreiraju nakon seedovanja

### Produkcija

**NIKAD ne pokreći ovaj seeder u produkciji!**

Ovaj seeder je **samo za development i testiranje**.

### Čišćenje

Za brisanje svih podataka:

```bash
php artisan migrate:fresh
```

Za ponovno kreiranje:

```bash
php artisan migrate:fresh --seed --seeder=DevelopmentDataSeeder
```

## 🔧 Prilagođavanje

Možeš prilagoditi brojeve u seederu:

```php
// U DevelopmentDataSeeder.php

// Broj klijenata (trenutno 50)
$clients = User::factory()->count(50)->create([...]);

// Broj vlasnika/salona (trenutno 15)
$owners = User::factory()->count(15)->create([...]);

// Broj osoblja po salonu (trenutno 3-5)
$numStaff = rand(3, 5);

// Broj termina po salonu
// Prošli: rand(20, 40)
// Današnji: rand(5, 10)
// Budući: rand(30, 50)
```

## 📞 Dodatne Informacije

- Svi telefoni su u formatu: `06X-XXXXXX`
- Svi emailovi su u formatu: `userX@example.com` ili `salonX@example.com`
- Svi gradovi su stvarni gradovi u BiH
- Radna vremena su realistična (08:00-20:00)
- Cijene usluga su u rasponu 10-100 KM

## ✅ Provjera Uspješnosti

Nakon pokretanja seedera, provjeri:

1. ✅ Broj korisnika: `SELECT COUNT(*) FROM users;` (trebalo bi biti 65)
2. ✅ Broj salona: `SELECT COUNT(*) FROM salons;` (trebalo bi biti 15)
3. ✅ Broj termina: `SELECT COUNT(*) FROM appointments;` (trebalo bi biti 750-1500)
4. ✅ Broj recenzija: `SELECT COUNT(*) FROM reviews;` (trebalo bi biti ~200)
5. ✅ Login test: Pokušaj login sa `user1@example.com` / `password`

Ako sve radi - **spremno je za testiranje!** 🎉
