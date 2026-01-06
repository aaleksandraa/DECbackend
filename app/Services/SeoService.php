<?php

namespace App\Services;

use App\Models\Salon;
use App\Models\Staff;
use Illuminate\Support\Str;

class SeoService
{
    private string $baseUrl;
    private string $siteName = 'Frizerino';
    private string $defaultDescription = 'Online zakazivanje termina za frizere, kozmetičare i salone širom Bosne i Hercegovine. Brzo, jednostavno i besplatno.';

    public function __construct()
    {
        $this->baseUrl = rtrim(config('app.frontend_url'), '/');
    }

    /**
     * Generate SEO meta tags for salon page
     */
    public function generateSalonMeta(Salon $salon): array
    {
        $description = $this->generateSalonDescription($salon);
        $keywords = $this->generateSalonKeywords($salon);
        $imageUrl = $salon->cover_image ?? $salon->logo ?? $this->baseUrl . '/default-salon.jpg';

        return [
            'title' => "{$salon->name} - {$salon->city} | {$this->siteName}",
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $this->baseUrl . '/salon/' . $salon->slug,
            'og' => [
                'title' => $salon->name,
                'description' => $description,
                'image' => $imageUrl,
                'url' => $this->baseUrl . '/salon/' . $salon->slug,
                'type' => 'business.business',
            ],
            'schema' => $this->generateSalonSchema($salon),
        ];
    }

    /**
     * Generate rich description for salon
     */
    private function generateSalonDescription(Salon $salon): string
    {
        $parts = [];

        // Base description
        if ($salon->description) {
            $parts[] = Str::limit(strip_tags($salon->description), 120);
        } else {
            $parts[] = "{$salon->name} u {$salon->city}u";
        }

        // Services
        if ($salon->services && $salon->services->count() > 0) {
            $serviceNames = $salon->services->take(3)->pluck('name')->toArray();
            $parts[] = "Usluge: " . implode(', ', $serviceNames);
        }

        // Rating
        if ($salon->rating && $salon->review_count > 0) {
            $parts[] = "Ocjena: {$salon->rating}/5 ({$salon->review_count} recenzija)";
        }

        // Location
        if ($salon->address) {
            $parts[] = "Adresa: {$salon->address}, {$salon->city}";
        }

        return implode('. ', $parts) . '. Zakažite termin online!';
    }

    /**
     * Generate keywords for salon
     */
    private function generateSalonKeywords(Salon $salon): string
    {
        $keywords = [
            $salon->name,
            $salon->city,
            'frizerski salon',
            'zakazivanje termina',
            'online rezervacija',
        ];

        // Add service-based keywords
        if ($salon->services && $salon->services->count() > 0) {
            foreach ($salon->services->take(5) as $service) {
                $keywords[] = $service->name;
            }
        }

        // Add category keywords
        $categoryKeywords = [
            'frizer',
            'kozmetičar',
            'manikir',
            'pedikir',
            'salon ljepote',
            'beauty salon',
        ];

        $keywords = array_merge($keywords, $categoryKeywords);

        return implode(', ', array_unique($keywords));
    }

