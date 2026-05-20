<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\DirectoryService;
use App\Support\View;

final class DirectoryController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('directory/index', [
            'featured' => DirectoryService::featured(),
            'cities' => DirectoryService::cities(),
        ]));
    }

    public function citySpecialty(Request $request, string $city, string $specialty): Response
    {
        $doctors = DirectoryService::byCitySpecialty($city, $specialty);

        return Response::html(View::render('directory/city', [
            'city' => $city,
            'specialty' => $specialty,
            'doctors' => $doctors,
        ]));
    }

    public function profile(Request $request, string $slug): Response
    {
        $data = DirectoryService::findBySlug($slug);
        if ($data === null) {
            return Response::html('Doctor not found', 404);
        }

        return Response::html(View::render('directory/profile', $data + [
            'jsonLd' => $this->jsonLd($data),
        ]));
    }

    /** @param array<string, mixed> $data */
    private function jsonLd(array $data): string
    {
        $p = $data['profile'];
        $loc = $data['locations'][0] ?? [];
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Physician',
            'name' => $p['full_name'] ?? '',
            'medicalSpecialty' => $p['specialty_primary'] ?? '',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $loc['city'] ?? '',
                'streetAddress' => $loc['address'] ?? '',
            ],
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => (float) ($p['avg_rating'] ?? 0),
                'reviewCount' => (int) ($p['total_reviews'] ?? 0),
            ],
        ];

        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
