<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentTypeMaster extends Model
{
    protected $table = 'investment_type_master';

    protected $fillable = ['name', 'status'];

    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class, 'investment_type_master_id');
    }
}
