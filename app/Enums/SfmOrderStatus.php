<?php

namespace App\Enums;

enum SfmOrderStatus: string
{
    case Ignore = 'ignore';
    case New = 'new';
    case Accepted = 'accepted';
    case Packed = 'packed';
    case Dispatched = 'dispatched';
    case Rejected = 'rejected';
    case ReturnQueue = 'return_queue';
    case ReturnReceived = 'return_received';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Ignore => 'Ignore',
            self::New => 'New',
            self::Accepted => 'Accepted',
            self::Packed => 'Packed',
            self::Dispatched => 'Dispatched',
            self::Rejected => 'Rejected',
            self::ReturnQueue => 'Return Queue',
            self::ReturnReceived => 'Return Received',
            self::Completed => 'Completed',
        };
    }

    public function rank(): int
    {
        return config('dropflow.status_ranks.'.$this->value, 0);
    }

    /**
     * @return list<self>
     */
    public static function ibsWorkflowCases(): array
    {
        return [
            self::New,
            self::Accepted,
            self::Packed,
            self::Dispatched,
            self::Rejected,
            self::ReturnQueue,
            self::ReturnReceived,
            self::Completed,
        ];
    }

    /**
     * @return list<self>
     */
    public static function forMappingDropdown(): array
    {
        return array_merge([self::Ignore], self::ibsWorkflowCases());
    }

    public function allowsSourceUpdate(): bool
    {
        return in_array($this, [self::Accepted, self::Packed], true);
    }

    public function isSourceUpdateLocked(): bool
    {
        return ! $this->allowsSourceUpdate() && $this !== self::Ignore;
    }
}
