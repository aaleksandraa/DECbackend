<?php

/**
 * Check if all requirements for registration are met
 * Run: php check_registration_requirements.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== Registration Requirements Check ===\n\n";

$allGood = true;

// 1. Check if users table exists
echo "1. Checking users table...\n";
if (Schema::hasTable('users')) {
    echo "   ✅ users table exists\n";

    // Check required columns
    $requiredColumns = ['id', 'name', 'email', 'password', 'role', 'phone', 'email_verified_at'];
    foreach ($requiredColumns as $column) {
        if (Schema::hasColumn('users', $column)) {
            echo "   ✅ Column '$column' exists\n";
        } else {
            echo "   ❌ Column '$column' MISSING\n";
            $allGood = false;
        }
    }
} else {
    echo "   ❌ users table DOES NOT EXIST\n";
    $allGood = false;
}

echo "\n";

// 2. Check if user_consents table exists
echo "2. Checking user_consents table...\n";
if (Schema::hasTable('user_consents')) {
    echo "   ✅ user_consents table exists\n";

    // Check required columns
    $requiredColumns = ['id', 'user_id', 'consent_type', 'accepted', 'version', 'ip_address', 'user_agent'];
    foreach ($requiredColumns as $column) {
        if (Schema::hasColumn('user_consents', $column)) {
            echo "   ✅ Column '$column' exists\n";
        } else {
            echo "   ❌ Column '$column' MISSING\n";
            $allGood = false;
        }
    }
} else {
    echo "   ❌ user_consents table DOES NOT EXIST\n";
    echo "   💡 Run: php artisan migrate\n";
    $allGood = false;
}

echo "\n";

// 3. Check email configuration
echo "3. Checking email configuration...\n";
$mailDriver = config('mail.default');
$mailFrom = config('mail.from.address');

echo "   Mail driver: $mailDriver\n";
echo "   Mail from: $mailFrom\n";

if ($mailDriver === 'log') {
    echo "   ⚠️  Mail driver is 'log' - emails will be logged, not sent\n";
} elseif ($mailDriver === 'smtp') {
    echo "   ✅ SMTP configured\n";
    echo "   Host: " . config('mail.mailers.smtp.host') . "\n";
    echo "   Port: " . config('mail.mailers.smtp.port') . "\n";
} else {
    echo "   ✅ Mail driver: $mailDriver\n";
}

echo "\n";

// 4. Check APP_DEBUG setting
echo "4. Checking APP_DEBUG setting...\n";
$debug = config('app.debug');
echo "   APP_DEBUG: " . ($debug ? 'true' : 'false') . "\n";
if ($debug) {
    echo "   ⚠️  Debug mode is ON - detailed errors will be shown\n";
} else {
    echo "   ✅ Debug mode is OFF - production ready\n";
}

echo "\n";

// 5. Test database connection
echo "5. Testing database connection...\n";
try {
    DB::connection()->getPdo();
    echo "   ✅ Database connection successful\n";
    echo "   Database: " . DB::connection()->getDatabaseName() . "\n";
} catch (\Exception $e) {
    echo "   ❌ Database connection FAILED\n";
    echo "   Error: " . $e->getMessage() . "\n";
    $allGood = false;
}

echo "\n";

// 6. Check if migrations are up to date
echo "6. Checking migrations status...\n";
try {
    $migrations = DB::table('migrations')->count();
    echo "   ✅ Migrations table exists\n";
    echo "   Total migrations run: $migrations\n";

    // Check for specific migration
    $userConsentsM igration = DB::table('migrations')
        ->where('migration', 'like', '%create_user_consents_table%')
        ->first();

    if ($userConsentsMigration) {
        echo "   ✅ user_consents migration has been run\n";
    } else {
        echo "   ❌ user_consents migration NOT RUN\n";
        echo "   💡 Run: php artisan migrate\n";
        $allGood = false;
    }
} catch (\Exception $e) {
    echo "   ❌ Could not check migrations\n";
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n";

// 7. Test UserConsent model
echo "7. Testing UserConsent model...\n";
try {
    $consent = new \App\Models\UserConsent();
    echo "   ✅ UserConsent model can be instantiated\n";

    // Check if recordConsent method exists
    if (method_exists(\App\Models\UserConsent::class, 'recordConsent')) {
        echo "   ✅ recordConsent method exists\n";
    } else {
        echo "   ❌ recordConsent method MISSING\n";
        $allGood = false;
    }
} catch (\Exception $e) {
    echo "   ❌ UserConsent model error\n";
    echo "   Error: " . $e->getMessage() . "\n";
    $allGood = false;
}

echo "\n";
echo "=== Summary ===\n";
if ($allGood) {
    echo "✅ All requirements are met! Registration should work.\n";
} else {
    echo "❌ Some requirements are missing. Please fix the issues above.\n";
    echo "\n";
    echo "Common fixes:\n";
    echo "1. Run migrations: php artisan migrate\n";
    echo "2. Check .env file for correct database credentials\n";
    echo "3. Make sure mail configuration is set up\n";
}

echo "\n";
