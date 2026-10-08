<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Portfolio extends Model
{
    protected $table = 'portfolio';

    protected $fillable = [
        'account_master_id',
        'investment_type_master_id',
        'symbol',
        'qty',
        'investement_price',
        'current_price',
        'total_investment',
        'total_PL',
        'status',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountMaster::class, 'account_master_id');
    }

    public function investmentType(): BelongsTo
    {
        return $this->belongsTo(InvestmentTypeMaster::class, 'investment_type_master_id');
    }
}
