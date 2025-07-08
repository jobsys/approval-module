<template>
	<div class="approval-box min-w-[440px]">
		<div v-if="canApproveTask" :class="[flex ? ' flex w-full gap-4' : ' ']">
			<a-card hoverable class="shadow" :class="[flex ? ' flex-grow' : ' ']">
				<template #title>
					<NodeIndexOutlined />
					审核流程
					<a-tooltip title="如遇审核流程变更或是审核异常，可以重置审核流程，之前的审核结果会弃用，当前内容变为待审核状态">
						<a-button ghost :icon="h(RedoOutlined)" type="primary" size="small" class="ml-2" @click="onResetTask">重置审核流程 </a-button>
					</a-tooltip>
				</template>
				<template #extra
					><a href="javascript:" @click.stop="() => (state.showHistoriesModal = true)">
						<HistoryOutlined></HistoryOutlined>
						审核记录</a
					>
				</template>
				<a-timeline v-if="approvalTasks.length">
					<a-timeline-item>
						<div class="shadow text-sm p-2 rounded border-solid border-[1px] border-gray-200 bg-gray-100">
							<span class="font-bold">提交时间</span>：{{ approvable.created_at || "" }}
						</div>
					</a-timeline-item>
					<a-timeline-item>
						<div class="shadow text-sm p-2 rounded border-solid border-[1px] border-gray-200 bg-gray-100">
							<span class="font-bold">更新时间</span>：{{ approvable.updated_at || "" }}
						</div>
					</a-timeline-item>

					<a-timeline-item v-for="task in approvalTasks" :key="task.id" :color="statusConfig(task).color">
						<div class="mb-2">
							<a-tooltip title="审核节点">
								<span class="bg-amber-500 text-white shadow-lg inline-block px-2 rounded">{{ task.node?.name }}</span>
							</a-tooltip>
						</div>
						<div>
							<a-tooltip title="审核结果">
								<!-- 状态文字 -->
								<a-tag class="w-24" :color="statusConfig(task).color">
									<template #icon>
										<component :is="statusConfig(task).icon"></component>
									</template>
									{{ statusConfig(task).text }}
								</a-tag>
							</a-tooltip>

							<a-tooltip :title="task.executor ? `审核角色: 审核人` : `审核角色`">
								<a-tag>
									<template #icon>
										<UserOutlined></UserOutlined>
									</template>
									{{ task.approver?.name || "未指定" }}
									<span v-if="task.executor" class="font-bold">: {{ task.executor.nickname }}</span>
								</a-tag>
							</a-tooltip>

							<!-- 时间 -->
							<a-tooltip title="审核时间">
								<a-tag v-if="['skipped', 'updated'].includes(task.status)">
									<template #icon>
										<ClockCircleOutlined></ClockCircleOutlined>
									</template>
									{{ task.updated_at }}
								</a-tag>
								<a-tag v-else-if="['approved', 'rejected'].includes(task.status)">
									<template #icon>
										<ClockCircleOutlined></ClockCircleOutlined>
									</template>
									{{ task.approved_at }}
								</a-tag>
							</a-tooltip>
						</div>
						<div v-if="task.comment" class="text-sm my-2 p-2 rounded border-solid border-[1px] border-gray-200 bg-gray-100">
							<span class="font-bold">审核意见</span>：{{ task.comment }}
						</div>
						<div v-if="task.remark" class="text-sm my-2 p-2 rounded border-solid border-[1px] border-gray-200 bg-gray-100">
							<span class="font-bold">审核备注</span>：{{ task.remark }}
						</div>
					</a-timeline-item>
				</a-timeline>
				<a-empty
					v-else
					:description="approvalErrorMessage"
					:image-style="{ display: 'flex', alignItems: 'center', justifyContent: 'center' }"
				>
					<template #image>
						<ExclamationCircleOutlined class="text-[60px] text-red-500" />
					</template>
				</a-empty>
			</a-card>
			<a-card hoverable class="shadow" :class="[flex ? ' flex-grow' : ' mt-4! ']">
				<template #title>
					<UserOutlined />
					我的审核
				</template>
				<template v-if="!currentApprovalTask">
					<a-empty>
						<template #description> 无审核任务</template>
					</a-empty>
				</template>
				<template v-else-if="currentApprovalTask.status === 'pending'">
					<a-form :model="state.approveForm" :label-col="{ span: 6 }" @finish="onApprove">
						<a-form-item
							label="审核结果"
							name="approval_status"
							required
							:rules="{ required: true, message: '请选择审核结果', trigger: 'change' }"
						>
							<a-radio-group v-model:value="state.approveForm.approval_status">
								<template v-for="(option, index) in approvalOptions" :key="index">
									<a-radio v-if="option.value !== 'pending'" :value="option.value">{{ option.label }} </a-radio>
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
						<div class="ml-[80px] mb-4">
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
							<a-button type="primary" html-type="submit" :loading="state.approveFetcher.loading"> 提交审核 </a-button>
						</div>
					</a-form>
				</template>
				<template v-else-if="['rejected', 'approved'].includes(currentApprovalTask.status)">
					<a-form :label-col="{ span: 4 }">
						<a-form-item label="审核结果">{{ statusConfig(currentApprovalTask).text }}</a-form-item>
						<a-form-item label="审核意见">
							<a-textarea readonly :value="currentApprovalTask.comment || '无'"></a-textarea>
						</a-form-item>
						<a-form-item label="审核时间">{{ currentApprovalTask.approved_at }}</a-form-item>
					</a-form>
				</template>
				<template v-else-if="currentApprovalTask.status === 'skipped'">
					<a-empty>
						<template #description> 审核任务跳过 - 无需审核</template>
					</a-empty>
				</template>
			</a-card>
		</div>
		<a-descriptions v-else :column="1" bordered class="min-w-[500px] bg-white rounded-lg shadow-lg">
			<a-descriptions-item label="审核状态">
				<component :is="useApprovalStatus(approvable)" />
			</a-descriptions-item>
			<a-descriptions-item label="审核时间">{{ approvable?.approval_at || "-" }}</a-descriptions-item>
			<a-descriptions-item label="审核说明">{{ approvable?.approval_comment || "-" }}</a-descriptions-item>
		</a-descriptions>

		<NewbieModal v-model:visible="state.showHistoriesModal" title="审核记录">
			<a-empty v-if="!approvalTaskHistories?.length" description="暂无审核记录"></a-empty>
			<a-timeline v-else class="mt-10">
				<a-timeline-item v-for="task in approvalTaskHistories" :key="task.id" :color="statusConfig(task).color">
					<!-- 时间 -->
					<a-tooltip title="操作时间">
						<a-tag v-if="['skipped', 'updated'].includes(task.status)">
							<template #icon>
								<ClockCircleOutlined />
							</template>

							{{ task.updated_at }}
						</a-tag>
						<a-tag v-else-if="['approved', 'rejected'].includes(task.status)">
							<template #icon>
								<ClockCircleOutlined />
							</template>
							{{ task.approved_at }}
						</a-tag>
						<a-tag v-if="['reset'].includes(task.status)">
							<template #icon>
								<RedoOutlined />
							</template>

							{{ task.updated_at }}
						</a-tag>
					</a-tooltip>

					<a-tooltip title="审核结果">
						<!-- 状态文字 -->
						<a-tag class="w-24" :color="statusConfig(task).color">
							<template #icon v-if="task.status !== 'updated'">
								<SecurityScanOutlined />
							</template>
							{{ statusConfig(task).text }}
						</a-tag>
					</a-tooltip>

					<a-tooltip title="设定节点" v-if="task.status !== 'updated'">
						<a-tag>
							<template #icon>
								<NodeIndexOutlined />
							</template>
							{{ task.approver?.name }}
						</a-tag>
					</a-tooltip>
					<a-tooltip title="审核人" v-if="task.status !== 'updated'">
						<a-tag>
							<template #icon>
								<UserOutlined />
							</template>
							<span v-if="task.executor">
								<span class="font-bold" v-if="task.executor?.nickname">{{ task.executor.nickname }} </span>
								<span v-if="task.executor?.work_num">【{{ task.executor.work_num }}】</span>
							</span>
						</a-tag>
					</a-tooltip>

					<div v-if="task.comment" class="text-sm my-2 p-2 rounded border-solid border-[1px] border-gray-200 bg-gray-100">
						<span class="font-bold">审核意见</span>：{{ task.comment }}
					</div>
				</a-timeline-item>
			</a-timeline>
		</NewbieModal>
	</div>
