<?php

namespace App\Modules\FoodListings\Services;

use App\Modules\Core\Entities\Tag;
use App\Modules\FoodListings\Entities\FoodRequest;
use App\Modules\FoodListings\Repositories\FoodRequestRepository;
use Exception;

class FoodRequestService
{
    public function __construct(
        public FoodRequestRepository $foodRequestRepository
    ) {}

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

        $request = $this->foodRequestRepository->store($data);

        $tagIds = Tag::whereIn('slug', $tagSlugs)->pluck('id')->toArray();
        $request->tags()->sync($tagIds);

        return $request->fresh(['tags']);
    }

    public function updateRequest(string $id, array $data, string $recipientId): object
    {
        $request = $this->foodRequestRepository->fetchBy('id', $id);

        if (! $request) {
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

        $this->foodRequestRepository->update($id, $data);

        $request = $this->foodRequestRepository->fetchBy('id', $id, ['tags']);

        if ($tagSlugs !== null) {
            $tagIds = Tag::whereIn('slug', $tagSlugs)->pluck('id')->toArray();
            $request->tags()->sync($tagIds);
            $request = $this->foodRequestRepository->fetchBy('id', $id, ['tags']);
        }

        return $request;
    }

    public function cancelRequest(string $id, string $recipientId): void
    {
        $request = $this->foodRequestRepository->fetchBy('id', $id);

        if (! $request) {
            throw new Exception('Request not found', 404);
        }

        if ($request->recipient_id !== $recipientId) {
            throw new Exception('Unauthorized', 403);
        }

        if (! in_array($request->status, ['open', 'accepted'])) {
            throw new Exception('Cannot cancel a fulfilled or expired request', 400);
        }

        $this->foodRequestRepository->update($id, ['status' => 'cancelled']);
    }

    public function getRequestsForRecipient(string $recipientId, array $params = []): object
    {
        return $this->foodRequestRepository->fetchForRecipient($recipientId, $params);
    }

    public function getRequestById(string $id, array $relations = []): FoodRequest
    {
        $request = $this->foodRequestRepository->fetchBy('id', $id, $relations);

        if (! $request) {
            throw new Exception('Request not found', 404);
        }

        return $request;
    }

    public function formatRequestResponse(object $request, ?float $distanceKm = null): array
    {
        return [
            'id' => $request->id,
            'title' => $request->title,
            'description' => $request->description,
            'quantity_needed' => $request->quantity_needed,
            'tags' => $request->relationLoaded('tags') ? $request->tags->map(fn ($tag) => [
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
