<?php

namespace Modules\Approval\Services;

use App\Models\Department;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Approval\Contracts\ApprovableTarget;
use Modules\Approval\Entities\ApprovalProcess;
use Modules\Approval\Entities\ApprovalProcessBinding;
use Modules\Approval\Entities\ApprovalProcessNode;
use Modules\Approval\Entities\ApprovalTask;
use Modules\Approval\Entities\ApprovalTaskHistory;
use Modules\Approval\Enums\ApprovalStatus;
use Modules\Approval\Enums\ApprovalSubsequentAction;
use Modules\Approval\Enums\ApproverTypes;
use Modules\Approval\Notifications\ApprovedNotification;
use Modules\Approval\Notifications\ApproveTodo;
use Modules\Permission\Entities\Role;
use Modules\Permission\Traits\Authorisations;
use Modules\Starter\Entities\BaseModel;
use Modules\Starter\Traits\Snapshotable;

class ApprovalService
{
	/**
	 * 创建审核任务
	 * @param ApprovableTarget $approvable
	 * @return array
	 */
	public function createApprovalTask(ApprovableTarget $approvable): array
	{

		[$process, $binding] = $this->getApprovableProcess($approvable);
		if (!$process) {
			return [true, null];
		}

		if (!$process->nodes->count()) {
			return [false, '审核流程未配置审核节点'];
		}

		//TODO 在这里判断是否已经进入审核流程，如果进行审核流程可能就不能进行修改了?

		if ($approvable->approvalTasks()->count() && $approvable->approvalTaskHistories()->count()) {
			//如果已经有审核历史，则添加一条审核对象已经更新的记录，如果没有审核历史，审核对象可以任意更新
			/**
			 * 用审核任务的属性创建更新历史
			 * @var ApprovalTask $task
			 */
			$task = $approvable->approvalTasks()->first();
			ApprovalTaskHistory::create(array_merge($task->getOriginal(), [
				'id' => null,
				'status' => ApprovalStatus::Updated,
				'comment' => '',
				'remark' => '更新对象',
				'approve_user_id' => null,
				'approved_at' => null,
				'created_at' => now(),
				'updated_at' => now(),
			]));
		}

		//然后删除所有审核任务
		$approvable->approvalTasks()->delete();

		[, $error] = $this->createNodeTaskEntity($approvable, $process);

		if ($error) {
			return [false, $error];
		}

		if ($binding && $binding->is_auto_approve) {
			[, $error] = $this->autoApprove($binding, $approvable);
		}

		if ($error) {
			return [false, $error];
		}

		return [true, null];
	}

	/**
	 * 检测用户是否对该对象拥有审核权限
	 * 如果有审核权限，将会在  Approvable 中挂载上当前的审核任务 current_task
	 * @param ApprovableTarget $approvable
	 * @param User|null $user
	 * @return array
	 */
	public function canUserApproveTarget(ApprovableTarget $approvable, User $user = null): array
	{
		[$process,] = $this->getApprovableProcess($approvable);
		if (!$process) {
			return [true, null];
		}

		if (!$process->nodes->count()) {
			return [false, '审核流程未配置审核节点'];
		}

		if (empty($user)) {
			$user = auth()->user();
		}

		if (!$user->isSuperAdmin()) {
			//不是超管拿可以审核的第一个任务，按流程进行
			$department_ids = $user->departments()->pluck('id')->toArray();
			$role_ids = $user->roles()->pluck('id')->toArray();
			$task = ApprovalTask::where('approvable_type', get_class($approvable))->where('approvable_id', $approvable->getKey())
				->whereIn('subsequent_action', [ApprovalSubsequentAction::Approve, ApprovalSubsequentAction::Visible])
				->where('approval_process_id', $process->id)
				->where(function ($query) use ($user, $department_ids, $role_ids) {
					return $query->where(function ($query) use ($user) {
						$query->where('approver_id', $user->id)->where('approver_type', User::class);
					})->orWhere(function ($query) use ($department_ids) {
						$query->whereIn('approver_id', $department_ids)->where('approver_type', Department::class);
					})->orWhere(function ($query) use ($role_ids) {
						$query->whereIn('approver_id', $role_ids)->where('approver_type', Role::class);
					});
				})->first();
		} else {
			//超管拿最后一个待审核任务，前置任务将统一被设置成跳过
			$task = ApprovalTask::where('approvable_type', get_class($approvable))->where('approvable_id', $approvable->getKey())
				->where('approval_process_id', $process->id)->orderByDesc('id')->first();
		}

		$approvable->{'current_task'} = $task;

		return [$task, $task ? null : '无审核任务'];
	}