</template>
<script setup>
import { inject, reactive, ref, h, computed } from "vue"
import {
	ClockCircleOutlined,
	ExclamationCircleOutlined,
	HistoryOutlined,
	NodeIndexOutlined,
	RedoOutlined,
	SecurityScanOutlined,
	UserOutlined,
} from "@ant-design/icons-vue"
import { cloneDeep } from "lodash-es"
import { useFetch, useModalConfirm, useProcessStatusSuccess } from "jobsys-newbie/hooks"
import { message } from "ant-design-vue"
import { useApprovalStatus, useApprovalStatusConfig } from "../hooks/approval"
import { router } from "@inertiajs/vue3"

const emits = defineEmits(["afterApproved"])

const props = defineProps({
	approvable: { type: Object, default: () => null }, // 审核对象
	canApprove: { type: Boolean, default: false }, //是否可以审核
	tasks: { type: Array, default: () => [] }, // 审核流程的中该审核对象的任务列表
	histories: { type: Array, default: () => [] }, // 审核对象的审核历史记录
	currentTask: { type: Object, default: () => null }, // 当前审核任务
	flex: { type: Boolean, default: false }, //是否左右布局
	extraData: { type: Object, default: () => ({}) }, //审核时的额外参数
	errorMessage: { type: String, default: "" }, // 错误信息, eg: 该业务未正确设置审核流程
	afterReset: { type: Function, default: () => null }, // 重置后回调
})

