<?php

namespace Modules\Approval\Services;

use App\Models\Department;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Approval\Entities\ApprovalProcess;
use Modules\Approval\Entities\ApprovalProcessNode;
use Modules\Approval\Entities\ApprovalTask;
use Modules\Approval\Entities\ApprovalTaskHistory;
use Modules\Approval\Enums\ApprovalStatus;
use Modules\Approval\Enums\ApprovalSubsequentAction;
use Modules\Approval\Enums\ApproverTypes;
use Modules\Permission\Entities\Role;
use Modules\Starter\Traits\Snapshotable;

class ApprovalService
{
	/**
	 * 创建审批任务
	 * @param Model $approvable
	 * @param array $config
	 * @return array
	 */
	public function createApprovalTask(Model $approvable, array $config): array
	{

		$type = $config['type'];

		$process = ApprovalProcess::with(['nodes'])->where('type', $type)->first();

		if (!$process || !$process->nodes->count()) {
			return [false, '该审批流程未配置审批节点'];
		}

		if ($approvable->approvalTasks()->count() && $approvable->approvalTaskHistories()->count()) {
			//如果已经有审批历史，则添加一条审批对象已经更新的记录，如果没有审批历史，审批对象可以任意更新
			/**
			 * 用审批任务的属性创建更新历史
			 * @var ApprovalTask $task
			 */
			$task = $approvable->approvalTasks()->first();
			ApprovalTaskHistory::create(array_merge($task->getOriginal(), [
				'status' => ApprovalStatus::Updated,
				'comment' => '',
				'approve_user_id' => null,
				'approved_at' => null,
				'created_at' => now(),
				'updated_at' => now(),
			], ['id' => null]));

		}

		//然后删除所有审批任务
		$approvable->approvalTasks()->delete();

		//如果后续结点不可见， 则只创建第一节点为的审批任务
		if ($process->subsequent_action === ApprovalSubsequentAction::Invisible) {
			//如果后续结点不可见，则创建所有节点的审批任务，并只有第一个节点为可审批状态
			foreach ($process->nodes as $index => $node) {
				$task = $this->createNodeTask($process, $node, $approvable,
					$index === 0 ? ApprovalSubsequentAction::Approve : ApprovalSubsequentAction::Invisible
				);


				if (!$task) {
					return [false, '审批任务创建失败'];
				}

				//发送待办消息
				if ($index === 0) {
					$this->sendApprovalNotification($config, $task, $approvable);
				}
			}
		} else if ($process->subsequent_action === ApprovalSubsequentAction::Visible) {
			//如果后续结点可见，则创建所有节点的审批任务，并只有第一个节点为可审批状态
			foreach ($process->nodes as $index => $node) {
				$task = $this->createNodeTask($process, $node, $approvable,
					$index === 0 ? ApprovalSubsequentAction::Approve : ApprovalSubsequentAction::Visible
				);

				if (!$task) {
					return [false, '审批任务创建失败'];
				}

				//发送待办消息
				if ($index === 0) {
					$this->sendApprovalNotification($config, $task, $approvable);
				}

			}
		} else if ($process->subsequent_action === ApprovalSubsequentAction::Approve) {
			foreach ($process->nodes as $node) {
				$task = $this->createNodeTask($process, $node, $approvable, ApprovalSubsequentAction::Approve);
				if (!$task) {
					return [false, '审批任务创建失败'];
				}
				//发送待办消息
				$this->sendApprovalNotification($config, $task, $approvable);
			}
		}

		//如果审批对象本身有审批状态快照，则更新
		if (array_key_exists('approval_status', $approvable->attributesToArray())) {
			$approvable->approval_status = ApprovalStatus::Pending;
			$approvable->save();
		}

		return [true, null];
	}


