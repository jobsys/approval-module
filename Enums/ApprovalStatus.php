<?php

namespace Modules\Approval\Enums;

enum ApprovalStatus: string
{
    const Pending = 'pending'; //待审批
    const Approved = 'approved'; //审批通过
	const Rejected = 'rejected'; //审批驳回
	const Skipped = 'skipped'; //审批跳过
	const Updated = 'updated'; //审批对象已更新
}
