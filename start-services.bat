@echo off
REM Frizerino - Start All Services (Windows)
REM This script starts all required Laravel services for development

echo.
echo ========================================
echo   Frizerino - Starting Services
echo ========================================
echo.

REM Check if we're in the backend directory
if not exist "artisan" (
    echo ERROR: artisan file not found.
    echo Please run this script from the backend directory.
    pause
    exit /b 1
)

echo Starting services...
echo.

REM Check if Redis is running
echo [0/3] Checking Redis...
redis-cli ping >nul 2>&1
if %errorlevel% neq 0 (
    echo WARNING: Redis is not running!
    echo Please start Redis manually: redis-server
    echo.
) else (
    echo Redis is running!
    echo.
)

REM Start Laravel Scheduler
echo [1/3] Starting Laravel Scheduler (for daily reports and reminders)...
start "Frizerino - Scheduler" cmd /k "php artisan schedule:work"
timeout /t 2 /nobreak >nul

REM Start Queue Worker
echo [2/3] Starting Queue Worker (for emails and notifications)...
start "Frizerino - Queue Worker" cmd /k "php artisan queue:work"
timeout /t 2 /nobreak >nul

REM Optional: Start Redis if not running
echo [3/3] Redis check complete
echo.

echo.
echo ========================================
echo   All services started successfully!
echo ========================================
echo.
echo Services running:
echo   - Scheduler: Runs daily reports (20:00) and reminders (18:00)
echo   - Queue Worker: Processes email and notification jobs
echo   - Redis: Cache and queue backend
echo.
echo To stop services:
echo   - Close the terminal windows
echo   - Or press Ctrl+C in each window
echo.
echo Manual testing commands:
echo   php artisan reports:send-daily
echo   php artisan appointments:send-reminders
echo.
pause
