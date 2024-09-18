<?php

namespace Modules\Approval\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalProcessBinding extends Model
{
    protected $casts = [
        'is_auto_approve' => 'boolean',
        'parameters' => 'array',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(ApprovalProcess::class, 'approval_process_id');
    }
}
