<?php

namespace Modules\Approval\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Approval\Events\ApprovableCreated;
use Modules\Approval\Services\ApprovalService;

class ApprovableCreatedListener implements ShouldQueue
{
    /**
     * Create the event listener.
     */

    protected ApprovalService $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    public function handle(ApprovableCreated $event): void
    {
        $approvable = $event->approvable;
        list($result, $error) = $this->approvalService->createApprovalTask($approvable);
    }
}