	/**
	 * 获取用户待审批的审批对象
	 * @param Builder|Model $builder
	 * @param User $user
	 * @param ApprovalProcess $process
	 * @param string $status
	 * @return Builder
	 */
	public function getUserApprovable(Builder|Model $builder, User $user, ApprovalProcess $process, string $status = ''): Builder
	{

		if (!$user->isSuperAdmin()) {
			$department_ids = $user->departments()->pluck('id')->toArray();
			$role_ids = $user->roles()->pluck('id')->toArray();
			$builder->withWhereHas('approvalTasks',
				function ($query) use ($user, $department_ids, $role_ids, $process, $status) {
					$query->when($status, function ($query, $status) {
						$query->where('status', $status);
					})->where('approval_process_id', $process->id);
					$query->where(function ($query) use ($user) {
						$query->where('approver_id', $user->id)->where('approver_type', User::class);
					})->orWhere(function ($query) use ($department_ids) {
						$query->whereIn('approver_id', $department_ids)->where('approver_type', Department::class);
					})->orWhere(function ($query) use ($role_ids) {
						$query->whereIn('approver_id', $role_ids)->where('approver_type', Role::class);
					})->select(['id', 'approvable_id', 'approvable_type', 'status', 'subsequent_action']);
				}
			)->whereHas('approvalTasks');
		} else {
			$builder->withWhereHas('approvalTasks',
				function ($query) use ($process, $status) {
					$query->when($status, function ($query, $status) {
						$query->where('status', $status);
					})->where('approval_process_id', $process->id)->select(['id', 'approvable_id', 'approvable_type', 'status', 'subsequent_action']);
				}
			)->whereHas('approvalTasks');
		}

		return $builder;
	}


	/**
	 * 审批
	 * @param Model $approvable
	 * @param ApprovalProcess $process
	 * @param string $approval_status
	 * @param string $approval_comment
	 * @return array
	 */
	public function approve(Model $approvable, ApprovalProcess $process, string $approval_status, string $approval_comment = ''): array
	{
		$user = request()->user();
		$result = [
			'approval_status' => ApprovalStatus::Pending,
			'approval_comment' => $approval_comment,
		];

		$approvable->load(['approvalTasks' => function ($query) use ($process) {
			$query->where('approval_process_id', $process->id);
		}]);

		$task = $this->getUserApprovableTask($process, $approvable);

		if (!$task) {
			return [false, '审批任务不存在'];
		}

		if ($task->status !== ApprovalStatus::Pending) {
			return [false, '审批任务已审批'];
		}

		try {
			DB::beginTransaction();
			//保存审批状态
			$task->status = $approval_status;
			$task->comment = $approval_comment;
			$task->approve_user_id = $user->id;
			$task->approved_at = now();
			$task->subsequent_action = ApprovalSubsequentAction::Visible;
			$task->save();


			//保存审批历史
			ApprovalTaskHistory::create(array_merge($task->getOriginal(), ['id' => null]));

			//保存审批对象快照
			if (in_array(Snapshotable::class, class_uses_recursive($approvable))) {
				$approvable->snapshot();
			}


			//更新后续节点的操作
			/**
			 * @var $tasks Collection
			 */
			$tasks = $approvable->approvalTasks->sortBy('id')->sortBy('weight');

			$task_index = $tasks->search(function ($item) use ($task) {
				return $item->id == $task->id;
			});

			/**
			 * @var $next_task ApprovalTask
			 */
			$next_task = $tasks->get($task_index + 1);

			if ($next_task && $next_task->subsequent_action !== ApprovalSubsequentAction::Approve) {
				$next_task->subsequent_action = ApprovalSubsequentAction::Approve;
				$next_task->save();

				$config = collect(config('approval.approval_types'))->first(function ($item) use ($process) {
					return $item['type'] == $process->type;
				});
				if ($config) {
					$this->sendApprovalNotification($config, $next_task, $approvable);
				}
			}

			if (!$next_task || $approval_status === ApprovalStatus::Rejected) {
				$result['approval_status'] = $approval_status;
				$result['approval_comment'] = $approval_comment;
			}


			//如果有审批对象有结果快照的，那在审批不过通或者是最一个审批节点的时候，更新审批对象的审批状态
			if (array_key_exists('approval_status', $approvable->attributesToArray())) {
				if (!$next_task || $approval_status !== ApprovalStatus::Approved) {
					$approvable->approval_status = $approval_status;
					if (array_key_exists('approval_comment', $approvable->attributesToArray())) {
						$approvable->approval_comment = $approval_comment;
					}
					$approvable->save();
				}
			}


			//前面的任务节点如果是未审批的，则设置为跳过，而且不可再审批
			$previous_tasks = $tasks->slice(0, $task_index);
			foreach ($previous_tasks as $previous_task) {
				if ($previous_task->subsequent_action === ApprovalSubsequentAction::Approve || $user->isSuperAdmin()) {
					$previous_task->subsequent_action = ApprovalSubsequentAction::Visible;
					if ($previous_task->status === ApprovalStatus::Pending) {
						$previous_task->status = ApprovalStatus::Skipped;
					}
					$previous_task->save();
				}
			}
			DB::commit();
			return [$result, null];
		} catch (Exception $e) {
			DB::rollBack();
			Log::error('Approve::' . $e->getMessage());
			return [false, '审批失败，请联系系统管理员'];
		}
	}


