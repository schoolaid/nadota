<?php

namespace SchoolAid\Nadota\Tests\Mcp\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class McpBatch extends Model
{
    protected $table = 'mcp_batches';

    protected $guarded = [];

    protected $casts = [
        'is_open' => 'boolean',
        'closed_on' => 'date',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(McpOwner::class, 'owner_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(McpLog::class, 'mcp_batch_id');
    }

    public function computedAmount(): float
    {
        return (float) $this->total * 2;
    }

    public function computedCurrency(): string
    {
        return 'EUR';
    }
}
