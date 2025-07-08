<?php

namespace Modules\Approval\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Approval\Entities\ApprovalTask;
use Modules\Approval\Entities\ApprovalTaskHistory;

trait Approvable
{

	public static function bootApprovable(): void
	{
		static::retrieved(function ($model) {
			// 动态追加 appends
			$model->mergeApprovableAppends(['approvable_slug']);
		});
	}

	protected function mergeApprovableAppends(array $attributes): void
	{
		$this->appends = array_unique(array_merge($this->appends, $attributes));
	}

	public function getApprovableSlugAttribute(): string
	{
		return self::getModelSlug();
	}

	/**
	 * 局部 Scope
	 * 查询关联的审核节点状态
	 */
	public function scopeApproval(Builder $query): void
	{
		$query->with(['approval_tasks:id,approval_process_node_id,approver_id,status,approvable_type,approvable_id', 'approval_tasks.node:id,name']);
	}

	public static function getApprovableType(): string
	{
		return self::getModelName();
	}

	public static function getApprovableClass(): string
	{
		return self::class;
	}

	public function approval_tasks(): MorphMany
	{
		return $this->morphMany(ApprovalTask::class, 'approvable');
	}

	public function approval_task_histories(): MorphMany
	{
		return $this->morphMany(ApprovalTaskHistory::class, 'approvable');
	}

	public function loadApprovalDetail(): void
	{
		$this->loadMissing(['approval_tasks.node:id,name', 'approval_tasks.approver:id,name', 'approval_tasks.executor:id,nickname', 'approval_task_histories.approver:id,name', 'approval_task_histories.executor:id,nickname,work_num']);
	}

	public function getApprovableAuth(): string
	{
		return "api.manager." . self::getModelSlug() . ".approve";
	}

	public function getApproveUrl(): string
	{
		return route("page.manager." . self::getModelSlug() . ".detail", ['id' => $this->id]);
	}

	public function beforeReset(): array
	{
		return [true, null];
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
