<?php

namespace App\Mlibms\Services;

use App\Mlibms\Models\Copy;

class CopyService
{
    /**
     * Look up copy by barcode, accession, or UUID.
     */
    public function findByBarcodeOrAccession(string $identifier): ?Copy
    {
        return Copy::with(['book.authors', 'book.publisher', 'book.primaryCategory', 'location'])
            ->where('barcode', $identifier)
            ->orWhere('accession_number', $identifier)
            ->orWhere('uuid', $identifier)
            ->first();
    }

    /**
     * Update copy condition or status.
     */
    public function updateCopyState(Copy $copy, array $data): Copy
    {
        $copy->update(array_filter([
            'location_id' => $data['location_id'] ?? null,
            'condition' => $data['condition'] ?? null,
            'status' => $data['status'] ?? null,
            'notes' => $data['notes'] ?? null,
            'replacement_cost_cents' => $data['replacement_cost_cents'] ?? null,
        ], fn($v) => !is_null($v)));

        return $copy->fresh(['book', 'location']);
    }
}
