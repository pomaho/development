<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AmoAccount;
use Illuminate\Foundation\Events\Dispatchable;

final class AmoAccountSynced
{
    use Dispatchable;

    public function __construct(public readonly AmoAccount $account)
    {
    }
}
