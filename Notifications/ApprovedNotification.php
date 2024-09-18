<?php

namespace Modules\Approval\Notifications;


use Illuminate\Notifications\Notification;
use Modules\Approval\Contracts\ApprovableTarget;
use Modules\Approval\Enums\ApprovalStatus;


class ApprovedNotification extends Notification
{

	public ApprovableTarget $approvable;

	public function __construct(ApprovableTarget $approvable)
	{
		$this->approvable = $approvable;
	}

	public function via($notifiable): array
	{
		return ['database'];
	}

	public function toDatabase($notifiable): array
	{
		return [
			'title' => "{$this->approvable->getApprovableType()} 审核结果",
			'message' => $this->approvable->approval_status === ApprovalStatus::Approved ?
				"{$this->approvable->getApprovableType()} 审核通过" :
				"{$this->approvable->getApprovableType()} 审核驳回， 驳回原因：{$this->approvable->approval_comment}"
		];
	}
}
