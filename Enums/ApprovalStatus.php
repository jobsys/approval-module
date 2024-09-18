<?php

namespace Modules\Approval\Enums;

enum ApprovalStatus: string
{
	const Pending = 'pending'; //待审核
	const Processing = 'processing'; //审核中
	const Approved = 'approved'; //审核通过
	const Rejected = 'rejected'; //审核驳回
	const Skipped = 'skipped'; //审核跳过
	const Updated = 'updated'; //审核对象已更新
}
