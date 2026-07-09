<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PortalConnect\Wallet\Models\Wallet as BaseWallet;

class Wallet extends BaseWallet
{
    /** @use HasFactory<\Database\Factories\WalletFactory> */
    use HasFactory;
}
