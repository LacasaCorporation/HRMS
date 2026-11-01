<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeparationSettlementItem extends Model
{
    protected $fillable = [
        'separation_settlement_id', 'category', 'description', 'amount', 'notes',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function settlement()
    {
        return $this->belongsTo(SeparationSettlement::class, 'separation_settlement_id');
    }
}
