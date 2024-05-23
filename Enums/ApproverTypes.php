<?php

namespace Modules\Approval\Enums;

enum ApproverTypes: string
{


	const LocalDepartment = 'local-department'; //本部门
	const SuperiorDepartment = 'superior-department'; //上级部门
	const DesignatedDepartment = 'designated-department'; //指定部门
	const DesignatedRole = 'designated-role'; //指定角色
	const DesignatedUser = 'designated-user'; //指定用户
}
