<?php

namespace App\Store\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user('sanctum');
        if (!$viewer && ($token = $request->bearerToken())) {
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($accessToken && (!$accessToken->expires_at || $accessToken->expires_at->isFuture())) {
                $viewer = $accessToken->tokenable;
            }
        }

        $gender = $viewer?->gender;
        $isAdmin = $viewer && (
            $viewer->hasRole('admin')
            || $viewer->hasRole('super-admin')
            || $viewer->can('manage-store')
            || $viewer->can('viewAny', \App\Store\Models\StoreProduct::class)
        );

        $primaryImg = $this->primaryImage();
        $frontUrl = $primaryImg?->image_url;
        $backUrl = null;
        $merchandiseImagesMap = null;

        if ($this->is_merchandise) {
            $targetType = 'neutral';
            if ($gender === 'male') {
                $targetType = 'brother';
            } elseif ($gender === 'female') {
                $targetType = 'sister';
            }

            $frontImg = $this->getMerchandiseImage($targetType, 'front') ?? $this->getMerchandiseImage('neutral', 'front');
            $backImg = $this->getMerchandiseImage($targetType, 'back') ?? $this->getMerchandiseImage('neutral', 'back');

            $frontUrl = $frontImg ? url("/api/v1/store/products/{$this->uuid}/images/{$frontImg->uuid}") : null;
            $backUrl = $backImg ? url("/api/v1/store/products/{$this->uuid}/images/{$backImg->uuid}") : null;

            if ($isAdmin) {
                $allowedImages = $this->images;
                $merchandiseImagesMap = [
                    'sister_front' => $this->formatMerchandiseSlot('sister', 'front'),
                    'sister_back' => $this->formatMerchandiseSlot('sister', 'back'),
                    'brother_front' => $this->formatMerchandiseSlot('brother', 'front'),
                    'brother_back' => $this->formatMerchandiseSlot('brother', 'back'),
                    'neutral_front' => $this->formatMerchandiseSlot('neutral', 'front'),
                    'neutral_back' => $this->formatMerchandiseSlot('neutral', 'back'),
                ];
            } elseif ($gender === 'male') {
                $allowedImages = $this->images->filter(fn ($img) => in_array($img->image_type, ['brother', 'neutral'], true));
            } elseif ($gender === 'female') {
                $allowedImages = $this->images->filter(fn ($img) => in_array($img->image_type, ['sister', 'neutral'], true));
            } else {
                $allowedImages = $this->images->filter(fn ($img) => $img->image_type === 'neutral');
            }
        } else {
            $allowedImages = $this->images;
        }

        $data = [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price_cents' => $this->price_cents,
            'formatted_price' => $this->formatted_price,
            'currency' => $this->currency,
            'sku' => $this->sku,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'has_variants' => $this->has_variants,
            'is_merchandise' => (bool) $this->is_merchandise,
            'inventory_quantity' => $this->inventory_quantity,
            'primary_image_url' => $frontUrl ?: $primaryImg?->image_url,
            'front_image_url' => $frontUrl,
            'back_image_url' => $backUrl,
            'images' => $allowedImages->map(fn ($img) => [
                'uuid' => $img->uuid,
                'image_url' => url("/api/v1/store/products/{$this->uuid}/images/{$img->uuid}"),
                'image_type' => $img->image_type,
                'view_type' => $img->view_type,
                'is_primary' => $img->is_primary,
                'is_private' => $img->is_private,
                'display_order' => $img->display_order,
            ])->values(),
            'variants' => $this->variants->map(fn ($v) => [
                'id' => $v->id,
                'uuid' => $v->uuid,
                'name' => $v->name,
                'sku' => $v->sku,
                'price_override_cents' => $v->price_override_cents,
                'effective_price_cents' => $v->effective_price_cents,
                'formatted_price' => $v->formatted_price,
                'inventory_quantity' => $v->inventory_quantity,
                'is_active' => $v->is_active,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($isAdmin && $this->is_merchandise) {
            $data['merchandise_images'] = $merchandiseImagesMap;
        }

        return $data;
    }

    private function formatMerchandiseSlot(string $type, string $view): ?array
    {
        $img = $this->getMerchandiseImage($type, $view);
        if (!$img) {
            return null;
        }

        return [
            'uuid' => $img->uuid,
            'image_url' => url("/api/v1/store/products/{$this->uuid}/images/{$img->uuid}"),
            'image_type' => $img->image_type,
            'view_type' => $img->view_type,
            'is_private' => $img->is_private,
        ];
    }
}
