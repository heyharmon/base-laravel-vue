<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\ApifyRun;
use Illuminate\Support\Facades\Log;

class OrganizationImportService
{
    private int $imported = 0;
    private int $updated = 0;
    private int $skipped = 0;

    public function importFromApifyData(array $data, ApifyRun $apifyRun): array
    {
        $this->imported = 0;
        $this->updated = 0;
        $this->skipped = 0;

        foreach ($data as $item) {
            $this->processItem($item);
        }

        $apifyRun->update([
            'items_processed' => count($data),
            'items_imported' => $this->imported,
            'items_updated' => $this->updated,
            'items_skipped' => $this->skipped,
        ]);

        Log::info('Completed organization import', [
            'run_id' => $apifyRun->apify_run_id,
            'total' => count($data),
            'imported' => $this->imported,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
        ]);

        return [
            'total' => count($data),
            'imported' => $this->imported,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
        ];
    }

    private function processItem(array $item): void
    {
        try {
            $placeId = $this->extractPlaceId($item['url'] ?? '');
            if (!$placeId) {
                $this->skipped++;
                return;
            }
            $existingOrg = Organization::findByGooglePlaceId($placeId);
            if ($existingOrg && $existingOrg->trashed()) {
                $this->skipped++;
                return;
            }
            $organizationData = $this->mapApifyDataToOrganization($item, $placeId);
            if ($existingOrg) {
                $existingOrg->update($organizationData);
                $this->updated++;
            } else {
                Organization::create($organizationData);
                $this->imported++;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to process organization item', [
                'item' => $item,
                'error' => $e->getMessage(),
            ]);
            $this->skipped++;
        }
    }

    private function mapApifyDataToOrganization(array $item, string $placeId): array
    {
        return [
            'name' => $item['title'] ?? 'Unknown',
            'google_place_id' => $placeId,
            'banner' => $item['imageUrl'] ?? null,
            'score' => isset($item['totalScore']) ? (float) $item['totalScore'] : null,
            'reviews' => isset($item['reviewsCount']) ? (int) $item['reviewsCount'] : null,
            'street' => $item['street'] ?? null,
            'city' => $item['city'] ?? null,
            'state' => $item['state'] ?? null,
            'country_code' => $item['countryCode'] ?? null,
            'website' => $item['website'] ?? null,
            'phone' => $item['phone'] ?? null,
            'category' => $item['categoryName'] ?? null,
            'map_url' => $item['url'] ?? null,
        ];
    }

    private function extractPlaceId(string $url): ?string
    {
        if (preg_match('/query_place_id=([^&]+)/', $url, $matches)) {
            return $matches[1];
        }
        if ($url) {
            return md5($url);
        }
        return null;
    }
}
