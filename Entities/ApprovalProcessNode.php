<?php

namespace Modules\Approval\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalProcessNode extends Model
{
    use softDeletes;
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function approver(): MorphTo
    {
        return $this->morphTo();
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(ApprovalProcess::class);
    }
}
