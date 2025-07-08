<?php

namespace Modules\Approval\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

interface ApprovableTarget
{


	/**
	 * 关联审核任务
	 * @return MorphMany
	 */
	public function approval_tasks(): MorphMany;

	/**
	 * 关联审核历史
	 * @return MorphMany
	 */
	public function approval_task_histories(): MorphMany;

	/**
	 * 获取审核对象类名
	 * @return string
	 */
	public static function getApprovableClass(): string;


	/**
	 * 审核对象类型
	 * @return string
	 */
	public static function getApprovableType(): string; //如：申请表

	/**
	 * 审核对象权限表达式
	 * @return string
	 */
	public function getApprovableAuth(): string; //如： api.manager.student.resource.approve

	/**
	 * 返回审核页面URL或者路由
	 * @return string
	 */
	public function getApproveUrl(): string;  //如：return route('page.manager.student.resource.detail', ['id' => $this->id]);

	/**
	 * 待办消息
	 * @return string
	 */
	public function getApproveTodoMessage(): string;

	/**
	 * 获取审核发起者实例，用于通知等业务
	 * @return Authenticatable|null
	 */
	public function getInitiator(): ?Authenticatable; // 如： return $this->creator

	/**
	 * 加载审核详情
	 * @return void
	 */
	public function loadApprovalDetail(): void;


	/**
	 * 重置流程前置，如检查是否有权限重置
	 * 返回[true, null] 或者 [false, $errorMessages]
	 * @return array
	 */
	public function beforeReset(): array;


	/**
	 * 审核前置，如检查是否有权限审核
	 * 返回[true, null] 或者 [false, $errorMessages]
	 * @param array $params ['task_index' => $task_index, 'task' => $task, 'approval_status' => $approval_status]
	 * @return array
	 */
	public function beforeApprove(array $params): array;

	/**
	 * 审核后置，如更新审核状态等
	 * 返回[true, null] 或者 [false, $errorMessages]
	 * @param array $params ['task_index' => $task_index, 'task' => $task, 'approval_status' => $approval_status]
	 * @return array
	 */
	public function afterApprove(array $params): array;


}
