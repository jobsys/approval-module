<?php

namespace Modules\Approval\Enums;

enum ApprovalStatus: string
{
    case Pending = 'pending'; //待审批
    case Approved = 'approved'; //审批通过
    case Rejected = 'rejected'; //审批驳回
    case Skipped = 'skipped'; //审批跳过
    case Updated = 'updated'; //审批对象已更新
}
