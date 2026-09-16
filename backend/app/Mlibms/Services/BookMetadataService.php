<?php

namespace App\Mlibms\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BookMetadataService
{
    /**
     * Normalize ISBN string by removing hyphens, spaces, converting to uppercase X,
     * and trimming 18-digit EAN+5 price extension barcodes down to 13-digit ISBNs.
     */
    public function normalizeIsbn(string $isbn): string
    {
        $clean = strtoupper(trim(preg_replace('/[^0-9X]/i', '', $isbn)));

        // Handle 18-digit EAN+5 price extension barcodes (e.g. 978013235088451999)
        if (strlen($clean) >= 18 && (str_starts_with($clean, '978') || str_starts_with($clean, '979'))) {
            $clean = substr($clean, 0, 13);
        }

        return $clean;
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
     * Comprehensive metadata lookup returning structured result status:
     * - FOUND
     * - NOT_FOUND
     * - UPSTREAM_ERROR
     * - INVALID_INPUT
     *
     * Fallback sequence (No Google Books):
     * 1. Open Library Direct ISBN API (https://openlibrary.org/isbn/{isbn}.json)
     * 2. Open Library Books API
     * 3. Open Library Search API
     * 4. IT Bookstore API (Tech books fallback)
     */
    public function lookupMetadataResult(string $isbn): array
    {
        $cleanIsbn = $this->normalizeIsbn($isbn);

        if (empty($cleanIsbn) || !$this->isValidIsbn($cleanIsbn)) {
            return [
                'status' => 'INVALID_INPUT',
                'message' => 'The scanned or entered ISBN is invalid.',
                'data' => null,
                'provider' => null,
            ];
        }

        $candidates = [$cleanIsbn];
        if (strlen($cleanIsbn) === 10) {
            $converted13 = $this->convertIsbn10To13($cleanIsbn);
            if ($converted13 && !in_array($converted13, $candidates, true)) {
                $candidates[] = $converted13;
            }
        }

        $hasUpstreamError = false;
        $hasAttemptedNotFound = false;

        foreach ($candidates as $cand) {
            // 1. Open Library Direct ISBN API
            $olDirectResult = $this->lookupOpenLibraryDirectIsbn($cand);
            if ($olDirectResult['status'] === 'FOUND') {
                return [
                    'status' => 'FOUND',
                    'message' => 'Found metadata online.',
                    'data' => $olDirectResult['data'],
                    'provider' => 'Open Library Direct ISBN',
                ];
            }
            if ($olDirectResult['status'] === 'UPSTREAM_ERROR') {
                $hasUpstreamError = true;
            } elseif ($olDirectResult['status'] === 'NOT_FOUND') {
                $hasAttemptedNotFound = true;
            }

            // 2. Open Library Books API
            $olBooksResult = $this->lookupOpenLibraryBooks($cand);
            if ($olBooksResult['status'] === 'FOUND') {
                return [
                    'status' => 'FOUND',
                    'message' => 'Found metadata online.',
                    'data' => $olBooksResult['data'],
                    'provider' => 'Open Library Books',
                ];
            }
            if ($olBooksResult['status'] === 'UPSTREAM_ERROR') {
                $hasUpstreamError = true;
            } elseif ($olBooksResult['status'] === 'NOT_FOUND') {
                $hasAttemptedNotFound = true;
            }

            // 3. Open Library Search API
            $olSearchResult = $this->lookupOpenLibrarySearch($cand);
            if ($olSearchResult['status'] === 'FOUND') {
                return [
                    'status' => 'FOUND',
                    'message' => 'Found metadata online.',
                    'data' => $olSearchResult['data'],
                    'provider' => 'Open Library Search',
                ];
            }
            if ($olSearchResult['status'] === 'UPSTREAM_ERROR') {
                $hasUpstreamError = true;
            } elseif ($olSearchResult['status'] === 'NOT_FOUND') {
                $hasAttemptedNotFound = true;
            }

            // 4. IT Bookstore API (tertiary fallback)
            $itBookResult = $this->lookupItBookstore($cand);
            if ($itBookResult['status'] === 'FOUND') {
                return [
                    'status' => 'FOUND',
                    'message' => 'Found metadata online.',
                    'data' => $itBookResult['data'],
                    'provider' => 'IT Bookstore',
                ];
            }
            if ($itBookResult['status'] === 'UPSTREAM_ERROR') {
                $hasUpstreamError = true;
            } elseif ($itBookResult['status'] === 'NOT_FOUND') {
                $hasAttemptedNotFound = true;
            }
        }

        // Return UPSTREAM_ERROR only if ALL providers failed with connection/server error and none responded with NOT_FOUND
        if ($hasUpstreamError && !$hasAttemptedNotFound) {
            return [
                'status' => 'UPSTREAM_ERROR',
                'message' => 'External metadata service is temporarily unavailable.',
                'data' => null,
                'provider' => null,
            ];
        }

        Log::info('mlibms.metadata.not_found', ['isbn' => $cleanIsbn]);

        return [
            'status' => 'NOT_FOUND',
            'message' => 'No external metadata found for this ISBN.',
            'data' => null,
            'provider' => null,
        ];
    }

    /**
     * Simple metadata lookup returning array metadata or null.
     */
    public function lookupMetadata(string $isbn): ?array
    {
        $result = $this->lookupMetadataResult($isbn);
        return $result['status'] === 'FOUND' ? $result['data'] : null;
    }

    /**
     * Query Open Library Direct ISBN API (https://openlibrary.org/isbn/{isbn}.json)
     */
    private function lookupOpenLibraryDirectIsbn(string $isbn): array
    {
        $startTime = microtime(true);
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'MSA-Platform-Library/1.0 (https://msa-platform.org)'
            ])->timeout(5)->get("https://openlibrary.org/isbn/{$isbn}.json");

            $durationMs = round((microtime(true) - $startTime) * 1000);

            if ($response->status() === 404) {
                return ['status' => 'NOT_FOUND', 'data' => null];
            }

            if ($response->successful()) {
                $data = $response->json();
                if ($data && !empty($data['title'])) {
                    $authors = [];
                    if (!empty($data['authors'])) {
                        foreach ((array) $data['authors'] as $authorRef) {
                            if (is_array($authorRef) && !empty($authorRef['name'])) {
                                $authors[] = $authorRef['name'];
                            } elseif (is_array($authorRef) && !empty($authorRef['key'])) {
                                $authorName = $this->fetchOpenLibraryAuthorName($authorRef['key']);
                                if ($authorName) {
                                    $authors[] = $authorName;
                                }
                            }
                        }
                    }

                    $publishers = (array) ($data['publishers'] ?? []);
                    $publishers = array_map(fn($p) => is_array($p) ? ($p['name'] ?? '') : (string) $p, $publishers);

                    $coverUrl = null;
                    if (!empty($data['covers']) && is_array($data['covers']) && count($data['covers']) > 0) {
                        $coverId = $data['covers'][0];
                        if ($coverId > 0) {
                            $coverUrl = "https://covers.openlibrary.org/b/id/{$coverId}-M.jpg";
                        }
                    }

                    $pubYear = null;
                    if (!empty($data['publish_date']) && preg_match('/\b\d{4}\b/', (string) $data['publish_date'], $m)) {
                        $pubYear = (int) $m[0];
                    }

                    $summary = null;
                    if (!empty($data['description'])) {
                        $summary = is_array($data['description']) ? ($data['description']['value'] ?? null) : (string) $data['description'];
                    } elseif (!empty($data['notes'])) {
                        $summary = is_array($data['notes']) ? ($data['notes']['value'] ?? null) : (string) $data['notes'];
                    }

                    return [
                        'status' => 'FOUND',
                        'data' => [
                            'title' => $data['title'] ?? null,
                            'subtitle' => $data['subtitle'] ?? null,
                            'authors' => array_values(array_filter($authors)),
                            'publishers' => array_values(array_filter($publishers)),
                            'publication_year' => $pubYear,
                            'cover_image_url' => $coverUrl,
                            'summary' => $summary,
                            'isbn_13' => strlen($isbn) === 13 ? $isbn : null,
                            'isbn_10' => strlen($isbn) === 10 ? $isbn : null,
                            'provider' => 'Open Library Direct ISBN',
                        ],
                    ];
                }
                return ['status' => 'NOT_FOUND', 'data' => null];
            }

            Log::warning('mlibms.metadata.provider_failure', [
                'provider' => 'open_library_direct_isbn',
                'operation' => 'direct_isbn_api',
                'isbn' => $isbn,
                'status' => $response->status(),
                'classification' => 'upstream_error',
                'duration_ms' => $durationMs,
            ]);

            return ['status' => 'UPSTREAM_ERROR', 'data' => null];
        } catch (\Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000);
            Log::warning('mlibms.metadata.provider_failure', [
                'provider' => 'open_library_direct_isbn',
                'operation' => 'direct_isbn_api',
                'isbn' => $isbn,
                'status' => null,
                'classification' => 'upstream_error',
                'duration_ms' => $durationMs,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'UPSTREAM_ERROR', 'data' => null];
        }
    }

    private function fetchOpenLibraryAuthorName(string $authorKey): ?string
    {
        try {
            $cleanKey = ltrim($authorKey, '/');
            $response = Http::withHeaders([
                'User-Agent' => 'MSA-Platform-Library/1.0 (https://msa-platform.org)'
            ])->timeout(3)->get("https://openlibrary.org/{$cleanKey}.json");

            if ($response->successful()) {
                return $response->json('name');
            }
        } catch (\Throwable $e) {
            // Ignore author detail fetch failure
        }
        return null;
    }

    /**
     * Query Open Library Books API.
     */
    private function lookupOpenLibraryBooks(string $isbn): array
    {
        $startTime = microtime(true);
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'MSA-Platform-Library/1.0 (https://msa-platform.org)'
            ])->timeout(5)->get("https://openlibrary.org/api/books", [
                'bibkeys' => "ISBN:{$isbn}",
                'format' => 'json',
                'jscmd' => 'data',
            ]);

            $durationMs = round((microtime(true) - $startTime) * 1000);

            if ($response->status() === 404) {
                return ['status' => 'NOT_FOUND', 'data' => null];
            }

            if ($response->successful()) {
                $data = $response->json("ISBN:{$isbn}");
                if ($data && !empty($data['title'])) {
                    $authors = array_map(fn($a) => $a['name'] ?? '', $data['authors'] ?? []);
                    $publishers = array_map(fn($p) => $p['name'] ?? '', $data['publishers'] ?? []);

                    $coverUrl = $data['cover']['medium'] ?? $data['cover']['large'] ?? null;
                    if ($coverUrl && str_starts_with($coverUrl, 'http://')) {
                        $coverUrl = str_replace('http://', 'https://', $coverUrl);
                    }

                    $pubYear = null;
                    if (!empty($data['publish_date']) && preg_match('/\b\d{4}\b/', $data['publish_date'], $m)) {
                        $pubYear = (int) $m[0];
                    }

                    return [
                        'status' => 'FOUND',
                        'data' => [
                            'title' => $data['title'] ?? null,
                            'subtitle' => $data['subtitle'] ?? null,
                            'authors' => array_values(array_filter($authors)),
                            'publishers' => array_values(array_filter($publishers)),
                            'publication_year' => $pubYear,
                            'cover_image_url' => $coverUrl,
                            'summary' => is_string($data['notes'] ?? null) ? $data['notes'] : null,
                            'isbn_13' => strlen($isbn) === 13 ? $isbn : null,
                            'isbn_10' => strlen($isbn) === 10 ? $isbn : null,
                            'provider' => 'Open Library Books',
                        ],
                    ];
                }
                return ['status' => 'NOT_FOUND', 'data' => null];
            }

            Log::warning('mlibms.metadata.provider_failure', [
                'provider' => 'open_library_books',
                'operation' => 'books_api',
                'isbn' => $isbn,
                'status' => $response->status(),
                'classification' => 'upstream_error',
                'duration_ms' => $durationMs,
            ]);

            return ['status' => 'UPSTREAM_ERROR', 'data' => null];
        } catch (\Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000);
            Log::warning('mlibms.metadata.provider_failure', [
                'provider' => 'open_library_books',
                'operation' => 'books_api',
                'isbn' => $isbn,
                'status' => null,
                'classification' => 'upstream_error',
                'duration_ms' => $durationMs,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'UPSTREAM_ERROR', 'data' => null];
        }
    }

    /**
     * Query Open Library Search API.
     */
    private function lookupOpenLibrarySearch(string $isbn): array
    {
        $startTime = microtime(true);
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'MSA-Platform-Library/1.0 (https://msa-platform.org)'
            ])->timeout(5)->get('https://openlibrary.org/search.json', [
                'isbn' => $isbn,
            ]);

            $durationMs = round((microtime(true) - $startTime) * 1000);

            if ($response->status() === 404) {
                return ['status' => 'NOT_FOUND', 'data' => null];
            }

            if ($response->successful()) {
                $docs = $response->json('docs') ?? [];
                if (!empty($docs)) {
                    $doc = $docs[0];
                    $authors = (array) ($doc['author_name'] ?? []);
                    $publishers = (array) ($doc['publisher'] ?? []);

                    $coverUrl = null;
                    if (!empty($doc['cover_i'])) {
                        $coverUrl = "https://covers.openlibrary.org/b/id/{$doc['cover_i']}-M.jpg";
                    }

                    return [
                        'status' => 'FOUND',
                        'data' => [
                            'title' => $doc['title'] ?? null,
                            'subtitle' => $doc['subtitle'] ?? null,
                            'authors' => array_values(array_filter($authors)),
                            'publishers' => array_values(array_filter($publishers)),
                            'publication_year' => isset($doc['first_publish_year']) ? (int) $doc['first_publish_year'] : null,
                            'cover_image_url' => $coverUrl,
                            'summary' => null,
                            'isbn_13' => strlen($isbn) === 13 ? $isbn : null,
                            'isbn_10' => strlen($isbn) === 10 ? $isbn : null,
                            'provider' => 'Open Library Search',
                        ],
                    ];
                }
                return ['status' => 'NOT_FOUND', 'data' => null];
            }

            Log::warning('mlibms.metadata.provider_failure', [
                'provider' => 'open_library_search',
                'operation' => 'search_api',
                'isbn' => $isbn,
                'status' => $response->status(),
                'classification' => 'upstream_error',
                'duration_ms' => $durationMs,
            ]);

            return ['status' => 'UPSTREAM_ERROR', 'data' => null];
        } catch (\Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000);
            Log::warning('mlibms.metadata.provider_failure', [
                'provider' => 'open_library_search',
                'operation' => 'search_api',
                'isbn' => $isbn,
                'status' => null,
                'classification' => 'upstream_error',
                'duration_ms' => $durationMs,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'UPSTREAM_ERROR', 'data' => null];
        }
    }

    /**
     * Query IT Bookstore API.
     */
    private function lookupItBookstore(string $isbn): array
    {
        $startTime = microtime(true);
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'MSA-Platform-Library/1.0 (https://msa-platform.org)'
            ])->timeout(5)->get("https://api.itbook.store/1.0/books/{$isbn}");

            $durationMs = round((microtime(true) - $startTime) * 1000);

            if ($response->status() === 404) {
                return ['status' => 'NOT_FOUND', 'data' => null];
            }

            if ($response->successful()) {
                $data = $response->json();
                if ($data && ($data['error'] ?? '0') === '0' && !empty($data['title'])) {
                    $authors = !empty($data['authors']) ? array_map('trim', explode(',', $data['authors'])) : [];
                    $publisher = $data['publisher'] ?? null;
                    $year = !empty($data['year']) ? (int) $data['year'] : null;

                    return [
                        'status' => 'FOUND',
                        'data' => [
                            'title' => $data['title'] ?? null,
                            'subtitle' => $data['subtitle'] ?? null,
                            'authors' => array_values(array_filter($authors)),
                            'publishers' => $publisher ? [$publisher] : [],
                            'publication_year' => $year,
                            'cover_image_url' => $data['image'] ?? null,
                            'summary' => $data['desc'] ?? null,
                            'isbn_13' => $data['isbn13'] ?? (strlen($isbn) === 13 ? $isbn : null),
                            'isbn_10' => $data['isbn10'] ?? (strlen($isbn) === 10 ? $isbn : null),
                            'provider' => 'IT Bookstore',
                        ],
                    ];
                }
                return ['status' => 'NOT_FOUND', 'data' => null];
            }

            Log::warning('mlibms.metadata.provider_failure', [
                'provider' => 'it_bookstore',
                'operation' => 'books_api',
                'isbn' => $isbn,
                'status' => $response->status(),
                'classification' => 'upstream_error',
                'duration_ms' => $durationMs,
            ]);

            return ['status' => 'UPSTREAM_ERROR', 'data' => null];
        } catch (\Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000);
            Log::warning('mlibms.metadata.provider_failure', [
                'provider' => 'it_bookstore',
                'operation' => 'books_api',
                'isbn' => $isbn,
                'status' => null,
                'classification' => 'upstream_error',
                'duration_ms' => $durationMs,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'UPSTREAM_ERROR', 'data' => null];
        }
    }
}
