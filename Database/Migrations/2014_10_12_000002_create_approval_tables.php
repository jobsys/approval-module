<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create('approval_processes', function (Blueprint $table) {
			$table->id();
			$table->string('name')->index()->unique()->comment('流程名称');
			$table->integer('creator_id')->index()->comment('流程创建者ID');
			$table->string('subsequent_action')->default('invisible')->comment('后续节点可用操作: invisible, visible, approve');
			$table->boolean('is_active')->default(true)->comment('是否激活');
			$table->text('remark')->nullable()->comment('备注');
			$table->timestamps();
			$table->comment('审核流程表');
		});

		//审核流程节点
		Schema::create('approval_process_nodes', function (Blueprint $table) {
			$table->id();
			$table->integer('approval_process_id')->index()->comment('流程ID');
			$table->integer('creator_id')->index()->comment('节点创建者ID');
			$table->string('name')->index()->comment('节点名称');
			$table->string('approver_type')->index()->nullable()->comment('审核人类型');
			$table->integer('approver_id')->index()->nullable()->comment('审核人ID');
			$table->integer('weight')->default(0)->comment('权重');
			$table->softDeletes();
			$table->timestamps();
			$table->comment('审核流程节点表');
		});

		Schema::create('approval_tasks', function (Blueprint $table) {
			$table->id();
			$table->integer('approval_process_id')->index()->comment('流程ID');
			$table->integer('approval_process_node_id')->index()->comment('节点ID');
			$table->morphs('approvable');
			$table->morphs('approver');
			$table->string('status')->default('pending')->comment('状态');
			$table->string('subsequent_action')->default('invisible')->comment('后续节点可用操作: invisible, visible, approve');
			$table->text('comment')->nullable()->comment('审核意见');
			$table->text('remark')->nullable()->comment('审核内部说明');
			$table->integer('approve_user_id')->nullable()->comment('审核人ID');
			$table->dateTime('approved_at')->nullable()->comment('审核时间');
			$table->timestamps();
			$table->comment("审核任务表");
		});

		Schema::create('approval_task_histories', function (Blueprint $table) {
			$table->id();
			$table->integer('approval_process_id')->index()->comment('流程ID');
			$table->integer('approval_process_node_id')->index()->comment('节点ID');
			$table->morphs('approvable');
			$table->morphs('approver');
			$table->string('status')->default('pending')->comment('状态');
			$table->string('subsequent_action')->default('invisible')->comment('后续节点可用操作: invisible, visible, approve');
			$table->text('comment')->nullable()->comment('审核意见');
			$table->text('remark')->nullable()->comment('审核内部说明');
			$table->integer('approve_user_id')->nullable()->comment('审核人ID');
			$table->dateTime('approved_at')->nullable()->comment('审核时间');
			$table->timestamps();
			$table->comment('审核任务历史记录表');
		});

		Schema::create('approval_process_bindings', function (Blueprint $table) {
			$table->id();
			$table->integer('approval_process_id')->index()->nullable()->comment('流程ID');
			$table->string('approvable_type')->index()->comment('审核对象类型');
			$table->integer('approvable_id')->index()->nullable()->comment('审核对象ID');
			$table->boolean('is_auto_approve')->default(false)->comment('是否自动审核');
			$table->string('auto_approve_status')->nullable()->comment('自动审核状态');
			$table->string('auto_approve_comment')->nullable()->comment('自动审核意见');
			$table->json('parameters')->nullable()->comment('参数');
			$table->timestamps();
			$table->comment("审核流程业务绑定表");
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
		Schema::dropIfExists('approval_process_bindings');
	}
};
