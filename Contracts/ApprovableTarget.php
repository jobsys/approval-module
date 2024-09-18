<?php

namespace Modules\Approval\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

interface ApprovableTarget
{
	/**
	 * 审核对象类型
	 * @return string
	 */
	public function getApprovableType(): string; //学生资源

	/**
	 * 审核对象权限表达式
	 * @return string
	 */
	public function getApprovableAuth(): string; //api.manager.student.resource.approve

	/**
	 * 返回审核页面URL或者路由
	 * @return string
	 */
	public function getApproveUrl(): string;  //return route('page.manager.student.resource.detail', ['id' => $this->id]);

	/**
	 * 待办消息
	 * @return string
	 */
	public function getApproveTodoMessage(): string;

	/**
	 * 获取审核发起者实例，用于通知等业务
	 * @return Authenticatable
	 */
	public function getInitiator(): Authenticatable; // return $this->creator

	/**
	 * 获取审核任务
	 * @return MorphMany
	 */
	public function approvalTasks(): MorphMany;

	/**
	 * 获取审核历史
	 * @return MorphMany
	 */
	public function approvalTaskHistories(): MorphMany;

	/**
	 * 加载审核详情
	 * @return void
	 */
	public function loadApprovalDetail(): void;

	/**
	 * 审核前置，如检查是否有权限审核
	 * 返回[true, null] 或者 [false, $errorMessages]
	 * @param array $params
	 * @return array
	 */
	public function beforeApprove(array $params): array;

	/**
	 * 审核后置，如更新审核状态等
	 * 返回[true, null] 或者 [false, $errorMessages]
	 * @param array $params
	 * @return array
	 */
	public function afterApprove(array $params): array;


}
