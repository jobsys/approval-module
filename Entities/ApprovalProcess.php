<?php

namespace Modules\Approval\Entities;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Starter\Entities\BaseModel;
use Modules\Starter\Traits\Filterable;

class ApprovalProcess extends BaseModel
{

    use Filterable;

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $accessors = [
        'created_at' => 'datetime',
    ];

    public function nodes(): HasMany
    {
        return $this->hasMany(ApprovalProcessNode::class);
    }
}
