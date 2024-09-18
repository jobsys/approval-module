<template>
	<div class="approval-box min-w-[500px]">
		<a-card hoverable class="shadow-lg">
			<template #title>
				<NodeIndexOutlined />
				审核流程
			</template>
			<template #extra
				><a href="javascript:" @click.stop="() => (state.showHistoriesModal = true)">
					<HistoryOutlined></HistoryOutlined>
					审核记录</a
				>
			</template>
			<a-timeline v-if="tasks.length">
				<a-timeline-item v-for="task in tasks" :key="task.id" :color="statusMap[task.status].color">
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

					<a-tooltip title="审核结果">
						<!-- 状态文字 -->
						<a-tag class="w-24" :color="statusMap[task.status].color">
							<template #icon>
								<SecurityScanOutlined></SecurityScanOutlined>
							</template>
							{{ statusMap[task.status].text }}
						</a-tag>
					</a-tooltip>

					<a-tooltip title="审核人">
						<a-tag>
							<template #icon>
								<UserOutlined></UserOutlined>
							</template>
							<!-- 审核人 -->
							{{ task.approver.name }}
							<span v-if="task.executor" class="font-bold">: {{ task.executor.name }}</span>
						</a-tag>
					</a-tooltip>

					<div v-if="task.comment" class="text-sm my-2 p-2 rounded border-solid border-[1px] border-gray-200 bg-gray-100">
						<span class="font-bold">审核意见</span>：{{ task.comment }}
					</div>
					<div v-if="task.remark" class="text-sm my-2 p-2 rounded border-solid border-[1px] border-gray-200 bg-gray-100">
						<span class="font-bold">审核备注</span>：{{ task.remark }}
					</div>
				</a-timeline-item>
			</a-timeline>
			<a-empty v-else description="该业务未正确设置审核流程" :image-style="{ display: 'flex', alignItems: 'center', justifyContent: 'center' }">
				<template #image>
					<ExclamationCircleOutlined class="text-[60px] text-red-500" />
				</template>
			</a-empty>
		</a-card>
		<a-card hoverable class="mt-4 shadow-lg">
			<template #title>
				<UserOutlined />
				我的审核
			</template>
			<template v-if="!currentTask">
				<a-empty>
					<template #description> 无审核任务</template>
				</a-empty>
			</template>
			<template v-else-if="currentTask.status === 'pending'">
				<a-form :model="state.approveForm" :label-col="{ span: 4 }" @finish="onApprove">
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
								{{ tag }}</a-button
							>
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
			<template v-else-if="['rejected', 'approved'].includes(currentTask.status)">
				<a-form :label-col="{ span: 4 }">
					<a-form-item label="审核结果">{{ statusMap[currentTask.status].text }}</a-form-item>
					<a-form-item label="审核意见">
						<a-textarea readonly :value="currentTask.comment || '无'"></a-textarea>
					</a-form-item>
					<a-form-item label="审核时间">{{ currentTask.approved_at }}</a-form-item>
				</a-form>
			</template>
			<template v-else-if="currentTask.status === 'skipped'">
				<a-empty>
					<template #description> 审核任务跳过 - 无需审核</template>
				</a-empty>
			</template>
		</a-card>
	</div>
	<NewbieModal v-model:visible="state.showHistoriesModal" title="审核记录">
		<a-empty v-if="!histories?.length" description="暂无审核记录"></a-empty>
		<a-timeline v-else class="mt-10">
			<a-timeline-item v-for="task in histories" :key="task.id" :color="statusMap[task.status].color">
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
				</a-tooltip>

				<a-tooltip title="审核结果">
					<!-- 状态文字 -->
					<a-tag class="w-24" :color="statusMap[task.status].color">
						<template #icon v-if="task.status !== 'updated'">
							<SecurityScanOutlined />
						</template>
						{{ statusMap[task.status].text }}
					</a-tag>
				</a-tooltip>

				<a-tooltip title="审核者" v-if="task.status !== 'updated'">
					<a-tag>
						<template #icon>
							<UserOutlined />
						</template>
						<!-- 审核人 -->
						{{ task.approver.name }}
						<span v-if="task.executor" class="font-bold">: {{ task.executor.name }}</span>
					</a-tag>
				</a-tooltip>

				<div v-if="task.comment" class="text-sm my-2 p-2 rounded border-solid border-[1px] border-gray-200 bg-gray-100">
					<span class="font-bold">审核意见</span>：{{ task.comment }}
				</div>
			</a-timeline-item>
		</a-timeline>
	</NewbieModal>
</template>
<script setup>
import { inject, reactive, ref } from "vue"
import {
	ClockCircleOutlined,
	HistoryOutlined,
	SecurityScanOutlined,
	UserOutlined,
	NodeIndexOutlined,
	ExclamationCircleOutlined,
} from "@ant-design/icons-vue"
import { cloneDeep } from "lodash-es"
import { useFetch, useProcessStatusSuccess } from "jobsys-newbie/hooks"
import { message } from "ant-design-vue"

const emits = defineEmits(["afterApproved"])

const props = defineProps({
	tasks: { type: Array, default: () => [] }, // 审核流程的中该审核对象的任务列表
	histories: { type: Array, default: () => [] }, // 审核对象的审核历史记录
	currentTask: { type: Object, default: () => null }, // 当前审核任务
})

const route = inject("route")

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

const statusMap = ref({
	approved: { color: "green", text: "审核通过" },
	rejected: { color: "red", text: "审核驳回" },
	pending: { color: "blue", text: "待审核" },
	skipped: { color: "gray", text: "跳过" },
	updated: { color: "#333", text: "审核对象更新" },
})

const onApprove = async () => {
	try {
		const form = cloneDeep(state.approveForm)
		form.id = props.currentTask.id
		const res = await useFetch(state.approveFetcher).post(route("api.manager.approval.approve"), form)
		useProcessStatusSuccess(res, () => {
			message.success("审核成功")
			emits("afterApproved")
		})
	} catch (e) {
		message.error(e.message)
	}
}
</script>
