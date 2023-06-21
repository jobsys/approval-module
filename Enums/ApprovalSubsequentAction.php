<?php

namespace Modules\Approval\Enums;
enum ApprovalSubsequentAction: string
{
    case Invisible = 'invisible'; //不可见
    case Visible = 'visible'; //可见
    case Approve = 'approve'; //可审批
}