	/**
	 * 获取用户待审核的审核对象
	 * @param ApprovableTarget $approvable
	 * @param array|null $status
	 * @param array|null $subsequent_action
	 * @param User|null $user
	 * @return Builder
	 */
	public function getUserApprovable(ApprovableTarget $approvable, array $status = null, array $subsequent_action = null, User $user = null): Builder
	{

		[$process] = $this->getApprovableProcess($approvable);
		/**
		 * @var Builder $approvable
		 */
		//没有审核流程，就不返回内容
		if (!$process) {
			return $approvable->where(DB::raw(1), '!=', DB::raw(1));
		}
		if (empty($user)) {
			$user = auth()->user();
		}

		// 权限过滤
		if (in_array(Authorisations::class, class_uses_recursive($approvable))) {
			$approvable = $approvable->authorise();
		}

		if (!$user->isSuperAdmin()) {
			$department_ids = $user->departments()->pluck('id')->toArray();
			$role_ids = $user->roles()->pluck('id')->toArray();
			return $approvable->whereHas('approvalTasks',
				function ($query) use ($user, $department_ids, $role_ids, $process, $status, $subsequent_action) {
					$query->when($status, function ($query, $status) {
						$query->whereIn('status', $status);
					})->when($subsequent_action, function ($query, $subsequent_action) {
						$query->whereIn('subsequent_action', $subsequent_action);
					})->where('approval_process_id', $process->id)
						->where(function ($query) use ($user, $department_ids, $role_ids) {
							return $query->where(function ($query) use ($user) {
								$query->where('approver_id', $user->id)->where('approver_type', User::class);
							})->orWhere(function ($query) use ($department_ids) {
								$query->whereIn('approver_id', $department_ids)->where('approver_type', Department::class);
							})->orWhere(function ($query) use ($role_ids) {
								$query->whereIn('approver_id', $role_ids)->where('approver_type', Role::class);
							});
						})->select(['id', 'approvable_id', 'approvable_type', 'status', 'subsequent_action']);
				}
			);
		}
		return $approvable->whereHas('approvalTasks',
			function ($query) use ($process, $status, $subsequent_action) {
				$query->when($status, function ($query, $status) {
					$query->whereIn('status', $status);
				})->when($subsequent_action, function ($query, $subsequent_action) {
					$query->whereIn('subsequent_action', $subsequent_action);
				})->where('approval_process_id', $process->id)->select(['id', 'approvable_id', 'approvable_type', 'status', 'subsequent_action']);
			}
		);
	}

