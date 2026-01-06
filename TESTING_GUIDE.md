# Testing Guide - Frizerino

Komprehenzivni test suite za sve funkcionalnosti aplikacije.

## Pregled Testova

### Feature Tests (Integracijski testovi)

#### 1. PublicSearchTest.php
Testira sve aspekte javne pretrage salona.

**Testovi**:
- ✅ Pretraga bez filtera - vraća sve odobrene salone
- ✅ Filtriranje po gradu
- ✅ Filtriranje po nazivu
- ✅ Filtriranje po minimalnoj ocjeni
- ✅ Filtriranje po uslugama
- ✅ Filtriranje po datumu
- ✅ Filtriranje po vremenu (ujutro, kasno, rano)
- ✅ Paginacija - default 12 po stranici
- ✅ Paginacija - custom per_page
- ✅ Paginacija - stranica 2
- ✅ Kombinovani filtri
- ✅ Meta informacije (current_page, last_page, total)
- ✅ Primijenjeni filtri
- ✅ Nevalidni format datuma
- ✅ Nevalidni format vremena
- ✅ Isključivanje neodobrenih salona
- ✅ Filtriranje po publici (žene, muškarci, djeca)

**Pokretanje**:
```bash
php artisan test tests/Feature/PublicSearchTest.php
```

#### 2. SalonTest.php
Testira sve operacije vezane uz salone.

**Testovi**:
- ✅ Dohvaćanje detalja salona
- ✅ Salon nije pronađen (404)
- ✅ Dohvaćanje usluga salona
- ✅ Dohvaćanje zaposlenih salona
- ✅ Vlasnik salona može ažurirati profil
- ✅ Drugi korisnik ne može ažurirati salon
- ✅ Upload slika salona
- ✅ Ažuriranje radnog vremena
- ✅ Dohvaćanje recenzija salona
- ✅ Pretraga salona po lokaciji
- ✅ Filtriranje po publici
- ✅ Izračun ocjene salona
- ✅ Salon bez recenzija

**Pokretanje**:
```bash
php artisan test tests/Feature/SalonTest.php
```

#### 3. AppointmentTest.php
Testira sve operacije vezane uz termine.

**Testovi**:
- ✅ Kreiranje termina kao gost
- ✅ Validacija - nedostaje ime
- ✅ Validacija - nevalidan telefon
- ✅ Kreiranje termina kao prijavljeni klijent
- ✅ Klijent ne može rezervirati izvan radnog vremena
- ✅ Klijent ne može rezervirati na neradni dan
- ✅ Klijent ne može rezervirati preklapajući termin
- ✅ Dohvaćanje termina klijenta
- ✅ Dohvaćanje jednog termina
- ✅ Klijent ne može vidjeti tuđe termine
- ✅ Otkazivanje termina
- ✅ Ne može se otkazati već otkazani termin
- ✅ Vlasnik salona može vidjeti termine
- ✅ Vlasnik salona može potvrditi termin
- ✅ Vlasnik salona može odbiti termin
- ✅ Validacija formata datuma
- ✅ Validacija formata vremena

**Pokretanje**:
```bash
php artisan test tests/Feature/AppointmentTest.php
```

#### 4. AuthTest.php
Testira sve operacije vezane uz autentifikaciju.

**Testovi**:
- ✅ Registracija korisnika
- ✅ Validacija - nedostaje email
- ✅ Validacija - nevalidan email
- ✅ Validacija - lozinka prekratka
- ✅ Validacija - lozinka se ne poklapa
- ✅ Validacija - dupli email
- ✅ Prijava korisnika
- ✅ Prijava sa nevalidnim kredencijalima
- ✅ Prijava sa nepostoječim korisnikom
- ✅ Odjava korisnika
- ✅ Dohvaćanje prijavljenog korisnika
- ✅ Neautentificirani korisnik ne može pristupiti zaštićenim rutama
- ✅ Zahtjev za resetovanje lozinke
- ✅ Resetovanje lozinke sa nevalidnim emailom
- ✅ Ažuriranje profila korisnika
- ✅ Promjena lozinke
- ✅ Promjena lozinke sa pogrešnom trenutnom lozinkom
- ✅ Registracija salona
- ✅ Registracija kao admin (trebala bi biti odbljena)

