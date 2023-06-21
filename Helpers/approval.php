<?php

use Modules\Approval\Enums\ApprovalStatus;

if (!function_exists('approval_status_options')) {
    /**
     * 获取前端可用的审批状态选项
     * @return array[]
     */
    function approval_status_options(): array
    {
        return [
            ['value' => ApprovalStatus::Pending->value, 'label' => '待审批'],
            ['value' => ApprovalStatus::Approved->value, 'label' => '审批通过'],
            ['value' => ApprovalStatus::Rejected->value, 'label' => '审批驳回'],
            //  ['value' => ApprovalStatus::Skipped->value, 'label' => '审批跳过']
        ];
    }
}