	/**
	 * 审核
	 * @param ApprovableTarget $approvable
	 * @param string $approval_status
	 * @param string $approval_comment
	 * @param string $approval_remark
	 * @return array
	 */
	public function approve(ApprovableTarget $approvable, string $approval_status, string $approval_comment = '', string $approval_remark = ''): array
	{
		$approvable->loadMissing(['approvalTasks']);


		[$task, $error] = $this->canUserApproveTarget($approvable);

		if ($error) {
			return [false, $error];
		}

		if ($task->status !== ApprovalStatus::Pending) {
			return [false, '审核任务已处理'];
		}

		/**
		 * @var $tasks Collection
		 */
		$tasks = $approvable->approvalTasks->sortBy('id')->sortBy('weight');

		$task_index = $tasks->search(fn($item) => $item->id == $task->id);

		// 审核前置任务
		[$before_result, $before_message] = $approvable->beforeApprove(['task_index' => $task_index, 'task' => $task, 'approval_status' => $approval_status]);

		if (!$before_result) {
			return [$before_result, $before_message];
		}

		try {
			DB::beginTransaction();

			//保存审核对象快照
			if (in_array(Snapshotable::class, class_uses_recursive($approvable))) {
				$approvable->snapshot();
			}

			//保存审核状态
			$task->status = $approval_status;
			$task->comment = $approval_comment;
			$task->remark = $approval_remark;
			$task->approve_user_id = auth()->user()->id;
			$task->approved_at = now();
			$task->subsequent_action = ApprovalSubsequentAction::Visible;
			$task->save();

			//保存审核历史
			ApprovalTaskHistory::create(array_merge($task->getOriginal(), ['id' => null]));

			//前面的任务节点如果是未审核的，则设置为跳过，而且不可再审核
			$previous_tasks = $tasks->slice(0, $task_index);
			foreach ($previous_tasks as $previous_task) {
				if ($previous_task->subsequent_action === ApprovalSubsequentAction::Approve) {
					$previous_task->subsequent_action = ApprovalSubsequentAction::Visible;
					if ($previous_task->status === ApprovalStatus::Pending) {
						$previous_task->status = ApprovalStatus::Skipped;
					}
					$previous_task->save();
				}
			}


			//激活下个节点并发送待办通知
			/**
			 * @var $next_task ApprovalTask
			 */
			$next_task = $tasks->get($task_index + 1);

			if ($next_task && $next_task->subsequent_action !== ApprovalSubsequentAction::Approve) {
				$next_task->subsequent_action = ApprovalSubsequentAction::Approve;
				$next_task->save();
				$this->sendApprovalTodoNotification($next_task, $approvable);
			}

			$is_finished = !$next_task || $approval_status === ApprovalStatus::Rejected;

			// 审核流程完成 - Rejected 或者没有后续节点
			if ($is_finished) {
				$approvable->{'approval_status'} = $approval_status;
				$approvable->{'approval_comment'} = $approval_comment;

				//如果有审核对象有结果快照的，那在审核不过通或者是最一个审核节点的时候，更新审核对象的审核状态
				if (array_key_exists('approval_status', $approvable->attributesToArray())) {
					app(get_class($approvable))->whereKey($approvable->getKey())->update([
						'approval_status' => $approval_status,
						'approval_comment' => $approval_comment,
						'approval_at' => now()
					]);
				}
				//通知发起者
				$approvable->getInitiator()->notify(new ApprovedNotification($approvable));
			} else {
				if (array_key_exists('approval_status', $approvable->attributesToArray())) {
					app(get_class($approvable))->whereKey($approvable->getKey())->update([
						'approval_status' => ApprovalStatus::Processing,
						'approval_comment' => $approval_comment,
						'approval_at' => now()
					]);
				}
			}

			// 审核后置任务
			[$after_result, $after_message] = $approvable->afterApprove(['task_index' => $task_index, 'task' => $task, 'approval_status' => $approval_status, 'is_finished' => $is_finished]);

			if (!$after_result) {
				return [$after_result, $after_message];
			}

			DB::commit();
			return [$is_finished, null];
		} catch (Exception $e) {
			DB::rollBack();
			Log::error('Approve::' . $e->getMessage());
			return [false, '审核失败，请联系系统管理员'];
		}
	}

	/**
	 * 自动审核
	 * @param ApprovalProcessBinding $binding
	 * @param ApprovableTarget $approvable
	 * @return array
	 */
	public function autoApprove(ApprovalProcessBinding $binding, ApprovableTarget $approvable): array
	{

		if (!$binding->is_auto_approve) {
			return [true, null];
		}

		$approvable->loadMissing(['approvalTasks']);

		[$task, $error] = $this->canUserApproveTarget($approvable, User::find(config('conf.super_admin_id')));

		if ($error) {
			return [false, $error];
		}


		/**
		 * @var $tasks Collection
		 */
		$tasks = $approvable->approvalTasks->sortBy('id')->sortBy('weight');

		$task_index = $tasks->search(fn($item) => $item->id == $task->id);


		// 审核前置任务
		[$before_result, $before_message] = $approvable->beforeApprove(['task_index' => $task_index, 'task' => $task, 'approval_status' => $binding->auto_approve_status]);

		if (!$before_result) {
			return [$before_result, $before_message];
		}

		try {
			DB::beginTransaction();

			//保存审核对象快照
			if (in_array(Snapshotable::class, class_uses_recursive($approvable))) {
				$approvable->snapshot();
			}

			//保存审核状态
			$task->status = $binding->auto_approve_status;
			$task->comment = $binding->auto_approve_comment;
			$task->remark = '自动审核';
			$task->approve_user_id = config('conf.super_admin_id');
			$task->approved_at = now();
			$task->subsequent_action = ApprovalSubsequentAction::Visible;
			$task->save();

			//保存审核历史
			ApprovalTaskHistory::create(array_merge($task->getOriginal(), ['id' => null]));


			//前面的任务节点如果是未审核的，则设置为跳过，而且不可再审核
			$previous_tasks = $tasks->slice(0, $task_index);
			foreach ($previous_tasks as $previous_task) {
				if ($previous_task->subsequent_action === ApprovalSubsequentAction::Approve) {
					$previous_task->subsequent_action = ApprovalSubsequentAction::Visible;
					if ($previous_task->status === ApprovalStatus::Pending) {
						$previous_task->status = ApprovalStatus::Skipped;
					}
					$previous_task->save();
				}
			}

			//如果有审核对象有结果快照的，那在审核不过通或者是最一个审核节点的时候，更新审核对象的审核状态
			if (array_key_exists('approval_status', $approvable->attributesToArray())) {
				app(get_class($approvable))->whereKey($approvable->getKey())->update([
					'approval_status' => $binding->auto_approve_status,
					'approval_comment' => $binding->auto_approve_comment,
					'approval_at' => now()
				]);
			}

			//通知发起者
			$approvable->getInitiator()->notify(new ApprovedNotification($approvable));

			// 审核后置任务
			[$after_result, $after_message] = $approvable->afterApprove(['task_index' => $task_index, 'task' => $task, 'approval_status' => $binding->auto_approve_status, 'is_finished' => true]);

			if (!$after_result) {
				return [$after_result, $after_message];
			}

			DB::commit();
			return [true, null];
		} catch (Exception $e) {
			DB::rollBack();
			Log::error('AutoApprove::' . $e->getMessage());
			return [false, '审核失败，请联系系统管理员'];
		}
	}

