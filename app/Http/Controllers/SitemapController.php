<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('app.frontend_url'), '/');
    }

    /**
     * Generate the main sitemap index
     */
    public function index(): Response
    {
        $content = Cache::remember('sitemap_index', 3600, function () {
            $salonLastMod = Salon::max('updated_at');
            $staffLastMod = Staff::max('updated_at');
            $serviceLastMod = Service::max('updated_at');

            $sitemaps = [
                ['loc' => $this->baseUrl . '/sitemap-static.xml', 'lastmod' => now()->toW3cString()],
                ['loc' => $this->baseUrl . '/sitemap-cities.xml', 'lastmod' => now()->toW3cString()],
                ['loc' => $this->baseUrl . '/sitemap-salons.xml', 'lastmod' => $salonLastMod ? Carbon::parse($salonLastMod)->toW3cString() : now()->toW3cString()],
                ['loc' => $this->baseUrl . '/sitemap-staff.xml', 'lastmod' => $staffLastMod ? Carbon::parse($staffLastMod)->toW3cString() : now()->toW3cString()],
                ['loc' => $this->baseUrl . '/sitemap-services.xml', 'lastmod' => $serviceLastMod ? Carbon::parse($serviceLastMod)->toW3cString() : now()->toW3cString()],
            ];

            return $this->generateSitemapIndex($sitemaps);
        });

        return response($content, 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Static pages sitemap
     */
    public function static(): Response
    {
        $content = Cache::remember('sitemap_static', 3600, function () {
            $urls = [
                // Homepage
                ['loc' => $this->baseUrl, 'priority' => '1.0', 'changefreq' => 'daily'],
                ['loc' => $this->baseUrl . '/pretraga', 'priority' => '0.9', 'changefreq' => 'daily'],

                // Contact
                ['loc' => $this->baseUrl . '/kontakt', 'priority' => '0.7', 'changefreq' => 'monthly'],

                // Help pages
                ['loc' => $this->baseUrl . '/pomoc/kako-registrovati-salon', 'priority' => '0.6', 'changefreq' => 'monthly'],
                ['loc' => $this->baseUrl . '/pomoc/kako-zakazati-termin', 'priority' => '0.6', 'changefreq' => 'monthly'],
                ['loc' => $this->baseUrl . '/pomoc/kako-otkazati-rezervaciju', 'priority' => '0.6', 'changefreq' => 'monthly'],

                // Legal pages
                ['loc' => $this->baseUrl . '/politika-privatnosti', 'priority' => '0.4', 'changefreq' => 'yearly'],
                ['loc' => $this->baseUrl . '/uslovi-koristenja', 'priority' => '0.4', 'changefreq' => 'yearly'],

                // Auth pages (lower priority but still indexed for branded searches)
                ['loc' => $this->baseUrl . '/prijava', 'priority' => '0.5', 'changefreq' => 'monthly'],
                ['loc' => $this->baseUrl . '/registracija', 'priority' => '0.5', 'changefreq' => 'monthly'],
            ];

            return $this->generateUrlset($urls);
        });

        return response($content, 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Cities sitemap - all cities with salons
     */
    public function cities(): Response
    {
        $content = Cache::remember('sitemap_cities', 3600, function () {
            $urls = [];

            // Get all unique cities from salons
            $cities = Salon::where('status', 'approved')
                ->whereNotNull('city_slug')
                ->select('city', 'city_slug')
                ->distinct()
                ->get();

            // Categories matching frontend routes
            $categories = [
                'frizeri',
                'kozmeticari',
                'manikir',
                'pedikir',
                'berber',
                'masaza',
            ];

            foreach ($cities as $city) {
                // City main page
                $urls[] = [
                    'loc' => $this->baseUrl . '/saloni/' . $city->city_slug,
                    'priority' => '0.8',
                    'changefreq' => 'daily',
                ];

                // City + category pages
                foreach ($categories as $categorySlug) {
                    $urls[] = [
                        'loc' => $this->baseUrl . '/saloni/' . $city->city_slug . '/' . $categorySlug,
                        'priority' => '0.7',
                        'changefreq' => 'daily',
                    ];
                }
            }

            // Generic category pages (without city)
            foreach ($categories as $categorySlug) {
                $urls[] = [
                    'loc' => $this->baseUrl . '/saloni/' . $categorySlug,
                    'priority' => '0.8',
                    'changefreq' => 'daily',
                ];
            }

            // Also add locations from Location model
            $locations = Location::whereRaw('is_active = true')
                ->whereNotNull('city_slug')
                ->get();

            foreach ($locations as $location) {
                // Only add if not already added from salons
                $existingUrl = $this->baseUrl . '/saloni/' . $location->city_slug;
                if (!collect($urls)->contains('loc', $existingUrl)) {
                    $urls[] = [
                        'loc' => $existingUrl,
                        'priority' => '0.6',
                        'changefreq' => 'weekly',
                    ];

                    // Add category pages for these locations too
                    foreach ($categories as $categorySlug) {
                        $urls[] = [
                            'loc' => $this->baseUrl . '/saloni/' . $location->city_slug . '/' . $categorySlug,
                            'priority' => '0.5',
                            'changefreq' => 'weekly',
                        ];
                    }
                }
            }

            return $this->generateUrlset($urls);
        });

        return response($content, 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Salons sitemap - all individual salon pages
     */
    public function salons(): Response
    {
        $content = Cache::remember('sitemap_salons', 3600, function () {
            $urls = [];

            $salons = Salon::where('status', 'approved')
                ->whereNotNull('slug')
                ->select('slug', 'updated_at', 'rating', 'review_count')
                ->get();

            foreach ($salons as $salon) {
                // Higher priority for salons with good ratings
                $priority = '0.7';
                if ($salon->rating >= 4.5 && $salon->review_count >= 10) {
                    $priority = '0.9';
                } elseif ($salon->rating >= 4.0 && $salon->review_count >= 5) {
                    $priority = '0.8';
                }

                $urls[] = [
                    'loc' => $this->baseUrl . '/salon/' . $salon->slug,
                    'lastmod' => $salon->updated_at ? Carbon::parse($salon->updated_at)->toW3cString() : null,
                    'priority' => $priority,
                    'changefreq' => 'weekly',
                ];
            }

            return $this->generateUrlset($urls);
        });

        return response($content, 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Staff sitemap - all individual staff members (frizeri, kozmetičari, etc.)
     */
    public function staff(): Response
    {
        $content = Cache::remember('sitemap_staff', 3600, function () {
            $urls = [];

            $staffMembers = Staff::whereRaw('is_active = true')
                ->with(['salon' => function ($query) {
                    $query->where('status', 'active')->select('id', 'slug', 'name');
                }])
                ->select('id', 'name', 'salon_id', 'role', 'rating', 'review_count', 'updated_at')
                ->get();

            foreach ($staffMembers as $staff) {
                if (!$staff->salon || !$staff->salon->slug) {
                    continue;
                }

                // Staff page is salon page with staff parameter
                // URL format: /salon/{salon-slug}?staff={staff-id}
                $priority = '0.6';
                if ($staff->rating >= 4.5 && $staff->review_count >= 5) {
                    $priority = '0.8';
                } elseif ($staff->rating >= 4.0) {
                    $priority = '0.7';
                }

                $urls[] = [
                    'loc' => $this->baseUrl . '/salon/' . $staff->salon->slug . '?staff=' . $staff->id,
                    'lastmod' => $staff->updated_at ? Carbon::parse($staff->updated_at)->toW3cString() : null,
                    'priority' => $priority,
                    'changefreq' => 'weekly',
                ];
            }

            return $this->generateUrlset($urls);
        });

        return response($content, 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Services sitemap - search pages by service type
     */
    public function services(): Response
    {
        $content = Cache::remember('sitemap_services', 3600, function () {
            $urls = [];

            // Common service categories and keywords
            $serviceKeywords = [
                // Hair services
                'sisanje' => 'Šišanje',
                'farbanje-kose' => 'Farbanje kose',
                'pramenovi' => 'Pramenovi',
                'feniranje' => 'Feniranje',
                'tretman-kose' => 'Tretman kose',
                'keratinski-tretman' => 'Keratinski tretman',
                'ombre' => 'Ombre',
                'balayage' => 'Balayage',
                'musko-sisanje' => 'Muško šišanje',
                'zensko-sisanje' => 'Žensko šišanje',
                'djecije-sisanje' => 'Dječije šišanje',

                // Beard & Barbershop
                'brijanje' => 'Brijanje',
                'oblikovanje-brade' => 'Oblikovanje brade',
                'brada' => 'Brada',

                // Nails
                'manikir' => 'Manikir',
                'pedikir' => 'Pedikir',
                'gel-nokti' => 'Gel nokti',
                'akrilni-nokti' => 'Akrilni nokti',
                'lakiranje' => 'Lakiranje noktiju',

                // Face & Skin
                'tretman-lica' => 'Tretman lica',
                'ciscenje-lica' => 'Čišćenje lica',
                'mezoterapija' => 'Mezoterapija',
                'botox' => 'Botox',
                'fileri' => 'Fileri',
                'depilacija' => 'Depilacija',
                'epilacija' => 'Epilacija',

                // Makeup
                'sminkanje' => 'Šminkanje',
                'svecana-sminka' => 'Svečana šminka',
                'mladenacka-sminka' => 'Mladenačka šminka',

                // Eyebrows & Lashes
                'obrve' => 'Obrve',
                'trepavice' => 'Trepavice',
                'laminacija-obrva' => 'Laminacija obrva',
                'microblading' => 'Microblading',
                'nadogradnja-trepavica' => 'Nadogradnja trepavica',

                // Massage & Wellness
                'masaza' => 'Masaža',
                'relax-masaza' => 'Relax masaža',
                'sportska-masaza' => 'Sportska masaža',
                'anticelulitna-masaza' => 'Anticelulitna masaža',
                'spa' => 'Spa',
                'wellness' => 'Wellness',
                'sauna' => 'Sauna',
            ];

            // Get all cities for service + city combinations
            $cities = Salon::where('status', 'active')
                ->whereNotNull('city_slug')
                ->select('city', 'city_slug')
                ->distinct()
                ->get();

            // Service pages (search results for each service)
            foreach ($serviceKeywords as $slug => $name) {
                $urls[] = [
                    'loc' => $this->baseUrl . '/pretraga?usluga=' . urlencode($slug),
                    'priority' => '0.7',
                    'changefreq' => 'weekly',
                ];

                // Service + city combinations for major cities
                foreach ($cities as $city) {
                    $urls[] = [
                        'loc' => $this->baseUrl . '/pretraga?usluga=' . urlencode($slug) . '&grad=' . urlencode($city->city_slug),
                        'priority' => '0.6',
                        'changefreq' => 'weekly',
                    ];
                }
            }

            return $this->generateUrlset($urls);
        });

        return response($content, 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Generate sitemap index XML
     */
    private function generateSitemapIndex(array $sitemaps): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($sitemaps as $sitemap) {
            $xml .= '  <sitemap>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($sitemap['loc']) . '</loc>' . "\n";
            if (isset($sitemap['lastmod'])) {
                $xml .= '    <lastmod>' . $sitemap['lastmod'] . '</lastmod>' . "\n";
            }
            $xml .= '  </sitemap>' . "\n";
        }

        $xml .= '</sitemapindex>';

        return $xml;
    }

    /**
     * Generate urlset XML
     */
    private function generateUrlset(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc']) . '</loc>' . "\n";

            if (isset($url['lastmod'])) {
                $xml .= '    <lastmod>' . $url['lastmod'] . '</lastmod>' . "\n";
            }

            if (isset($url['changefreq'])) {
                $xml .= '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n";
            }

            if (isset($url['priority'])) {
                $xml .= '    <priority>' . $url['priority'] . '</priority>' . "\n";
            }

            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Clear sitemap cache
     */
    public function clearCache(): Response
    {
        Cache::forget('sitemap_index');
        Cache::forget('sitemap_static');
        Cache::forget('sitemap_cities');
        Cache::forget('sitemap_salons');
        Cache::forget('sitemap_staff');
        Cache::forget('sitemap_services');

        return response()->json(['message' => 'Sitemap cache cleared']);
    }
}
