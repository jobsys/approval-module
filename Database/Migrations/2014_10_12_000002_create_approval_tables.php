<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //审批流程
        Schema::create('approval_processes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index()->unique()->comment('流程名称'); // 流程名称
            $table->integer('creator_id')->index()->comment('流程创建者ID'); // 流程创建者ID
            $table->string('type')->index()->unique()->comment('流程审批类型');
            $table->string('subsequent_action')->default('invisible')->comment('后续节点可用操作: invisible, visible, approve'); // 可用操作
            $table->boolean('is_active')->default(true)->comment('是否激活'); // 是否激活
            $table->text('remark')->nullable()->comment('备注'); // 备注
            $table->timestamps(); // 创建时间和更新时间
        });

        //审批流程节点
        Schema::create('approval_process_nodes', function (Blueprint $table) {
            $table->id();
            $table->integer('approval_process_id')->index()->comment('流程ID'); // 流程ID
            $table->integer('creator_id')->index()->comment('节点创建者ID'); // 节点创建者ID
            $table->string('name')->index()->comment('节点名称'); // 节点名称
            $table->morphs('approver');
            $table->integer('weight')->default(0)->comment('权重');
            $table->softDeletes();
            $table->timestamps(); // 创建时间和更新时间
        });

        //审批任务
        Schema::create('approval_tasks', function (Blueprint $table) {
            $table->id();
            $table->integer('approval_process_id')->index()->comment('流程ID'); // 流程ID
            $table->integer('approval_process_node_id')->index()->comment('节点ID'); // 节点ID
            $table->morphs('approvable');
            $table->morphs('approver');
            $table->string('status')->default('pending')->comment('状态'); // 状态
            $table->string('subsequent_action')->default('invisible')->comment('后续节点可用操作: invisible, visible, approve'); // 可用操作
            $table->text('comment')->nullable()->comment('审批意见'); // 审批意见
            $table->integer('approve_user_id')->nullable()->comment('审批人ID'); // 审批人ID
            $table->dateTime('approved_at')->nullable()->comment('审批时间'); // 审批时间
            $table->timestamps(); // 创建时间和更新时间
        });

        //审批任务历史记录
        Schema::create('approval_task_histories', function (Blueprint $table) {
            $table->id();
            $table->integer('approval_process_id')->index()->comment('流程ID'); // 流程ID
            $table->integer('approval_process_node_id')->index()->comment('节点ID'); // 节点ID
            $table->morphs('approvable');
            $table->morphs('approver');
            $table->string('status')->default('pending')->comment('状态'); // 状态
            $table->string('subsequent_action')->default('invisible')->comment('后续节点可用操作: invisible, visible, approve'); // 可用操作
            $table->text('comment')->nullable()->comment('审批意见'); // 审批意见
            $table->integer('approve_user_id')->nullable()->comment('审批人ID'); // 审批人ID
            $table->dateTime('approved_at')->nullable()->comment('审批时间'); // 审批时间
            $table->timestamps(); // 创建时间和更新时间
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_processes');
        Schema::dropIfExists('approval_process_nodes');
        Schema::dropIfExists('approval_tasks');
        Schema::dropIfExists('approval_task_histories');
    }
};