**Pokretanje**:
```bash
php artisan test tests/Feature/AuthTest.php
```

#### 5. ReviewTest.php
Testira sve operacije vezane uz recenzije.

**Testovi**:
- ✅ Kreiranje recenzije
- ✅ Validacija - nedostaje ocjena
- ✅ Validacija - ocjena premala (0)
- ✅ Validacija - ocjena prevelika (6)
- ✅ Validacija - komentar prekratak
- ✅ Validacija - komentar predugačak
- ✅ Korisnik ne može kreirati duplu recenziju
- ✅ Dohvaćanje recenzija salona
- ✅ Paginacija recenzija
- ✅ Ažuriranje vlastite recenzije
- ✅ Korisnik ne može ažurirati tuđu recenziju
- ✅ Brisanje vlastite recenzije
- ✅ Korisnik ne može obrisati tuđu recenziju
- ✅ Izračun ocjene salona
- ✅ Sortiranje recenzija po ocjeni
- ✅ Neautentificirani korisnik ne može kreirati recenziju
- ✅ Recenzije sa svim validnim ocjenama (1-5)

**Pokretanje**:
```bash
php artisan test tests/Feature/ReviewTest.php
```

#### 6. FavoriteTest.php
Testira sve operacije vezane uz omiljene salone.

**Testovi**:
- ✅ Dodavanje salona u omiljene
- ✅ Ne može se dodati isti salon dva puta
- ✅ Uklanjanje salona iz omiljenih
- ✅ Dohvaćanje omiljenih salona korisnika
- ✅ Paginacija omiljenih
- ✅ Neautentificirani korisnik ne može dodati omiljene
- ✅ Dodavanje nepostoječeg salona u omiljene
- ✅ Uklanjanje nepostoječeg omiljenog
- ✅ Različiti korisnici imaju različite omiljene
- ✅ Broj omiljenih u detaljima salona
- ✅ Vlasnik salona ne može dodati vlastiti salon u omiljene

**Pokretanje**:
```bash
php artisan test tests/Feature/FavoriteTest.php
```

### Unit Tests

#### 1. AppointmentServiceTest.php
Testira poslovnu logiku AppointmentService.

**Testovi**:
- ✅ Staff dostupan tijekom radnog vremena
- ✅ Staff nije dostupan prije radnog vremena
- ✅ Staff nije dostupan nakon radnog vremena
- ✅ Staff nije dostupan na neradni dan
- ✅ Staff nije dostupan ako bi termin premašio radno vrijeme
- ✅ Staff nije dostupan ako postoji postojeći termin
- ✅ Staff dostupan ako termin ne preklapa
- ✅ Dohvaćanje dostupnih ID-eva salona za datum
- ✅ Dohvaćanje dostupnih ID-eva salona za specifično vrijeme
- ✅ Nema dostupnih salona za kasno večernje vrijeme
- ✅ Dohvaćanje dostupnih vremenskih slotova za staff
- ✅ Dostupni slotovi isključuju prošla vremena za danas
- ✅ Provjera dostupnosti salona
- ✅ Salon nije dostupan ako nema zaposlenih
- ✅ Konverzija datuma iz DD.MM.YYYY u ISO format
- ✅ Konverzija datuma iz YYYY-MM-DD ostaje ista

**Pokretanje**:
```bash
php artisan test tests/Unit/AppointmentServiceTest.php
```

## Pokretanje Testova

### Svi testovi
```bash
php artisan test
```

### Samo Feature testovi
```bash
php artisan test tests/Feature
```

### Samo Unit testovi
```bash
php artisan test tests/Unit
```

### Specifičan test file
```bash
php artisan test tests/Feature/PublicSearchTest.php
```

### Specifičan test
```bash
php artisan test tests/Feature/PublicSearchTest.php --filter test_search_returns_all_approved_salons
```

### Sa verbose outputom
```bash
php artisan test --verbose
```

