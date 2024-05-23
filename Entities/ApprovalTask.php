<?php

namespace Modules\Approval\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Starter\Entities\BaseModel;

class ApprovalTask extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'approved_at' => 'datetime',
    ];

    protected $accessors = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    //审批者
    public function approver(): MorphTo
    {
        return $this->morphTo();
    }

    public function executor()
    {
        return $this->belongsTo(User::class, 'approve_user_id', 'id');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(ApprovalProcess::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(ApprovalProcessNode::class);
    }


}
