<?php

use App\Http\Controllers\SitemapController;
use App\Http\Controllers\RobotsController;
use Illuminate\Support\Facades\Route;

// Sitemap routes
Route::get('/sitemap.xml', [SitemapController::class, 'index']);
Route::get('/sitemap-static.xml', [SitemapController::class, 'static']);
Route::get('/sitemap-cities.xml', [SitemapController::class, 'cities']);
Route::get('/sitemap-salons.xml', [SitemapController::class, 'salons']);
Route::get('/sitemap-staff.xml', [SitemapController::class, 'staff']);
Route::get('/sitemap-services.xml', [SitemapController::class, 'services']);

// Robots.txt
Route::get('/robots.txt', [RobotsController::class, 'index']);

Route::get('/', function () {
    return view('welcome');
});
