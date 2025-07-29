<?php

namespace App\Services;

class DomainNormalizer
{
    public static function normalize(string $url): string
    {
        // Remove protocol and www prefix
        $domain = preg_replace('#^https?://(?:www\.)?(.+)#i', '$1', $url);
        $domain = explode('/', $domain)[0];
        return strtolower(trim($domain));
    }
}
