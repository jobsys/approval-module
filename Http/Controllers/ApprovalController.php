<?php

namespace Modules\Approval\Http\Controllers;

use App\Http\Controllers\BaseManagerController;
use App\Models\Department;
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
		];

		$subsequent_action_options = [
			['label' => '不可见', 'value' => ApprovalSubsequentAction::Invisible],
			['label' => '可见不可审核', 'value' => ApprovalSubsequentAction::Visible],
			['label' => '可审核', 'value' => ApprovalSubsequentAction::Approve]
		];

		$binding_items = collect(config('approval.approvables'))->map(function ($item) {
			$item['children'] = collect($item['children'])->map(function ($approval) {
				$binding = ApprovalProcessBinding::where('approvable_type', $approval)->first();
				$approval_type = app($approval)->getApprovableType();
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
				$approvable_entity = app($approvable);

				if (!$this->login_user->can($approvable_entity->getApprovableAuth())) {
					continue;
				}

				$query = $approvalService->getUserApprovable($approvable_entity, [ApprovalStatus::Pending], [ApprovalSubsequentAction::Approve]);

				$children[] = [
					'name' => $approvable_entity->getApprovableType(),
					'slug' => $approvable_entity->getModelSlug(),
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
			$input['creator_id'] = $this->login_user_id;
			$process = ApprovalProcess::create($input);
		}

		ApprovalProcessNode::where('approval_process_id', $process->id)->delete();

		foreach ($nodes as $node) {
			unset($node['id']);
			unset($node['updated_at']);
			unset($node['created_at']);
			$node['approval_process_id'] = $process->id;
			$node['creator_id'] = $this->login_user_id;
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

	public function taskItems(ApprovalService $approvalService,)
	{
		$slug = request()->input('slug');
		$status = request()->input('status', false);

		$approvable = $approvalService->getApprovableBySlug($slug);


		if (!$this->login_user->can($approvable->getApprovableAuth())) {
			return $this->message("无{$approvable->getApprovableType()}审核权限");
		}

		if ($status === 'pending') {
			$query = $approvalService->getUserApprovable($approvable, [ApprovalStatus::Pending], [ApprovalSubsequentAction::Approve]);
		} else {
			$query = $approvalService->getUserApprovable($approvable, null, [ApprovalSubsequentAction::Approve, ApprovalSubsequentAction::Visible]);
		}

		$pagination = $query->latest()->paginate();

		$pagination->getCollection()->transform(fn($item) => $approvalService->wrapApprovable($item));

		return $this->json($pagination);
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

		foreach ($input['ids'] as $id) {
			/**
			 * @var ApprovableTarget|Model $approvable
			 */
			$approvable = $model->where('id', $id)->first();

			[, $error] = $service->approve($approvable, $input['approval_status'], $input['approval_comment'] ?? '', $input['approval_remark'] ?? '');

			log_access('批量审核对象', $approvable);
		}

		return $this->json();
	}
}
