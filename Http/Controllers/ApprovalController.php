<?php

namespace Modules\Approval\Http\Controllers;

use App\Http\Controllers\BaseManagerController;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Approval\Contracts\ApprovableTarget;
use Modules\Approval\Entities\ApprovalProcess;
use Modules\Approval\Entities\ApprovalProcessBinding;
use Modules\Approval\Entities\ApprovalProcessNode;
use Modules\Approval\Entities\ApprovalTask;
use Modules\Approval\Enums\ApprovalStatus;
use Modules\Approval\Enums\ApprovalSubsequentAction;
use Modules\Approval\Enums\ApprovalVendor;
use Modules\Approval\Enums\ApproverTypes;
use Modules\Approval\Services\ApprovalService;
use Modules\Permission\Entities\Role;

class ApprovalController extends BaseManagerController
{
	public function pageApprovalProcess()
	{

		$role_options = Role::get()->map(fn($item) => [
			'value' => $item->id,
			'label' => $item->name,
		]);

		$department_options = Department::get()->map(fn($item) => [
			'value' => $item->id,
			'label' => $item->name,
		]);

		$approver_options = [
			['label' => '本部门', 'value' => ApproverTypes::LocalDepartment],
			['label' => '上级部门', 'value' => ApproverTypes::SuperiorDepartment],
			['label' => '指定部门', 'value' => ApproverTypes::DesignatedDepartment],
			['label' => '指定角色', 'value' => ApproverTypes::DesignatedRole],
			['label' => '指定用户', 'value' => ApproverTypes::DesignatedUser],
			['label' => '手动指定', 'value' => ApproverTypes::CustomizeUser],
		];

		$subsequent_action_options = [
			['label' => '不可见', 'value' => ApprovalSubsequentAction::Invisible],
			['label' => '可见不可审核', 'value' => ApprovalSubsequentAction::Visible],
			['label' => '可审核', 'value' => ApprovalSubsequentAction::Approve]
		];

		$binding_items = collect(config('approval.approvables'))->map(function ($item) {
			$item['children'] = collect($item['children'])->map(function ($approval) {
				$binding = ApprovalProcessBinding::where('approvable_type', $approval)->first();
				$approval_type = $approval::getApprovableType();
				return [
					'key' => $approval_type,
					'service_name' => $approval_type,
					'service_value' => $approval,
					'is_auto_approve' => $binding?->is_auto_approve ?? false,
					'auto_approve_status' => $binding?->auto_approve_status,
					'auto_approve_comment' => $binding?->auto_approve_comment,
					'process' => $binding?->approval_process_id
				];
			});
			return [
				'key' => $item['slug'],
				'service_name' => $item['name'],
				'children' => $item['children']
			];
		});

		$process_options = ApprovalProcess::get(['is_active', 'id', 'name'])->map(fn(ApprovalProcess $item) => [
			'label' => $item->name, 'value' => $item->id
		]);

		return Inertia::render('PageApprovalProcess@Approval', [
			'roleOptions' => $role_options,
			'departmentOptions' => $department_options,
			'approverOptions' => $approver_options,
			'subsequentActionOptions' => $subsequent_action_options,
			'bindingItems' => $binding_items,
			'processOptions' => $process_options,
		]);
	}

	public function pageApprovalTodo(ApprovalService $approvalService)
	{
		$approvables = config('approval.approvables');


		foreach ($approvables as $index => $group) {

			$children = [];

			foreach ($group['children'] as $approvable) {
				/**
				 * @var ApprovableTarget $approvable_entity
				 */
				$approvable_entity = app($approvable::getApprovableClass());

				if (!auth()->user()->can($approvable_entity->getApprovableAuth())) {
					continue;
				}

				$query = $approvalService->getUserApprovable($approvable_entity, [ApprovalStatus::Pending], [ApprovalSubsequentAction::Approve]);

				$children[] = [
					'name' => $approvable_entity::getApprovableType(),
					'slug' => $approvable_entity::getModelSlug(),
					'approvable' => $approvable,
					'count' => $query->count()
				];
			}

			$approvables[$index]['children'] = $children;
		}

		return Inertia::render('PageApprovalTodo@Approval#TodoLayout', [
			'approvables' => $approvables
		]);
	}

	public function pageApprovalTodoList($slug)
	{
		return Inertia::render('PageApprovalTodoList@Approval#TodoLayout', ['slug' => $slug]);
	}

	public function processItems(Request $request)
	{
		$pagination = ApprovalProcess::withCount(['nodes'])->filterable()->paginate();
		return $this->json($pagination);
	}

	public function processItem(Request $request, $id)
	{
		$item = ApprovalProcess::with(['nodes'])->find($id);
		log_access('查看审核流程', $item);
		return $this->json($item);
	}

