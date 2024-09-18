<?php

namespace Modules\Approval\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Approval\Contracts\ApprovableTarget;

class ApprovableCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ApprovableTarget $approvable;

    /**
     * Create a new event instance.
     */
    public function __construct(ApprovableTarget $approvable)
    {
        $this->approvable = $approvable;
    }

}
