<?php

namespace App\Domains\Organizations\Enums;

enum OrganizationRole: string
{
    case PlatformAdministrator = 'platform_administrator';
    case ResellerAdministrator = 'reseller_administrator';
    case AccountAdministrator = 'account_administrator';
    case AccountOperator = 'account_operator';
    case ReadOnlyUser = 'read_only_user';

    public function canManageDevices(): bool
    {
        return $this !== self::ReadOnlyUser;
    }

    public function canManageVoicemail(): bool
    {
        return $this !== self::ReadOnlyUser;
    }

    public function canManageCallRouting(): bool
    {
        return $this !== self::ReadOnlyUser;
    }

    public function canManageMedia(): bool
    {
        return $this !== self::ReadOnlyUser;
    }

    public function canSyncCallDetailRecords(): bool
    {
        return $this !== self::ReadOnlyUser;
    }
}
