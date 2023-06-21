<?php

namespace Modules\Approval\Traits;

use Modules\Approval\Entities\ApprovalTask;
use Modules\Approval\Entities\ApprovalTaskHistory;
use Modules\Approval\Enums\ApprovalStatus;

trait Approvable
{
    public function approvalTasks()
    {
        return $this->morphMany(ApprovalTask::class, 'approvable');
    }

    public function approvalTaskHistories()
    {
        return $this->morphMany(ApprovalTaskHistory::class, 'approvable');
    }
}
