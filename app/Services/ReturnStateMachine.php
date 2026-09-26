<?php

namespace App\Services;

use App\Models\ReturnRequest;
use App\Models\ReturnRequestHistory;
use Illuminate\Validation\ValidationException;

class ReturnStateMachine
{
    public const TRANSITIONS = [
        'requested' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['received', 'cancelled'],
        'received' => ['completed'],
        'completed' => [],
        'rejected' => [],
        'cancelled' => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return $from === $to || in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function transition(
        ReturnRequest $return,
        string $target,
        string $source,
        ?int $userId,
        string $note,
        array $attributes = [],
    ): bool {
        if ($return->status === $target) {
            return false;
        }
        if (! $this->canTransition($return->status, $target)) {
            throw ValidationException::withMessages([
                'action' => "Không thể chuyển yêu cầu đổi trả từ {$return->status} sang {$target}.",
            ]);
        }

        $return->update(array_merge($attributes, ['status' => $target]));
        ReturnRequestHistory::create([
            'return_request_id' => $return->id,
            'status' => $target,
            'source' => $source,
            'changed_by_user_id' => $userId,
            'note' => $note,
        ]);

        return true;
    }
}