	public function processEdit(Request $request)
	{
		list($input, $error) = land_form_validate(
			$request->only(['id', 'name', 'subsequent_action', 'is_active', 'remark', 'nodes']),
			[
				'name' => 'bail|required|string',
				//'subsequent_action' => 'bail|required|string',
				'nodes' => 'bail|required|array',
				'nodes.*.name' => 'bail|required|string',
				//'nodes.*.approver_id' => 'bail|required|integer', //添加了上级部门，意味着可以无需指定审核部门ID
				'nodes.*.approver_type' => 'bail|required|string',
			],
			[
				'name' => '审核流程名称',
				//'subsequent_action' => '后续节点权限',
				'nodes' => '审核节点',
				'nodes.*.name' => '审核节点名称',
				//'nodes.*.approver_id' => '审核人',
				'nodes.*.approver_type' => '审核人类型',
			]
		);

		if ($error) {
			return $this->message($error);
		}

		$nodes = $input['nodes'];
		unset($input['nodes']);

		if (isset($input['id']) && $input['id']) {
			ApprovalProcess::where('id', $input['id'])->update($input);
			$process = ApprovalProcess::find($input['id']);
		} else {
			$input['creator_id'] = auth()->id();
			$process = ApprovalProcess::create($input);
		}

		ApprovalProcessNode::where('approval_process_id', $process->id)->delete();

		foreach ($nodes as $node) {
			unset($node['id']);
			unset($node['updated_at']);
			unset($node['created_at']);
			if ($node['approver_type'] === ApproverTypes::CustomizeUser) {
				$node['approver_id'] = ApprovalVendor::UNDETERMINED_USER;
			}
			$node['approval_process_id'] = $process->id;
			$node['creator_id'] = auth()->id();
			ApprovalProcessNode::create($node);
		}

		return $this->json();
	}

	public function processDelete(Request $request)
	{
		$id = $request->input('id');

		$item = ApprovalProcess::find($id);

		if (!$item) {
			return $this->message('找不到审核流程');
		}

		ApprovalProcessNode::where('approval_process_id', $id)->delete();

		$item->delete();

		return $this->json();

	}

	public function taskItems(ApprovalService $approvalService)
	{
		$slug = request()->input('slug');
		$status = request()->input('status', false);

		$approvable = $approvalService->getApprovableBySlug($slug);


		if (!auth()->user()->can($approvable->getApprovableAuth())) {
			return $this->message("无{$approvable::getApprovableType()}审核权限");
		}

		if ($status === 'pending') {
			$query = $approvalService->getUserApprovable($approvable, [ApprovalStatus::Pending], [ApprovalSubsequentAction::Approve]);
		} else {
			$query = $approvalService->getUserApprovable($approvable, null, [ApprovalSubsequentAction::Approve, ApprovalSubsequentAction::Visible]);
		}

		$pagination = $query->approval()->latest()->paginate();

		$pagination->getCollection()->transform(fn($item) => $approvalService->wrapApprovable($item));

		return $this->json($pagination);
	}


	public function taskReset(ApprovalService $service)
	{

		$approvable_slug = request('approvable_slug');
		$approvable_id = request('approvable_id');

		if (!$approvable_slug) {
			return $this->message('审核对象类型不存在');
		}

		if (!$approvable_id) {
			return $this->message('审核对象不存在');
		}


		$model = $service->getApprovableBySlug($approvable_slug);

		/**
		 * @var ApprovableTarget|Model $approvable
		 */
		$approvable = $model->where('id', $approvable_id)->first();

		[, $error] = $service->resetApprovalTask($approvable);

		if ($error) {
			return $this->message($error);
		}
		return $this->json();

	}

	public function bindingEdit(Request $request)
	{

		$items = $request->input('items');

		foreach ($items as $item) {
			if (empty($item['service_value'])) {
				continue;
			}

			$is_auto_approve = $item['is_auto_approve'] ?? false;

			$data = [
				'approval_process_id' => $item['process'] ?? null,
				'approvable_type' => $item['service_value'],
				'is_auto_approve' => $is_auto_approve,
				'auto_approve_status' => $is_auto_approve ? $item['auto_approve_status'] ?? null : null,
				'auto_approve_comment' => $is_auto_approve ? $item['auto_approve_comment'] ?? null : null,
			];

			$exist = ApprovalProcessBinding::where("approvable_type", $item['service_value'])->first();
			if ($exist) {
				$exist->update($data);
			} else {
				ApprovalProcessBinding::create($data);
			}
		}

		return $this->json();
	}

