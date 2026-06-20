<?php

namespace App\Repositories\CMS;

use App\Models\CMS\HomepageSection;
use App\Models\CMS\HomepageContentBlock;

class HomepageRepository
{
    public function getSectionsWithBlocks()
    {
        return HomepageSection::with('blocks')->orderBy('display_order')->get();
    }

    public function findSectionByKey(string $key): ?HomepageSection
    {
        return HomepageSection::where('key', $key)->first();
    }

    public function updateBlockValue(HomepageSection $section, string $key, ?string $value): bool
    {
        $block = $section->blocks()->where('key', $key)->first();
        if ($block) {
            return $block->update(['value' => $value]);
        }
        return false;
    }
}
