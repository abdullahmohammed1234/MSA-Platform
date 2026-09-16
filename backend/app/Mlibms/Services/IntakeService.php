<?php

namespace App\Mlibms\Services;

use App\Mlibms\Models\Author;
use App\Mlibms\Models\Book;
use App\Mlibms\Models\Category;
use App\Mlibms\Models\Copy;
use App\Mlibms\Models\Publisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class IntakeService
{
    /**
     * Search local catalog by ISBN-10 or ISBN-13.
     */
    public function findByIsbn(string $isbn): ?Book
    {
        $cleanIsbn = preg_replace('/[^0-9X]/i', '', $isbn);
        
        return Book::with(['authors', 'publisher', 'primaryCategory', 'copies.location'])
            ->where('isbn_13', $cleanIsbn)
            ->orWhere('isbn_10', $cleanIsbn)
            ->first();
    }

    public function __construct(
        private readonly ?BookMetadataService $metadataService = null
    ) {
    }

    /**
     * Assistive metadata lookup via multi-provider BookMetadataService.
     */
    public function lookupExternalMetadata(string $isbn): ?array
    {
        $svc = $this->metadataService ?? app(BookMetadataService::class);
        return $svc->lookupMetadata($isbn);
    }

    /**
     * Comprehensive metadata lookup returning status details.
     */
    public function lookupExternalMetadataResult(string $isbn): array
    {
        $svc = $this->metadataService ?? app(BookMetadataService::class);
        return $svc->lookupMetadataResult($isbn);
    }

    /**
     * Transactional creation of a new Book record with physical copies.
     */
    public function createBookWithCopies(array $bookPayload, array $copiesPayload = []): Book
    {
        return DB::transaction(function () use ($bookPayload, $copiesPayload) {
            $cleanIsbn13 = !empty($bookPayload['isbn_13']) ? preg_replace('/[^0-9X]/i', '', $bookPayload['isbn_13']) : null;
            $cleanIsbn10 = !empty($bookPayload['isbn_10']) ? preg_replace('/[^0-9X]/i', '', $bookPayload['isbn_10']) : null;

            $publisherId = null;
            if (!empty($bookPayload['publisher_name'])) {
                $publisher = Publisher::firstOrCreate(
                    ['name' => trim($bookPayload['publisher_name'])],
                    ['slug' => \Illuminate\Support\Str::slug($bookPayload['publisher_name'])]
                );
                $publisherId = $publisher->id;
            } elseif (!empty($bookPayload['publisher_id'])) {
                $publisherId = $bookPayload['publisher_id'];
            }

            $book = Book::create([
                'title' => trim($bookPayload['title']),
                'subtitle' => $bookPayload['subtitle'] ?? null,
                'primary_category_id' => $bookPayload['primary_category_id'] ?? null,
                'publisher_id' => $publisherId,
                'isbn_10' => $cleanIsbn10,
                'isbn_13' => $cleanIsbn13,
                'edition' => $bookPayload['edition'] ?? null,
                'publication_year' => $bookPayload['publication_year'] ?? null,
                'language' => $bookPayload['language'] ?? 'English',
                'summary' => $bookPayload['summary'] ?? null,
                'cover_image_url' => $bookPayload['cover_image_url'] ?? null,
                'default_loan_days' => $bookPayload['default_loan_days'] ?? 14,
                'is_reference_only' => $bookPayload['is_reference_only'] ?? false,
            ]);

            if (!empty($bookPayload['author_names'])) {
                foreach ((array) $bookPayload['author_names'] as $authorName) {
                    if (trim($authorName) !== '') {
                        $author = Author::firstOrCreate(
                            ['name' => trim($authorName)],
                            ['slug' => \Illuminate\Support\Str::slug($authorName)]
                        );
                        $book->authors()->attach($author->id, ['role' => 'author']);
                    }
                }
            }

            foreach ($copiesPayload as $copyData) {
                $this->addCopy(
                    book: $book,
                    locationId: $copyData['location_id'] ?? null,
                    condition: $copyData['condition'] ?? 'good',
                    acquisitionCostCents: $copyData['acquisition_cost_cents'] ?? null,
                    replacementCostCents: $copyData['replacement_cost_cents'] ?? null,
                    notes: $copyData['notes'] ?? null
                );
            }

            return $book->load(['authors', 'publisher', 'primaryCategory', 'copies.location']);
        });
    }

    /**
     * Add a physical copy to an existing book.
     */
    public function addCopy(
        Book $book,
        ?int $locationId = null,
        string $condition = 'good',
        ?int $acquisitionCostCents = null,
        ?int $replacementCostCents = null,
        ?string $notes = null
    ): Copy {
        $nextId = (Copy::max('id') ?? 0) + 1;
        do {
            $accessionNumber = 'MLIB-A-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
            $barcode = 'MLIB-C-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
            $nextId++;
        } while (Copy::where('barcode', $barcode)->orWhere('accession_number', $accessionNumber)->exists());

        return Copy::create([
            'book_id' => $book->id,
            'location_id' => $locationId,
            'accession_number' => $accessionNumber,
            'barcode' => $barcode,
            'condition' => $condition,
            'status' => 'available',
            'acquisition_date' => now()->toDateString(),
            'acquisition_cost_cents' => $acquisitionCostCents,
            'replacement_cost_cents' => $replacementCostCents,
            'notes' => $notes,
        ]);
    }
}
