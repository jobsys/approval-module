<?php

namespace Modules\Approval\Notifications;


use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Modules\Approval\Contracts\ApprovableTarget;
use Modules\Approval\Entities\ApprovalTask;

/***
 * 审核待办
 */
class ApproveTodo extends Notification implements shouldQueue
{
	use Queueable;

	public ApprovalTask $task;
	public ApprovableTarget $approvable;

	public function __construct(ApprovalTask $task, ApprovableTarget $approvable)
	{
		$this->task = $task;
		$this->approvable = $approvable;
	}

	public function via($notifiable): array
	{
		return ['database'];
	}

	public function toDatabase($notifiable): array
	{
		return [
			'url' => $this->approvable->getApproveUrl(),
			'title' => "待审核 - {$this->approvable->getApprovableType()}",
			'message' => "{$this->approvable->getApproveTodoMessage()}"
		];
	}
}
