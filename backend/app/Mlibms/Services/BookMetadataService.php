<?php

namespace App\Mlibms\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BookMetadataService
{
    /**
     * Normalize ISBN string by removing hyphens, spaces, and converting to uppercase X.
     */
    public function normalizeIsbn(string $isbn): string
    {
        return strtoupper(trim(preg_replace('/[^0-9X]/i', '', $isbn)));
    }

    /**
     * Convert ISBN-10 to ISBN-13 if applicable.
     */
    public function convertIsbn10To13(string $isbn10): ?string
    {
        $clean = $this->normalizeIsbn($isbn10);
        if (strlen($clean) !== 10) {
            return null;
        }

        $prefix = '978' . substr($clean, 0, 9);
        $checksum = 0;
        for ($i = 0; $i < 12; $i++) {
            $checksum += (int) $prefix[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        $checkDigit = (10 - ($checksum % 10)) % 10;

        return $prefix . $checkDigit;
    }

    /**
     * Validate ISBN-10 or ISBN-13 format & checksum.
     */
    public function isValidIsbn(string $isbn): bool
    {
        $clean = $this->normalizeIsbn($isbn);
        if (strlen($clean) === 10) {
            $checksum = 0;
            for ($i = 0; $i < 9; $i++) {
                $checksum += (int) $clean[$i] * (10 - $i);
            }
            $lastChar = $clean[9];
            $checksum += ($lastChar === 'X') ? 10 : (int) $lastChar;

            return ($checksum % 11) === 0;
        }

        if (strlen($clean) === 13) {
            $checksum = 0;
            for ($i = 0; $i < 12; $i++) {
                $checksum += (int) $clean[$i] * ($i % 2 === 0 ? 1 : 3);
            }
            $checkDigit = (10 - ($checksum % 10)) % 10;

            return $checkDigit === (int) $clean[12];
        }

        return false;
    }

    /**
     * Lookup metadata across multiple providers with graceful fallback.
     *
     * Order of providers:
     * 1. Google Books API
     * 2. Open Library API
     * 3. Fallback (Manual entry prompt / empty array)
     */
    public function lookupMetadata(string $isbn): ?array
    {
        $cleanIsbn = $this->normalizeIsbn($isbn);

        if (empty($cleanIsbn)) {
            return null;
        }

        // Try Primary Provider: Google Books API
        $googleData = $this->lookupGoogleBooks($cleanIsbn);
        if ($googleData !== null) {
            return $googleData;
        }

        // Try Secondary Provider: Open Library API
        $openLibraryData = $this->lookupOpenLibrary($cleanIsbn);
        if ($openLibraryData !== null) {
            return $openLibraryData;
        }

        // If ISBN-10 was supplied, convert to ISBN-13 and try again
        if (strlen($cleanIsbn) === 10) {
            $isbn13 = $this->convertIsbn10To13($cleanIsbn);
            if ($isbn13 !== null) {
                $googleData13 = $this->lookupGoogleBooks($isbn13);
                if ($googleData13 !== null) {
                    return $googleData13;
                }

                $openLibraryData13 = $this->lookupOpenLibrary($isbn13);
                if ($openLibraryData13 !== null) {
                    return $openLibraryData13;
                }
            }
        }

        Log::info('mlibms.metadata_lookup.no_provider_found', ['isbn' => $cleanIsbn]);

        return null;
    }

    /**
     * Query Google Books API.
     */
    private function lookupGoogleBooks(string $isbn): ?array
    {
        try {
            $response = Http::timeout(4)->get('https://www.googleapis.com/books/v1/volumes', [
                'q' => "isbn:{$isbn}",
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $items = $data['items'] ?? [];
                if (!empty($items)) {
                    $info = $items[0]['volumeInfo'] ?? [];
                    $authors = $info['authors'] ?? [];
                    $publisher = $info['publisher'] ?? null;
                    $pubDate = $info['publishedDate'] ?? null;
                    $year = null;
                    if ($pubDate && preg_match('/^\d{4}/', $pubDate, $matches)) {
                        $year = (int) $matches[0];
                    }

                    $isbn10 = null;
                    $isbn13 = null;
                    $industryIds = $info['industryIdentifiers'] ?? [];
                    foreach ($industryIds as $idObj) {
                        if (($idObj['type'] ?? '') === 'ISBN_10') {
                            $isbn10 = $idObj['identifier'] ?? null;
                        }
                        if (($idObj['type'] ?? '') === 'ISBN_13') {
                            $isbn13 = $idObj['identifier'] ?? null;
                        }
                    }

                    $imageLinks = $info['imageLinks'] ?? [];
                    $coverUrl = $imageLinks['thumbnail'] ?? $imageLinks['smallThumbnail'] ?? null;
                    if ($coverUrl) {
                        $coverUrl = str_replace('http://', 'https://', $coverUrl);
                    }

                    return [
                        'title' => $info['title'] ?? null,
                        'subtitle' => $info['subtitle'] ?? null,
                        'authors' => array_values(array_filter($authors)),
                        'publishers' => $publisher ? [$publisher] : [],
                        'publication_year' => $year,
                        'cover_image_url' => $coverUrl,
                        'summary' => $info['description'] ?? null,
                        'isbn_13' => $isbn13 ?? (strlen($isbn) === 13 ? $isbn : null),
                        'isbn_10' => $isbn10 ?? (strlen($isbn) === 10 ? $isbn : null),
                        'provider' => 'Google Books',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('mlibms.metadata_provider_error.google_books', [
                'isbn' => $isbn,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Query Open Library API.
     */
    private function lookupOpenLibrary(string $isbn): ?array
    {
        try {
            $response = Http::timeout(4)->get("https://openlibrary.org/api/books", [
                'bibkeys' => "ISBN:{$isbn}",
                'format' => 'json',
                'jscmd' => 'data',
            ]);

            if ($response->successful()) {
                $data = $response->json("ISBN:{$isbn}");
                if ($data) {
                    $authors = array_map(fn($a) => $a['name'] ?? '', $data['authors'] ?? []);
                    $publishers = array_map(fn($p) => $p['name'] ?? '', $data['publishers'] ?? []);

                    return [
                        'title' => $data['title'] ?? null,
                        'subtitle' => $data['subtitle'] ?? null,
                        'authors' => array_values(array_filter($authors)),
                        'publishers' => array_values(array_filter($publishers)),
                        'publication_year' => isset($data['publish_date']) ? (int) preg_replace('/[^0-9]/', '', $data['publish_date']) : null,
                        'cover_image_url' => $data['cover']['medium'] ?? $data['cover']['large'] ?? null,
                        'summary' => is_string($data['notes'] ?? null) ? $data['notes'] : null,
                        'isbn_13' => strlen($isbn) === 13 ? $isbn : null,
                        'isbn_10' => strlen($isbn) === 10 ? $isbn : null,
                        'provider' => 'Open Library',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('mlibms.metadata_provider_error.open_library', [
                'isbn' => $isbn,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
