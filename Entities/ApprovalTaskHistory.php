<?php

namespace Modules\Approval\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Starter\Entities\BaseModel;

class ApprovalTaskHistory extends BaseModel
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
	
    //可审批者
    public function approver(): MorphTo
    {
        return $this->morphTo();
    }

    //审批执行人
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
