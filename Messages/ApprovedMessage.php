<?php

namespace Modules\Approval\Messages;


use Modules\Approval\Contracts\ApprovableTarget;
use Modules\Approval\Enums\ApprovalStatus;
use Modules\Starter\Abstracts\Message;
use Modules\Starter\Enums\Message\MessageChannel;
use Modules\Starter\Enums\Message\MessageType;


class ApprovedMessage extends Message
{

	public function __construct(public ApprovableTarget $approvable)
	{
	}

	public function via($receiver): array
	{
		return [MessageChannel::DATABASE];
	}

	public function messageBag($receiver): array
	{
		return [
			'title' => "{$this->approvable::getApprovableType()} 审核结果",
			'content' => $this->approvable->approval_status === ApprovalStatus::Approved ?
				"{$this->approvable::getApprovableType()} 审核通过" :
				"{$this->approvable::getApprovableType()} 审核驳回， 驳回原因：{$this->approvable->approval_comment}",
			'type' => MessageType::NOTIFICATION,
		];
	}
}