	/**
	 * 为审核对象添加审核历史和详情
	 * @param ApprovalTask $approvable
	 * @return void
	 */
	public function getApprovalDetail(ApprovalTask $approvable): void
	{
		$approvable->load('approvalTasks.approver', 'approvalTasks.executor:id,name', 'approvalTaskHistories.approver', 'approvalTaskHistories.executor:id,name');
	}

	/**
	 * 包装审核对象
	 * @param ApprovableTarget $approvable
	 * @return array
	 */
	public function wrapApprovable(ApprovableTarget $approvable): array
	{
		return [
			'id' => $approvable->getKey(),
			'type' => $approvable->getApprovableType(),
			'url' => $approvable->getApproveUrl(),
			'message' => $approvable->getApproveTodoMessage(),
			'slug' => $approvable->getModelSlug(),
			'approval_status' => $approvable->approval_status ?? null,
			'approval_comment' => $approvable->approval_comment ?? null,
			'approval_at' => $approvable->approval_at ?? null,
			'created_at' => $approvable->created_at?->toDateTimeString() ?? null,
			'updated_at' => $approvable->updated_at?->toDateTimeString() ?? null,
		];
	}

	/**
	 * 根据 slug 获取审核对象
	 * @param $slug
	 * @return BaseModel|ApprovableTarget|null
	 */
	public function getApprovableBySlug($slug): BaseModel|ApprovableTarget|null
	{

		$approvables = config('approval.approvables');

		foreach ($approvables as $approvable) {
			foreach ($approvable['children'] as $item) {
				/**
				 * @var BaseModel|ApprovableTarget $model
				 */
				$model = app($item);
				if ($model->getModelSlug() === $slug) {
					return $model;
				}
			}
		}
		return null;
	}

	/**
	 * 获取审核对象绑定的审核流程
	 * 先判断是否对象自身有绑定审核流程，有的话就用自身的流程
	 * 如果自身没有单独绑定，那就用审核类别绑定的流程
	 * @param ApprovableTarget $approvable
	 * @return array [$process, $binding]
	 */
	private function getApprovableProcess(ApprovableTarget $approvable): array
	{
		//TODO 这里目前使用 approval_process_id 属性进行判断不太全面，后续有需求可以改进
		if (array_key_exists('approval_process_id', $approvable->attributesToArray()) && $approvable->approval_process_id) {
			$process = ApprovalProcess::with(['nodes'])->find($approvable->approval_process_id);
			//单独绑定的不支持自动审核
			if ($process) {
				return [$process, null];
			}
		}
		$binding = ApprovalProcessBinding::with(['process', 'process.nodes'])->where("approvable_type", get_class($approvable))->first();

		if ($binding) {
			return [$binding->process, $binding];
		}

		return [null, null];
	}

