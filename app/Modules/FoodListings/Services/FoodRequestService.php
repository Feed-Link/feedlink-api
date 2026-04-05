<?php

namespace App\Modules\FoodListings\Services;

use App\Modules\Core\Entities\Tag;
use App\Modules\FoodListings\Entities\FoodRequest;
use Exception;

class FoodRequestService
{
    public function createRequest(array $data, string $recipientId): object
    {
        $tagSlugs = $data['tags'] ?? [];
        unset($data['tags']);

        $data['recipient_id'] = $recipientId;
        $data['location'] = [
            'lat' => $data['latitude'],
            'long' => $data['longitude'],
        ];
        $data['status'] = 'open';

        $request = new FoodRequest($data);
        $request->save();

        // Sync tags
        $tagIds = Tag::whereIn('slug', $tagSlugs)->pluck('id')->toArray();
        $request->tags()->sync($tagIds);

        return $request;
    }

    public function updateRequest(string $id, array $data, string $recipientId): object
    {
        $request = FoodRequest::find($id);

        if (!$request) {
            throw new Exception('Request not found', 404);
        }

        if ($request->recipient_id !== $recipientId) {
            throw new Exception('Unauthorized', 403);
        }

        if ($request->status !== 'open') {
            throw new Exception('Can only update open requests', 400);
        }

        $tagSlugs = $data['tags'] ?? null;
        unset($data['tags']);

        if (isset($data['latitude']) && isset($data['longitude'])) {
            $data['location'] = [
                'lat' => $data['latitude'],
                'long' => $data['longitude'],
            ];
        }

        $request->update($data);

        // Sync tags if provided
        if ($tagSlugs !== null) {
            $tagIds = Tag::whereIn('slug', $tagSlugs)->pluck('id')->toArray();
            $request->tags()->sync($tagIds);
        }

        return $request->fresh();
    }

    public function formatRequestResponse(object $request, ?float $distanceKm = null): array
    {
        return [
            'id' => $request->id,
            'title' => $request->title,
            'description' => $request->description,
            'quantity_needed' => $request->quantity_needed,
            'tags' => $request->relationLoaded('tags') ? $request->tags->map(fn($tag) => [
                'slug' => $tag->slug,
                'name' => $tag->name,
                'category' => $tag->category,
            ])->toArray() : [],
            'needed_by' => $request->needed_by?->toISOString(),
            'status' => $request->status,
            'latitude' => (float) $request->latitude,
            'longitude' => (float) $request->longitude,
            'location' => $request->latitude && $request->longitude ? [
                'lat' => (float) $request->latitude,
                'lng' => (float) $request->longitude,
            ] : null,
            'address' => $request->address,
            'distance_km' => $distanceKm,
            'recipient' => $request->relationLoaded('recipient') && $request->recipient ? [
                'id' => $request->recipient->id,
                'name' => $request->recipient->name,
                'is_verified' => (bool) ($request->recipient->is_verified ?? false),
            ] : null,
            'accepted_at' => $request->accepted_at?->toISOString(),
            'created_at' => $request->created_at?->toISOString(),
        ];
    }
}
