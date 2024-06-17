<?php

namespace Modules\Approval\Http\Controllers;

use App\Http\Controllers\BaseManagerController;
use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Approval\Entities\ApprovalProcess;
use Modules\Approval\Entities\ApprovalProcessNode;
use Modules\Approval\Entities\ApprovalTask;
use Modules\Approval\Enums\ApprovalStatus;
use Modules\Approval\Enums\ApprovalSubsequentAction;
use Modules\Approval\Enums\ApproverTypes;
use Modules\Approval\Services\ApprovalService;
use Modules\Permission\Entities\Role;
use Modules\Starter\Emnus\State;

class ApprovalController extends BaseManagerController
{
	public function pageApprovalProcess()
	{
		$approval_types = array_values(config('approval.approval_types'));

		$role_options = Role::get()->map(function ($item) {
			return [
				'value' => $item->id,
				'label' => $item->display_name,
			];
		});

		$department_options = Department::get()->map(function ($item) {
			return [
				'value' => $item->id,
				'label' => $item->name,
			];
		});

		$approver_options = [
			['label' => '本部门', 'value' => ApproverTypes::LocalDepartment],
			['label' => '上级部门', 'value' => ApproverTypes::SuperiorDepartment],
			['label' => '指定部门', 'value' => ApproverTypes::DesignatedDepartment],
			['label' => '指定角色', 'value' => ApproverTypes::DesignatedRole],
			['label' => '指定用户', 'value' => ApproverTypes::DesignatedUser],
		];

		$subsequent_action_options = [
			['label' => '不可见', 'value' => ApprovalSubsequentAction::Invisible],
			['label' => '可见不可审批', 'value' => ApprovalSubsequentAction::Visible],
			['label' => '可审批', 'value' => ApprovalSubsequentAction::Approve]
		];

		return Inertia::render('PageApprovalProcess@Approval', [
			'approvalTypes' => $approval_types,
			'roleOptions' => $role_options,
			'departmentOptions' => $department_options,
			'approverOptions' => $approver_options,
			'subsequentActionOptions' => $subsequent_action_options,
		]);
	}

	public function processItems(Request $request)
	{
		$pagination = ApprovalProcess::withCount(['nodes'])->filterable()->paginate();

		log_access('查看审批流程列表');

		return $this->json($pagination);
	}

	public function processItem(Request $request, $id)
	{
		$item = ApprovalProcess::with(['nodes'])->find($id);
		log_access('查看审批流程', $id);
		return $this->json($item);
	}

	public function processEdit(Request $request)
	{
		list($input, $error) = land_form_validate(
			$request->only(['id', 'name', 'type', 'subsequent_action', 'is_active', 'remark', 'nodes']),
			[
				'name' => 'bail|required|string',
				'type' => 'bail|required|string',
				//'subsequent_action' => 'bail|required|string',
				'nodes' => 'bail|required|array',
				'nodes.*.name' => 'bail|required|string',
				//'nodes.*.approver_id' => 'bail|required|integer', //添加了上级部门，意味着可以无需指定审批部门ID
				'nodes.*.approver_type' => 'bail|required|string',
			],
			[
				'name' => '审批流程名称',
				'type' => '审批对象类型',
				//'subsequent_action' => '后续节点权限',
				'nodes' => '审批节点',
				'nodes.*.name' => '审批节点名称',
				//'nodes.*.approver_id' => '审批人',
				'nodes.*.approver_type' => '审批人类型',
			]
		);

		if ($error) {
			return $this->message($error);
		}

		$nodes = $input['nodes'];
		unset($input['nodes']);

		$unique = land_is_model_unique($input, ApprovalProcess::class, 'type', true);
		if (!$unique) {
			return $this->message('审批流程类型已存在');
		}

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

		log_access(isset($input['id']) && $input['id'] ? '编辑审批流程' : '新建审批流程', $process->id);

		return $this->json();
	}

	public function processDelete(Request $request)
	{
		$id = $request->input('id');

		$item = ApprovalProcess::find($id);

		if (!$item) {
			return $this->message('找不到审批流程');
		}

		ApprovalProcessNode::where('approval_process_id', $id)->delete();

		$item->delete();

		log_access('删除审批流程', $id);

		return $this->json();

	}

	public function approve(Request $request, ApprovalService $service)
	{
		list($input, $error) = land_form_validate(
			$request->only(['id', 'approval_status', 'approval_comment']),
			[
				'id' => 'bail|required|integer',
				'approval_status' => 'bail|required|string',
				'approval_comment' => 'bail|required|string',
			], [
				'id' => '任务ID',
				'approval_status' => '审批状态',
				'approval_comment' => '审批意见',
			]
		);

		if ($error) {
			return $this->message($error);
		}

		$task = ApprovalTask::with(['approvable'])->where('id', $input['id'])->first();
		if (!$task) {
			return $this->message('审批任务不存在');
		}

		$process = ApprovalProcess::find($task->approval_process_id);

		if (!$process) {
			return $this->message('未创建审批流程');
		}

		list($result, $error) = $service->approve($task->approvable, $process, $input['approval_status'], $input['approval_comment'] ?? '');

		if ($error) {
			return $this->message($error);
		}

		// 在项目审批所有节点都通过，或者是某一节点被驳回后给用户发送通知
		if (in_array($result['approval_status'], [ApprovalStatus::Approved, ApprovalStatus::Rejected])) {
			$config = collect(config('approval.approval_types'))->first(function ($item) use ($process) {
				return $item['type'] == $process->type;
			});

			if ($config && !empty($config['approved_event'])) {
				$event = app($config['approved_event']);
				$event::dispatch($task);
			}
		}


		log_access('审批对象', $task->id, $input['approval_status']);

		return $this->json(null, $result ? State::SUCCESS : State::FAIL);
	}

}
