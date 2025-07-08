<?php

namespace Modules\Approval\Enums;

//用于存放一些特殊变量
enum ApprovalVendor: string
{
	const UNDETERMINED_USER = 0; //手动指定用户时暂时先将 approver_id 设为 0
}