	/**
	 * 发送待审核通知给审核人
	 * @param ApprovalTask $task
	 * @param ApprovableTarget $approvable
	 * @return void
	 */
	private function sendApprovalTodoNotification(ApprovalTask $task, ApprovableTarget $approvable): void
	{
		if ($task->approver_type === User::class) {
			$task->approver->notify(new ApproveTodo($task, $approvable));
		} else if ($task->approver_type === Department::class) {
			$users = $task->approver->users;
			foreach ($users as $user) {
				$user->notify(new ApproveTodo($task, $approvable));
			}
		} else if ($task->approver_type === Role::class) {
			$users = $task->approver->users;
			foreach ($users as $user) {
				$user->notify(new ApproveTodo($task, $approvable));
			}
		}
	}

	/**
	 * 创建审核节点任务实例
	 * @param ApprovableTarget $approvable
	 * @param ApprovalProcess $process
	 * @return array
	 */
	private function createNodeTaskEntity(ApprovableTarget $approvable, ApprovalProcess $process): array
	{

		$tasks = [];

		//如果后续结点不可见， 则只创建第一节点为的审核任务
		if ($process->subsequent_action === ApprovalSubsequentAction::Invisible) {
			//如果后续结点不可见，则创建所有节点的审核任务，并只有第一个节点为可审核状态
			foreach ($process->nodes as $index => $node) {
				$task = $this->createNodeTask($process, $node, $approvable,
					$index === 0 ? ApprovalSubsequentAction::Approve : ApprovalSubsequentAction::Invisible
				);

				$tasks[] = $task;

				//发送待办消息
				if ($index === 0) {
					$this->sendApprovalTodoNotification($task, $approvable);
				}
			}
		} else if ($process->subsequent_action === ApprovalSubsequentAction::Visible) {
			//如果后续结点可见，则创建所有节点的审核任务，并只有第一个节点为可审核状态
			foreach ($process->nodes as $index => $node) {
				$task = $this->createNodeTask($process, $node, $approvable,
					$index === 0 ? ApprovalSubsequentAction::Approve : ApprovalSubsequentAction::Visible
				);

				$tasks[] = $task;

				//发送待办消息
				if ($index === 0) {
					$this->sendApprovalTodoNotification($task, $approvable);
				}

			}
		} else if ($process->subsequent_action === ApprovalSubsequentAction::Approve) {
			foreach ($process->nodes as $node) {
				$task = $this->createNodeTask($process, $node, $approvable, ApprovalSubsequentAction::Approve);

				$tasks[] = $task;

				//发送待办消息
				$this->sendApprovalTodoNotification($task, $approvable);
			}
		}

		//如果审核对象本身有审核状态快照，则更新
		if (array_key_exists('approval_status', $approvable->attributesToArray())) {
			$approvable->approval_status = ApprovalStatus::Pending;
			$approvable->save();
		}

		return [$tasks, null];
	}

	/**
	 * 创建节点审核任务
	 * @param ApprovalProcess $process
	 * @param ApprovalProcessNode $node
	 * @param $approvable
	 * @param $subsequent_action
	 * @return ApprovalTask
	 */
	private function createNodeTask(ApprovalProcess $process, ApprovalProcessNode $node, $approvable, $subsequent_action): ApprovalTask
	{
		if ($node->approver_type === ApproverTypes::LocalDepartment) {
			//本部门直接使用 Approvable 的部门 id
			$approver_type = Department::class;
			$approver_id = $approvable->department_id;
		} else if ($node->approver_type === ApproverTypes::SuperiorDepartment) {
			//在 Node 定义的时候，如果是上级部门审核，那么 approver_id 就是部门的 id，这里需要转换成上级部门的 id
			$approver_type = Department::class;
			$approver_id = Department::where('id', $approvable->department_id)->first()->parent()->first()->id;
		} else {
			$approver_type = match ($node->approver_type) {
				ApproverTypes::DesignatedDepartment => Department::class,
				ApproverTypes::DesignatedRole => Role::class,
				ApproverTypes::DesignatedUser => User::class,
			};
			$approver_id = $node->approver_id;
		}

		return ApprovalTask::create([
			'approval_process_id' => $process->id,
			'approval_process_node_id' => $node->id,
			'approvable_id' => $approvable->id,
			'status' => ApprovalStatus::Pending,
			'approvable_type' => get_class($approvable),
			'approver_id' => $approver_id,
			'approver_type' => $approver_type,
			'subsequent_action' => $subsequent_action,
		]);
	}

}
