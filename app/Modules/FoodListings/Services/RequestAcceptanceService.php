<?php

namespace App\Modules\FoodListings\Services;

use App\Modules\Core\Enums\NotificationTypeEnum;
use App\Modules\FoodListings\Entities\FoodRequest;
use App\Modules\FoodListings\Repositories\FoodRequestRepository;
use App\Modules\FoodListings\Repositories\RequestAcceptanceRepository;
use App\Modules\Notifications\Jobs\SendNotificationJob;
use Exception;

class RequestAcceptanceService
{
    public function __construct(
        protected FoodRequestRepository $foodRequestRepository,
        protected RequestAcceptanceRepository $requestAcceptanceRepository
    ) {}

    public function acceptRequest(string $requestId, string $donorId, ?string $note): object
    {
        $foodRequest = $this->foodRequestRepository->fetchBy('id', $requestId, ['recipient']);

        if (! $foodRequest) {
            throw new Exception('Request not found', 404);
        }

        if ($foodRequest->status !== 'open') {
            throw new Exception('This request is no longer open for acceptance', 400);
        }

        if ($this->requestAcceptanceRepository->hasPendingAcceptance($requestId, $donorId)) {
            throw new Exception('You have already submitted an acceptance for this request', 400);
        }

        $acceptance = $this->requestAcceptanceRepository->store([
            'food_request_id' => $requestId,
            'donor_id' => $donorId,
            'status' => 'pending',
            'note' => $note,
        ]);

        $acceptance->load('donor');

        SendNotificationJob::dispatch(
            $foodRequest->recipient_id,
            NotificationTypeEnum::REQUEST_ACCEPTED->value,
            'A donor wants to help',
            "{$acceptance->donor->name} has offered to fulfill your request \"{$foodRequest->title}\"",
            [
                'food_request_id' => $foodRequest->id,
                'acceptance_id' => $acceptance->id,
                'request_title' => $foodRequest->title,
            ]
        );

        return $acceptance;
    }

    public function withdrawAcceptance(string $requestId, string $donorId): void
    {
        $acceptance = $this->requestAcceptanceRepository->findPendingByDonor($requestId, $donorId);

        if (! $acceptance) {
            throw new Exception('No pending acceptance found for this request', 404);
        }

        $foodRequest = $this->foodRequestRepository->fetchBy('id', $requestId);

        $this->requestAcceptanceRepository->delete($acceptance->id);

        SendNotificationJob::dispatch(
            $foodRequest->recipient_id,
            NotificationTypeEnum::ACCEPTANCE_WITHDRAWN->value,
            'A donor withdrew their offer',
            "A donor has withdrawn their offer to fulfill your request \"{$foodRequest->title}\"",
            [
                'food_request_id' => $foodRequest->id,
                'request_title' => $foodRequest->title,
            ]
        );
    }

    public function confirmAcceptance(string $requestId, string $acceptanceId, string $recipientId): FoodRequest
    {
        $foodRequest = $this->foodRequestRepository->fetchBy('id', $requestId);

        if (! $foodRequest) {
            throw new Exception('Request not found', 404);
        }

        if ($foodRequest->recipient_id !== $recipientId) {
            throw new Exception('Unauthorized', 403);
        }

        if ($foodRequest->status !== 'open') {
            throw new Exception('This request is no longer open', 400);
        }

        $acceptance = $this->requestAcceptanceRepository->fetchBy('id', $acceptanceId);

        if (! $acceptance || $acceptance->food_request_id !== $requestId) {
            throw new Exception('Acceptance not found', 404);
        }

        if ($acceptance->status !== 'pending') {
            throw new Exception('This acceptance is not pending', 400);
        }

        $this->requestAcceptanceRepository->update($acceptanceId, ['status' => 'confirmed']);
        $this->requestAcceptanceRepository->rejectOtherPending($requestId, $acceptanceId);

        $this->foodRequestRepository->update($requestId, [
            'status' => 'accepted',
            'accepted_by' => $acceptance->donor_id,
            'accepted_at' => now(),
        ]);

        SendNotificationJob::dispatch(
            $acceptance->donor_id,
            NotificationTypeEnum::ACCEPTANCE_CONFIRMED->value,
            'Your offer was accepted',
            "The recipient has confirmed your offer for \"{$foodRequest->title}\"",
            [
                'food_request_id' => $foodRequest->id,
                'acceptance_id' => $acceptanceId,
                'request_title' => $foodRequest->title,
            ]
        );

        return $this->foodRequestRepository->fetchBy('id', $requestId, ['recipient', 'acceptedBy', 'acceptances.donor', 'tags']);
    }

    public function rejectAcceptance(string $requestId, string $acceptanceId, string $recipientId): void
    {
        $foodRequest = $this->foodRequestRepository->fetchBy('id', $requestId);

        if (! $foodRequest) {
            throw new Exception('Request not found', 404);
        }

        if ($foodRequest->recipient_id !== $recipientId) {
            throw new Exception('Unauthorized', 403);
        }

        $acceptance = $this->requestAcceptanceRepository->fetchBy('id', $acceptanceId);

        if (! $acceptance || $acceptance->food_request_id !== $requestId) {
            throw new Exception('Acceptance not found', 404);
        }

        if ($acceptance->status !== 'pending') {
            throw new Exception('This acceptance is not pending', 400);
        }

        $this->requestAcceptanceRepository->update($acceptanceId, ['status' => 'rejected']);

        SendNotificationJob::dispatch(
            $acceptance->donor_id,
            NotificationTypeEnum::ACCEPTANCE_REJECTED->value,
            'Your offer was declined',
            "The recipient has declined your offer for \"{$foodRequest->title}\"",
            [
                'food_request_id' => $foodRequest->id,
                'acceptance_id' => $acceptanceId,
                'request_title' => $foodRequest->title,
            ]
        );
    }

    public function completeRequest(string $requestId, string $recipientId): FoodRequest
    {
        $foodRequest = $this->foodRequestRepository->fetchBy('id', $requestId, ['acceptedBy']);

        if (! $foodRequest) {
            throw new Exception('Request not found', 404);
        }

        if ($foodRequest->recipient_id !== $recipientId) {
            throw new Exception('Unauthorized', 403);
        }

        if ($foodRequest->status !== 'accepted') {
            throw new Exception('Request must be in accepted status to mark as fulfilled', 400);
        }

        $this->foodRequestRepository->update($requestId, ['status' => 'fulfilled']);

        if ($foodRequest->accepted_by) {
            SendNotificationJob::dispatch(
                $foodRequest->accepted_by,
                NotificationTypeEnum::REQUEST_FULFILLED->value,
                'Food request fulfilled',
                "The recipient has confirmed that \"{$foodRequest->title}\" has been fulfilled. Thank you!",
                [
                    'food_request_id' => $foodRequest->id,
                    'request_title' => $foodRequest->title,
                ]
            );
        }

        return $this->foodRequestRepository->fetchBy('id', $requestId, ['recipient', 'acceptedBy', 'tags']);
    }

    public function getAcceptancesForRequest(string $requestId, string $recipientId, array $params = []): object
    {
        $foodRequest = $this->foodRequestRepository->fetchBy('id', $requestId);

        if (! $foodRequest) {
            throw new Exception('Request not found', 404);
        }

        if ($foodRequest->recipient_id !== $recipientId) {
            throw new Exception('Unauthorized', 403);
        }

        return $this->requestAcceptanceRepository->fetchForRequest($requestId, $params);
    }
}
