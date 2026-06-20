<?php

namespace App\Services\CMS;

use App\Repositories\CMS\HomepageRepository;
use App\Models\CMS\HomepageSection;

class HomepageService
{
    protected $homepageRepository;
    protected $revisionService;

    public function __construct(HomepageRepository $homepageRepository, RevisionService $revisionService)
    {
        $this->homepageRepository = $homepageRepository;
        $this->revisionService = $revisionService;
    }

    public function getSections()
    {
        return $this->homepageRepository->getSectionsWithBlocks();
    }

    public function getHomepageData(): array
    {
        $sections = $this->homepageRepository->getSectionsWithBlocks();
        $data = [];

        foreach ($sections as $section) {
            if (!$section->is_visible || $section->status !== 'published') {
                continue;
            }

            $sectionData = [];
            foreach ($section->blocks as $block) {
                // If it's offering structure, we can organize it on frontend or keep raw key-values
                $sectionData[$block->key] = $block->value;
            }
            $data[$section->key] = $sectionData;
        }

        return $data;
    }

    public function updateSection(string $key, array $blocks, ?int $userId): bool
    {
        $section = $this->homepageRepository->findSectionByKey($key);
        if (!$section) {
            return false;
        }

        // Keep old state in payload for auditing
        $oldBlocks = [];
        foreach ($section->blocks as $block) {
            $oldBlocks[$block->key] = $block->value;
        }

        foreach ($blocks as $blockKey => $value) {
            $this->homepageRepository->updateBlockValue($section, $blockKey, $value);
        }

        $this->revisionService->logAction(
            $userId,
            'update_homepage',
            $section,
            "Updated homepage section: {$section->name}",
            ['old' => $oldBlocks, 'new' => $blocks]
        );

        return true;
    }
}
