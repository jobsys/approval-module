<template>
	<a-page-header class="shadow mb-4 rounded-lg" title="返回" @back="onBack" />

	<a-tabs v-model:activeKey="state.activeKey" @change="onChangeTab">
		<a-tab-pane key="pending" tab="待审核"></a-tab-pane>
		<a-tab-pane key="all" tab="全部任务"></a-tab-pane>
	</a-tabs>

	<NewbieTable
		ref="tableRef"
		:row-selection="state.activeKey === 'pending'"
		:filterable="false"
		:key="state.activeKey"
		:url="route('api.manager.approval.task.items', { slug, status: state.activeKey })"
		:columns="tableColumns()"
		row-key="id"
	>
		<template #append>
			<NewbieButton type="primary" v-if="state.activeKey === 'pending'" :icon="h(AuditOutlined)" @click="onBatchApprove"
				>批量审核
			</NewbieButton>
		</template>
	</NewbieTable>

	<NewbieModal v-model:visible="state.showApproveModal" title="批量审核">
		<a-alert class="mb-4">
			<template #description
				>已选择 <span class="font-bold">{{ state.selectedIds.length }} </span> 条数据
			</template>
		</a-alert>
		<a-form :model="state.approveForm" :label-col="{ span: 4 }" @finish="onSubmit">
			<a-form-item label="审核结果" name="approval_status" required :rules="{ required: true, message: '请选择审核结果', trigger: 'change' }">
				<a-radio-group v-model:value="state.approveForm.approval_status">
					<template v-for="(option, index) in approvalOptions" :key="index">
						<a-radio v-if="option.value !== 'pending'" :value="option.value">{{ option.label }}</a-radio>
					</template>
				</a-radio-group>
			</a-form-item>

			<a-form-item
				label="审核意见"
				name="approval_comment"
				required
				:rules="{ required: state.approveForm.approval_status === 'rejected', message: '请填写审核意见', trigger: 'blur' }"
			>
				<a-textarea v-model:value="state.approveForm.approval_comment" placeholder="请填写审核意见"></a-textarea>
			</a-form-item>
			<div class="ml-[120px] mb-5">
				<div v-if="state.approveForm.approval_status === 'approved'">
					<a-button
						size="small"
						type="primary"
						ghost
						@click="() => (state.approveForm.approval_comment = tag)"
						v-for="tag in state.quickFills['approved']"
						color="green"
						:key="tag"
						class="mr-1"
					>
						{{ tag }}
					</a-button>
				</div>
				<div v-if="state.approveForm.approval_status === 'rejected'">
					<a-button
						size="small"
						@click="() => (state.approveForm.approval_comment = tag)"
						v-for="tag in state.quickFills['rejected']"
						danger
						:key="tag"
						class="mr-1"
					>
						{{ tag }}
					</a-button>
				</div>
			</div>
			<a-form-item label="审核备注" help="该信息对于申请者不可见" name="approval_remark">
				<a-textarea v-model:value="state.approveForm.approval_remark" placeholder="请填写审核备注"></a-textarea>
			</a-form-item>
			<a-divider></a-divider>
			<div class="flex justify-center">
				<a-button type="primary" html-type="submit" :loading="state.approveFetcher.loading"> 提交审核</a-button>
			</div>
		</a-form>
	</NewbieModal>
</template>

<script setup>
import { h, inject, nextTick, reactive, ref } from "vue"
import { useTableActions } from "jobsys-newbie"
import { useFetch, useProcessStatusSuccess } from "jobsys-newbie/hooks"
import { useApprovalOptions, useApprovalStatus } from "./hooks/approval"
import { AuditOutlined } from "@ant-design/icons-vue"
import { message } from "ant-design-vue"
import { router } from "@inertiajs/vue3"

const props = defineProps({
	slug: { type: String, required: true },
})

const route = inject("route")
const tableRef = ref()

const state = reactive({
	activeKey: "pending",
	showApproveModal: false,
	selectedIds: [],
	approveForm: {
		approval_status: "",
		approval_comment: "",
		approval_remark: "",
	},
	approveFetcher: {},
	quickFills: {
		approved: ["拟同意", "合格", "审核通过"],
		rejected: ["资料不足", "条件不符", "填报错误", "退回重填"],
	},
})

const approvalOptions = ref([
	{ label: "审核通过", value: "approved" },
	{ label: "审核驳回", value: "rejected" },
])

const onChangeTab = () => {
	nextTick(() => {
		tableRef.value.doFetch()
	})
}

const onBack = () => {
	router.visit(route("page.manager.todo.approval"))
}

const onBatchApprove = () => {
	const selectedRows = tableRef.value.getSelection()

	if (!selectedRows.length) {
		message.error("请先勾选数据")
		return
	}
	state.selectedIds = selectedRows.map((row) => row.id)

	state.showApproveModal = true
}

const onSubmit = async () => {
	const res = await useFetch(state.approveFetcher).post(route("api.manager.approval.approve.batch"), {
		ids: state.selectedIds,
		slug: props.slug,
		...state.approveForm,
	})
	useProcessStatusSuccess(res, () => {
		message.success("审核成功")
		tableRef.value.doFetch()
		state.showApproveModal = false
	})
}

const tableColumns = () => [
	{
		title: "审核状态",
		width: 100,
		key: "approval_status",
		filterable: {
			type: "select",
			options: useApprovalOptions(),
		},
		customRender: ({ record }) => useApprovalStatus(record),
	},
	{
		title: "审核类型",
		dataIndex: "type",
		width: 160,
		ellipsis: true,
	},
	{
		title: "审核内容",
		dataIndex: "message",
		width: 200,
		ellipsis: true,
	},
	{
		title: "提交时间",
		dataIndex: "created_at",
		width: 160,
	},
	{
		title: "更新时间",
		dataIndex: "updated_at",
		width: 160,
	},
	{
		title: "操作",
		width: 160,
		key: "operation",
		align: "center",
		fixed: "right",
		customRender({ record }) {
			const actions = []

			actions.push({
				name: "审核",
				props: {
					icon: h(AuditOutlined),
					size: "small",
				},
				action() {
					router.visit(record.url)
				},
			})
			return useTableActions(actions)
		},
	},
]
</script>
