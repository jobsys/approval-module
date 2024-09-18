<?php

use Modules\Approval\Enums\ApprovalStatus;

if (!function_exists('approval_status_options')) {
	/**
	 * 获取前端可用的审核状态选项
	 * @return array[]
	 */
	function approval_status_options(): array
	{
		return [
			['value' => ApprovalStatus::Pending, 'label' => '待审核'],
			['value' => ApprovalStatus::Processing, 'label' => '审核中'],
			['value' => ApprovalStatus::Approved, 'label' => '审核通过'],
			['value' => ApprovalStatus::Rejected, 'label' => '审核驳回'],
			//  ['value' => ApprovalStatus::Skipped->value, 'label' => '审核跳过']
		];
	}

	/**
	 * 获取审核状态文本
	 * @param $status
	 * @return string
	 */
	function approval_status_text($status): string
	{
		$status_map = [
			ApprovalStatus::Pending => '待审核',
			ApprovalStatus::Processing => '审核中',
			ApprovalStatus::Approved => '审核通过',
			ApprovalStatus::Rejected => '审核驳回',
		];
		return $status_map[$status] ?? '未知状态';
	}
}
