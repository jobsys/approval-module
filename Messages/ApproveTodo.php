<?php

namespace Modules\Approval\Messages;


use Modules\Approval\Contracts\ApprovableTarget;
use Modules\Approval\Entities\ApprovalTask;
use Modules\Starter\Abstracts\Message;
use Modules\Starter\Enums\Message\MessageChannel;
use Modules\Starter\Enums\Message\MessageType;

/***
 * 审核待办
 */
class ApproveTodo extends Message
{
	public function __construct(public ApprovalTask $task, public ApprovableTarget $approvable)
	{
	}

	public function via($receiver): array
	{
		return [MessageChannel::DATABASE];
	}

	public function messageBag($receiver): array
	{
		return [
			'title' => "待审核 - {$this->approvable::getApprovableType()}",
			'content' => "{$this->approvable->getApproveTodoMessage()}",
			'type' => MessageType::TODO,
			'data' => [
				'url' => $this->approvable->getApproveUrl(),
			]
		];
	}
}