	public function approve(Request $request, ApprovalService $service)
	{
		list($input, $error) = land_form_validate(
			$request->only(['id', 'approval_status', 'approval_comment', 'approval_remark']),
			[
				'id' => 'bail|required|integer',
				'approval_status' => 'bail|required|string',
				'approval_comment' => 'bail|required|string',
			], [
				'id' => '任务ID',
				'approval_status' => '审核状态',
				'approval_comment' => '审核意见',
			]
		);

		if ($error) {
			return $this->message($error);
		}

		$task = ApprovalTask::with(['approvable'])->where('id', $input['id'])->first();
		if (!$task) {
			return $this->message('审核任务不存在');
		}

		[, $error] = $service->approve($task->approvable, $input['approval_status'], $input['approval_comment'] ?? '', $input['approval_remark'] ?? '');

		if ($error) {
			return $this->message($error);
		}

		log_access('审核对象', $task);

		return $this->json();
	}

	public function batchApprove(Request $request, ApprovalService $service)
	{
		list($input, $error) = land_form_validate(
			$request->only(['ids', 'slug', 'approval_status', 'approval_comment', 'approval_remark']),
			[
				'ids' => 'bail|required|array',
				'slug' => 'bail|required|string',
				'approval_status' => 'bail|required|string',
				'approval_comment' => 'bail|required|string',
			], [
				'ids' => '审核任务',
				'slug' => '审核类型',
				'approval_status' => '审核状态',
				'approval_comment' => '审核意见',
			]
		);

		if ($error) {
			return $this->message($error);
		}

		$model = $service->getApprovableBySlug($input['slug']);

		$success_count = 0;

		foreach ($input['ids'] as $id) {
			/**
			 * @var ApprovableTarget|Model $approvable
			 */
			$approvable = $model->where('id', $id)->first();

			[, $error] = $service->approve($approvable, $input['approval_status'], $input['approval_comment'] ?? '', $input['approval_remark'] ?? '');

			if (!$error) {
				$success_count += 1;
			}

			log_access('批量审核对象', $approvable);
		}

		return $this->json(['total_count' => count($input['ids']), 'success_count' => $success_count]);
	}

	public function customizeDetail()
	{
		$approval_process_id = request('approval_process_id');
		$permission = request('permission');

		$approval_process = ApprovalProcess::with(['nodes'])->find($approval_process_id, ['id', 'name']);

		$nodes = $approval_process->nodes;

		$approval_process->unsetRelation('nodes');

		$node_options = $nodes->where('approver_type', ApproverTypes::CustomizeUser)->map(fn(ApprovalProcessNode $item) => [
			'label' => $item->name,
			'value' => $item->id,
		])->values();

		//只有有审核权限的用户
		$user_options = User::whereHas('permissions', fn($query) => $query->where('name', $permission))
			->orWhereHas('roles.permissions', fn($query) => $query->where('name', $permission))
			->get(['id', 'name', 'nickname', 'work_num'])->map(fn($item) => [
				'title' => $item->work_num ? "{$item->nickname}($item->work_num)" : $item->nickname,
				'key' => strval($item->id),
			])->values();

		return $this->json([
			'process' => $approval_process,
			'nodeOptions' => $node_options,
			'userOptions' => $user_options
		]);


	}

	public function customizeAssign(ApprovalService $service)
	{
		list($input, $error) = land_form_validate(
			request()->only(['node_id', 'slug', 'scope', 'method', 'user_ids', 'number']),
			[
				'node_id' => 'bail|required|numeric',
				'slug' => 'bail|required|string',
				'scope' => 'bail|required',
				'method' => 'bail|required|string',
				'user_ids' => 'bail|required|array',
			], [
				'node_id' => '审核节点',
				'slug' => '审核对象',
				'scope' => '数据范围',
				'method' => '分配方式',
				'user_ids' => '审核人员',
			]
		);

		if ($error) {
			return $this->message($error);
		}

		$model = $service->getApprovableBySlug($input['slug']);

		$tasks = ApprovalTask::where('approval_process_node_id', $input['node_id'])
			->where('approvable_type', get_class($model))
			->where(function ($query) use ($input) {
				if (is_array($input['scope']) && count($input['scope']) > 0) {
					return $query->whereIn('approvable_id', $input['scope']);
				}
				return $query;
			})->where('approver_type', User::class)
			->where('status', ApprovalStatus::Pending)->get(['id'])->pluck('id');

		if ($input['method'] === 'avg') {
			$chunk_size = ceil($tasks->count() / count($input['user_ids']));
		} else {
			$chunk_size = $input['number'];
		}
		//$tasks 按 user_ids 每个人分配 $chunk_size 个任务
		$chunks = $tasks->chunk($chunk_size)->toArray();
		foreach ($input['user_ids'] as $index => $user_id) {
			if (!isset($chunks[$index])) {
				break;
			}
			ApprovalTask::whereIn('id', $chunks[$index])->update(['approver_id' => $user_id]);
		}

		return $this->json();
	}

}
