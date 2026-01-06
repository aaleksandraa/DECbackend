<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    /**
     * Generate robots.txt file
     */
    public function index(): Response
    {
        $baseUrl = rtrim(config('app.frontend_url'), '/');
        $env = config('app.env');

        // In production, allow all bots
        // In development/staging, disallow all
        if ($env === 'production') {
            $content = $this->productionRobots($baseUrl);
        } else {
            $content = $this->developmentRobots();
        }

        return response($content, 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Production robots.txt - allow all search engines
     */
    private function productionRobots(string $baseUrl): string
    {
        return <<<ROBOTS
# Frizerino - Online zakazivanje termina za frizere i salone
# https://frizerino.com

# Allow all search engines
User-agent: *
Allow: /

# Disallow admin and private areas
Disallow: /admin
Disallow: /dashboard
Disallow: /api/
Disallow: /login
Disallow: /register
Disallow: /reset-password

# Disallow search result pages with parameters (avoid duplicate content)
Disallow: /*?*sort=
Disallow: /*?*page=
Disallow: /*?*filter=

# Allow important pages
Allow: /salon/*
Allow: /saloni/*
Allow: /pretraga
Allow: /kontakt
Allow: /pomoc/*
Allow: /o-nama
Allow: /za-salone
Allow: /cjenovnik

# Crawl delay (be nice to servers)
Crawl-delay: 1

# Sitemap location
Sitemap: {$baseUrl}/sitemap.xml

# Specific bot rules
User-agent: Googlebot
Allow: /
Crawl-delay: 0

User-agent: Bingbot
Allow: /
Crawl-delay: 1

User-agent: Slurp
Allow: /
Crawl-delay: 1

# Block bad bots
User-agent: AhrefsBot
Crawl-delay: 10

User-agent: SemrushBot
Crawl-delay: 10

User-agent: MJ12bot
Disallow: /

User-agent: DotBot
Disallow: /
ROBOTS;
    }

    /**
     * Development robots.txt - disallow all search engines
     */
    private function developmentRobots(): string
    {
        return <<<ROBOTS
# Development/Staging Environment
# Do not index this site

User-agent: *
Disallow: /
ROBOTS;
    }
}
