<?php

namespace Modules\Approval\Enums;
enum ApprovalSubsequentAction: string
{
    const Invisible = 'invisible'; //不可见
	const Visible = 'visible'; //可见
	const Approve = 'approve'; //可审核
}
