# Test Setup - Frizerino

## Preduslov

Testovi koriste PostgreSQL bazu. Trebate:
1. PostgreSQL instaliran i pokrenut
2. Test baza kreirana
3. Testovi se pokrenuti sa `php artisan test`

## Setup Test Baze

### 1. Kreiraj test bazu

```bash
# Konekcija na PostgreSQL
psql -U postgres

# Kreiraj test bazu
CREATE DATABASE frizerino_test;

# Izlaz
\q
```

### 2. Konfiguracija phpunit.xml

Već je konfiguriran sa:
```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_HOST" value="localhost"/>
<env name="DB_PORT" value="5432"/>
<env name="DB_DATABASE" value="frizerino_test"/>
<env name="DB_USERNAME" value="postgres"/>
<env name="DB_PASSWORD" value=""/>
```

Ako koristiš drugačite kredencijale, ažuriraj `phpunit.xml`.

### 3. Pokretanje Testova

```bash
# Svi testovi
php artisan test

# Samo Feature testovi
php artisan test tests/Feature

# Samo Unit testovi
php artisan test tests/Unit

# Specifičan test file
php artisan test tests/Feature/PublicSearchTest.php

# Sa verbose outputom
php artisan test --verbose

# Sa coverage reportom
php artisan test --coverage
```

## Kako Testovi Rade

1. **RefreshDatabase Trait**: Prije svakog testa, baza se resetuje
   - Sve migracije se pokrenute
   - Sve seederi se pokrenute (ako postoje)
   - Baza je čista za svaki test

2. **Factories**: Testovi koriste factories za kreiranje test podataka
   - `Salon::factory()->create()`
   - `Staff::factory()->create()`
   - `User::factory()->create()`
   - itd.

3. **Assertions**: Testovi provjeravaju rezultate
   - HTTP status kodove
   - JSON strukturu
   - Bazu podataka

## Važne Napomene

⚠️ **VAŽNO**: Test baza `frizerino_test` će biti **obrisana i ponovno kreirana** prije svakog test pokretanja!

- Nikada ne koristi test bazu za produkcijske podatke
- Test baza je samo za testiranje
- Produkcijska baza `frizerino` ostaje nepromijenjena

## Troubleshooting

### Greška: "SQLSTATE[08006]: Connection refused"

```
Rješenje: PostgreSQL nije pokrenut
- Provjeri da li je PostgreSQL servis pokrenut
- Na Windows: Services > PostgreSQL
- Na Linux: sudo systemctl start postgresql
- Na Mac: brew services start postgresql
```

### Greška: "SQLSTATE[3D000]: Invalid catalog name: 7 ERROR: database "frizerino_test" does not exist"

```
Rješenje: Test baza nije kreirana
- Kreiraj test bazu:
  psql -U postgres -c "CREATE DATABASE frizerino_test;"
```

### Greška: "SQLSTATE[28P01]: Invalid password"

```
Rješenje: Pogrešna lozinka
- Ažuriraj phpunit.xml sa ispravnom lozinkom
- Ili koristi .env.testing file
```

### Testovi su spori

```
Rješenje: Koristi --parallel flag
php artisan test --parallel
```

## Konfiguracija sa .env.testing

Alternativno, možeš kreirati `.env.testing` file:

```env
APP_ENV=testing
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=frizerino_test
DB_USERNAME=postgres
DB_PASSWORD=
CACHE_STORE=array
QUEUE_CONNECTION=sync
```

Zatim pokreni:
```bash
php artisan test --env=testing
```

## CI/CD Integration

Za GitHub Actions, dodaj u `.github/workflows/tests.yml`:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      postgres:
        image: postgres:15
        env:
          POSTGRES_PASSWORD: postgres
          POSTGRES_DB: frizerino_test
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
        ports:
          - 5432:5432

    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pgsql, pdo_pgsql
      
      - name: Install dependencies
        run: composer install
      
      - name: Run tests
        run: php artisan test --coverage
        env:
          DB_CONNECTION: pgsql
          DB_HOST: localhost
          DB_DATABASE: frizerino_test
          DB_USERNAME: postgres
          DB_PASSWORD: postgres
```

## Status

✅ **READY** - Testovi su spreman za pokretanje

**Sljedeći koraci**:
1. Kreiraj test bazu: `CREATE DATABASE frizerino_test;`
2. Pokreni testove: `php artisan test`
3. Provjeri rezultate
