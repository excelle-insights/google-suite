<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Models;

enum SyncStatus: string
{
    case Pending = 'pending';
    case Synced  = 'synced';
    case Failed  = 'failed';
}