const route = inject("route")

const approvalTasks = computed(() => {
	if (props.tasks?.length) {
		return props.tasks
	}
	if (props.approvable && props.approvable.approval_tasks?.length) {
		return props.approvable.approval_tasks
	}

	return []
})

const approvalTaskHistories = computed(() => {
	if (props.histories?.length) {
		return props.histories
	}
	if (props.approvable && props.approvable.approval_task_histories?.length) {
		return props.approvable.approval_task_histories
	}
	return []
})

const currentApprovalTask = computed(() => {
	if (props.currentTask) {
		return props.currentTask
	}
	if (props.approvable && props.approvable.current_task) {
		return props.approvable.current_task
	}
	return null
})

const canApproveTask = computed(() => {
	if (props.canApprove) {
		return true
	}
	return !!(props.approvable && props.approvable.can_approve)
})

const approvalErrorMessage = computed(() => {
	if (props.errorMessage) {
		return props.errorMessage
	}
	if (props.approvable && props.approvable.approval_message) {
		return props.approvable.approval_message
	}
	return null
})

const state = reactive({
	showHistoriesModal: false,
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

const statusConfig = useApprovalStatusConfig

const onApprove = async () => {
	try {
		const form = cloneDeep(state.approveForm)
		form.id = currentApprovalTask?.value.id
		const res = await useFetch(state.approveFetcher).post(route("api.manager.approval.approve"), { ...form, ...props.extraData })
		useProcessStatusSuccess(res, () => {
			message.success("审核成功")
			emits("afterApproved")
		})
	} catch (e) {
		message.error(e.message)
	}
}

const onResetTask = () => {
	if (!props.approvable?.approvable_slug) {
		message.error("未定义审核对象类型，无法重置")
		return
	}

	const modal = useModalConfirm(
		`原审核结果将被弃用，您确认要重置审核流程吗？`,
		async () => {
			try {
				const res = await useFetch(state.approveFetcher).post(route("api.manager.approval.task.reset"), {
					approvable_slug: props.approvable?.approvable_slug,
					approvable_id: props.approvable?.id,
				})
				modal.destroy()
				useProcessStatusSuccess(res, () => {
					message.success("重置成功")
					if (props.afterReset) {
						props.afterReset()
					}
					router.reload()
				})
			} catch (e) {
				modal.destroy(e)
			}
		},
		true,
	)
}
</script>