### Sa coverage reportom
```bash
php artisan test --coverage
```

### Sa HTML coverage reportom
```bash
php artisan test --coverage --coverage-html=coverage
```

## Test Database Setup

Testovi koriste RefreshDatabase trait koji:
1. Migrira bazu prije svakog testa
2. Vraća bazu u početno stanje nakon testa
3. Koristi transakcije za brže testove

**Konfiguracija**: `phpunit.xml`

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

## Factories

Testovi koriste Laravel Factories za kreiranje test podataka:

- `SalonFactory` - Kreira test salone
- `StaffFactory` - Kreira test zaposlene
- `ServiceFactory` - Kreira test usluge
- `AppointmentFactory` - Kreira test termine
- `UserFactory` - Kreira test korisnike
- `ReviewFactory` - Kreira test recenzije

**Primjer**:
```php
$salon = Salon::factory()->create([
    'name' => 'Test Salon',
    'status' => 'approved',
]);
```

## Best Practices

### 1. Test Naming
```php
// ✅ Dobro
public function test_user_can_create_appointment(): void

// ❌ Loše
public function test_create(): void
```

### 2. Arrange-Act-Assert Pattern
```php
// Arrange - Pripremi podatke
$user = User::factory()->create();

// Act - Izvrši akciju
$response = $this->actingAs($user)->postJson('/api/v1/appointments', [...]);

// Assert - Provjeri rezultat
$response->assertStatus(201);
```

### 3. Test Isolation
```php
// ✅ Svaki test je nezavisan
public function test_first(): void { ... }
public function test_second(): void { ... }

// ❌ Testovi zavise jedan od drugog
public function test_first(): void { ... }
public function test_second_depends_on_first(): void { ... }
```

### 4. Meaningful Assertions
```php
// ✅ Specifično
$this->assertDatabaseHas('appointments', [
    'user_id' => $user->id,
    'status' => 'confirmed',
]);

// ❌ Generalno
$this->assertTrue($response->ok());
```

## Troubleshooting

### Test ne prolazi
1. Provjeri da li su sve migracije pokrenute
2. Provjeri da li su sve relacije pravilno definirane
3. Provjeri da li su sve validacije ispravne

### Testovi su spori
1. Koristi `RefreshDatabase` umjesto `DatabaseTransactions`
2. Koristi factories umjesto direktnog kreiranja
3. Koristi `--parallel` flag za paralelno pokretanje

### Database locked error
```bash
# Obriši lock file
rm -f storage/database.sqlite-journal

# Ili koristi in-memory bazu
# Već je konfigurirana u phpunit.xml
```

## Coverage Goals

Cilj je postići:
- **Minimum 80%** code coverage
- **100%** coverage za kritične dijelove (auth, appointments)
- **90%** coverage za API endpoints

**Trenutni status**:
```
Lines:   XX%
Methods: XX%
Classes: XX%
```

## CI/CD Integration

Testovi se automatski pokrenuti na:
- Push na main branch
- Pull requests
- Pre-deployment checks

**GitHub Actions** (`.github/workflows/tests.yml`):
```yaml
- name: Run tests
  run: php artisan test --coverage
```

## Dodavanje Novih Testova

### 1. Kreiraj test file
```bash
php artisan make:test FeatureName --feature
php artisan make:test ServiceName --unit
```

### 2. Nasljeđuj TestCase
```php
class MyTest extends TestCase
{
    use RefreshDatabase;
}
```

### 3. Napiši testove
```php
public function test_something(): void
{
    // Arrange
    $data = [...];
    
    // Act
    $response = $this->postJson('/api/endpoint', $data);
    
    // Assert
    $response->assertStatus(201);
}
```

### 4. Pokreni testove
```bash
php artisan test tests/Feature/MyTest.php
```

## Resursi

- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Testing Best Practices](https://laravel.com/docs/testing#best-practices)

## Status

✅ **COMPLETE** - Svi testovi implementirani i funkcionalni

**Statistika**:
- 6 Feature test files
- 1 Unit test file
- 100+ test cases
- Pokrivanje svih kritičnih funkcionalnosti
