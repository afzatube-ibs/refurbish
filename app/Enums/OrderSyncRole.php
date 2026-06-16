<?php

namespace App\Enums;

enum OrderSyncRole: string
{
    case ImportTrigger = 'import_trigger';
    case UpdateExisting = 'update_existing';
    case Ignore = 'ignore';

    public function label(): string
    {
        return match ($this) {
            self::ImportTrigger => 'Import Trigger',
            self::UpdateExisting => 'Update Existing Only',
            self::Ignore => 'Ignore',
        };
    }

    public static function recommendedFor(SfmOrderStatus $status): self
    {
        return match ($status) {
            SfmOrderStatus::New => self::ImportTrigger,
            SfmOrderStatus::Rejected,
            SfmOrderStatus::Completed,
            SfmOrderStatus::ReturnQueue,
            SfmOrderStatus::ReturnReceived => self::UpdateExisting,
            default => self::Ignore,
        };
    }

    /**
     * @return list<self>
     */
    public static function forMappingDropdown(): array
    {
        return [
            self::ImportTrigger,
            self::UpdateExisting,
            self::Ignore,
        ];
    }
}