    /**
     * Generate Schema.org JSON-LD for salon
     */
    private function generateSalonSchema(Salon $salon): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BeautySalon',
            'name' => $salon->name,
            'description' => $salon->description ?? "{$salon->name} - frizerski i kozmetički salon u {$salon->city}u",
            'url' => $this->baseUrl . '/salon/' . $salon->slug,
            'telephone' => $salon->phone,
            'email' => $salon->email,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $salon->address,
                'addressLocality' => $salon->city,
                'addressCountry' => 'BA',
            ],
        ];

        // Add image
        if ($salon->cover_image || $salon->logo) {
            $schema['image'] = $salon->cover_image ?? $salon->logo;
        }

        // Add rating
        if ($salon->rating && $salon->review_count > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $salon->rating,
                'reviewCount' => $salon->review_count,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        // Add opening hours if available
        if ($salon->working_hours) {
            $schema['openingHoursSpecification'] = $this->parseWorkingHours($salon->working_hours);
        }

        // Add price range
        if ($salon->services && $salon->services->count() > 0) {
            $minPrice = $salon->services->min('price');
            $maxPrice = $salon->services->max('price');
            if ($minPrice && $maxPrice) {
                $schema['priceRange'] = "{$minPrice} KM - {$maxPrice} KM";
            }
        }

        return $schema;
    }

    /**
     * Parse working hours for Schema.org format
     */
    private function parseWorkingHours($workingHours): array
    {
        // This is a simplified version - adjust based on your working_hours format
        $days = [
            'Monday' => 'Ponedjeljak',
            'Tuesday' => 'Utorak',
            'Wednesday' => 'Srijeda',
            'Thursday' => 'Četvrtak',
            'Friday' => 'Petak',
            'Saturday' => 'Subota',
            'Sunday' => 'Nedjelja',
        ];

        $specifications = [];

        foreach ($days as $en => $bs) {
            $specifications[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $en,
                'opens' => '09:00',
                'closes' => '20:00',
            ];
        }

        return $specifications;
    }

    /**
     * Generate SEO meta tags for city page
     */
    public function generateCityMeta(string $city, ?string $category = null): array
    {
        $cityName = ucfirst($city);
        $categoryName = $category ? $this->getCategoryName($category) : null;

        if ($categoryName) {
            $title = "{$categoryName} u {$cityName}u | {$this->siteName}";
            $description = "Pronađite najbolje {$categoryName} u {$cityName}u. Online zakazivanje termina, recenzije korisnika i cijene usluga. Brzo i jednostavno!";
        } else {
            $title = "Frizerski i kozmetički saloni u {$cityName}u | {$this->siteName}";
            $description = "Pronađite najbolje frizere, kozmetičare i salone u {$cityName}u. Online zakazivanje termina, recenzije korisnika i cijene usluga.";
        }

        $url = $this->baseUrl . '/saloni/' . Str::slug($city);
        if ($category) {
            $url .= '/' . $category;
        }

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $this->generateCityKeywords($cityName, $categoryName),
            'canonical' => $url,
            'og' => [
                'title' => $title,
                'description' => $description,
                'url' => $url,
                'type' => 'website',
            ],
        ];
    }

    /**
     * Generate keywords for city page
     */
    private function generateCityKeywords(string $city, ?string $category = null): string
    {
        $keywords = [
            "frizer {$city}",
            "frizerski salon {$city}",
            "kozmetički salon {$city}",
            "zakazivanje termina {$city}",
            "online rezervacija {$city}",
        ];

        if ($category) {
            $keywords[] = "{$category} {$city}";
            $keywords[] = "najbolji {$category} {$city}";
        }

        return implode(', ', $keywords);
    }

    /**
     * Get category display name
     */
    private function getCategoryName(string $slug): string
    {
        $categories = [
            'frizeri' => 'Frizeri',
            'kozmeticari' => 'Kozmetičari',
            'manikir' => 'Manikir',
            'pedikir' => 'Pedikir',
            'berber' => 'Berberi',
            'masaza' => 'Masaža',
            'depilacija' => 'Depilacija',
            'trepavice' => 'Trepavice',
            'obrve' => 'Obrve',
        ];

        return $categories[$slug] ?? ucfirst($slug);
    }

    /**
     * Generate SEO meta tags for homepage
     */
    public function generateHomepageMeta(): array
    {
        return [
            'title' => "{$this->siteName} - Online zakazivanje termina za frizere i salone",
            'description' => $this->defaultDescription,
            'keywords' => 'frizerino, frizer, frizerski salon, kozmetički salon, zakazivanje termina, online rezervacija, bosna i hercegovina, sarajevo, banja luka, mostar, tuzla',
            'canonical' => $this->baseUrl,
            'og' => [
                'title' => $this->siteName,
                'description' => $this->defaultDescription,
                'url' => $this->baseUrl,
                'type' => 'website',
                'image' => $this->baseUrl . '/og-image.jpg',
            ],
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $this->siteName,
                'description' => $this->defaultDescription,
                'url' => $this->baseUrl,
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => $this->baseUrl . '/pretraga?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
    }
}
