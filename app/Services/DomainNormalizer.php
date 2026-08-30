<?php

namespace App\Services;

use Illuminate\Support\Str;

class DomainNormalizer
{
    public function domain(?string $website): ?string
    {
        if (blank($website)) {
            return null;
        }

        $url = Str::startsWith(trim($website), ['http://', 'https://']) ? trim($website) : 'https://'.trim($website);
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? Str::of($host)->lower()->trim('.')->replaceStart('www.', '')->toString() : null;
    }
}