	/**
	 * 获取当前用户对于某个审批对象的审批任务
	 * @param ApprovalProcess $process
	 * @param Model $approvable
	 * @return ApprovalTask|null
	 */
	public function getUserApprovableTask(ApprovalProcess $process, Model $approvable): ApprovalTask|null
	{

		$user = request()->user();

		if (!$user->isSuperAdmin()) {
			$department_ids = $user->departments()->pluck('id')->toArray();
			$role_ids = $user->roles()->pluck('id')->toArray();

			$query = ApprovalTask::where('approval_process_id', $process->id)
				->where('approvable_id', $approvable->id)
				->where('approvable_type', get_class($approvable))
				->where('subsequent_action', ApprovalSubsequentAction::Approve);
			//->where('status', ApprovalStatus::Pending);

			$task = $query->where(function ($query) use ($user, $department_ids, $role_ids) {
				$query->where(function ($query) use ($user) {
					$query->where('approver_id', $user->id)->where('approver_type', User::class);
				})->orWhere(function ($query) use ($department_ids) {
					$query->whereIn('approver_id', $department_ids)->where('approver_type', Department::class);
				})->orWhere(function ($query) use ($role_ids) {
					$query->whereIn('approver_id', $role_ids)->where('approver_type', Role::class);
				});
			})->orderBy('id', 'desc')->first();
		} else {
			$task = ApprovalTask::where('approval_process_id', $process->id)
				->where('approvable_id', $approvable->id)
				->where('approvable_type', get_class($approvable))
				//->where('status', ApprovalStatus::Pending)
				->orderBy('id', 'desc')->first();

		}
		return $task;
	}


	/**
	 * 为审批对象添加审批历史和详情
	 * @param ApprovalProcess $process
	 * @param Model $approvable
	 * @return void
	 */
	public function getApprovalDetail(ApprovalProcess $process, Model $approvable): void
	{
		$approvable->load('approvalTasks.approver', 'approvalTasks.executor:id,name', 'approvalTaskHistories.approver', 'approvalTaskHistories.executor:id,name');
		$approvable->{'current_task'} = $this->getUserApprovableTask($process, $approvable);
	}


	/**
	 * 创建节点审批任务
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
			//在 Node 定义的时候，如果是上级部门审批，那么 approver_id 就是部门的 id，这里需要转换成上级部门的 id
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

	/**
	 * 发送待审批通知给审批人
	 * @param array $config
	 * @param ApprovalTask $task
	 * @param Model $approvable
	 * @return void
	 */
	private function sendApprovalNotification(array $config, ApprovalTask $task, Model $approvable): void
	{
		if (isset($config['approve_todo']) && $notification = $config['approve_todo']) {
			if ($task->approver_type === User::class) {
				$task->approver->notify(new $notification($task, $approvable));
			} else if ($task->approver_type === Department::class) {
				$users = $task->approver->users;
				foreach ($users as $user) {
					$user->notify(new $notification($task, $approvable));
				}
			} else if ($task->approver_type === Role::class) {
				$users = $task->approver->users;
				foreach ($users as $user) {
					$user->notify(new $notification($task, $approvable));
				}
			}
		}
	}
}
