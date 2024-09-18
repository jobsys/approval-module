<?php

namespace Modules\Approval\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Approval\Entities\ApprovalTask;
use Modules\Approval\Entities\ApprovalTaskHistory;

trait Approvable
{
    public function getApprovableType(): string
    {
        return $this->model_name;
    }

    public function approvalTasks(): MorphMany
    {
        return $this->morphMany(ApprovalTask::class, 'approvable');
    }

    public function approvalTaskHistories(): MorphMany
    {
        return $this->morphMany(ApprovalTaskHistory::class, 'approvable');
    }

    public function loadApprovalDetail(): void
    {
        $this->loadMissing(['approvalTasks.approver', 'approvalTasks.executor:id,name', 'approvalTaskHistories.approver', 'approvalTaskHistories.executor:id,name']);
    }

    public function getApprovableAuth(): string
    {
        return "api.manager.{$this->model_slug}.approve";
    }

    public function getApproveUrl(): string
    {
        return route("page.manager.{$this->model_slug}.detail", ['id' => $this->id]);
    }

    /**
     * 审核前置，默认没有操作
     * @param array $params {task_index, task, approval_status}
     * @return array
     */
    public function beforeApprove(array $params = []): array
    {
        return [true, null];
    }

    /**
     * 审核前置，默认没有操作
     * @param array $params {task_index, task, approval_status, is_finished}
     * @return array
     */
    public function afterApprove(array $params = []): array
    {
        return [true, null];
    }
}
