<template>
	<div class="approval-box">
		<a-row :gutter="20">
			<a-col :span="12">
				<a-card title="审批流程">
					<template #extra
						><a href="javascript:" @click.stop="() => (state.showHistoriesModal = true)">
							<HistoryOutlined></HistoryOutlined>
							审批记录</a
						>
					</template>
					<a-timeline>
						<a-timeline-item v-for="task in tasks" :key="task.id" :color="statusMap[task.status].color">
							<!-- 时间 -->
							<a-tooltip title="操作时间">
								<a-tag v-if="['skipped', 'updated'].includes(task.status)">
									<template #icon>
										<ClockCircleOutlined></ClockCircleOutlined>
									</template>
									{{ task.updated_at_datetime }}
								</a-tag>
								<a-tag v-else-if="['approved', 'rejected'].includes(task.status)">
									<template #icon>
										<ClockCircleOutlined></ClockCircleOutlined>
									</template>
									{{ task.approved_at_datetime }}
								</a-tag>
							</a-tooltip>

							<a-tooltip title="审批结果">
								<!-- 状态文字 -->
								<a-tag class="w-24" :color="statusMap[task.status].color">
									<template #icon>
										<SecurityScanOutlined></SecurityScanOutlined>
									</template>
									{{ statusMap[task.status].text }}
								</a-tag>
							</a-tooltip>

							<a-tooltip title="审批者">
								<a-tag>
									<template #icon>
										<UserOutlined></UserOutlined>
									</template>
									<!-- 审批人 -->
									{{ task.approver.type === "role" ? task.approver.display_name : task.approver.name }}
									<span v-if="task.executor" class="font-bold">: {{ task.executor.name }}</span>
								</a-tag>
							</a-tooltip>

							<div v-if="task.comment" class="text-sm my-2 p-2 rounded border-solid border-[1px] border-gray-200 bg-gray-100">
								<span class="font-bold">审批意见</span>：{{ task.comment }}
							</div>
						</a-timeline-item>
					</a-timeline>
				</a-card>
			</a-col>
			<a-col :span="12">
				<a-card title="我的审批">
					<template v-if="!currentTask">
						<a-empty>
							<template #description> 无审批任务</template>
						</a-empty>
					</template>
					<template v-else-if="currentTask.status === 'pending'">
						<a-form :model="state.approveForm" :label-col="{ span: 4 }" @finish="onApprove">
							<a-form-item
								label="审批结果"
								name="approval_status"
								required
								:rules="{ required: true, message: '请选择审批结果', trigger: 'change' }"
							>
								<a-radio-group v-model:value="state.approveForm.approval_status">
									<template v-for="(option, index) in approvalOptions" :key="index">
										<a-radio v-if="option.value !== 'pending'" :value="option.value">{{ option.label }} </a-radio>
									</template>
								</a-radio-group>
							</a-form-item>

							<a-form-item
								label="审批意见"
								name="approval_comment"
								required
								:rules="{ required: state.approveForm.approval_status === 'rejected', message: '请填写审批意见', trigger: 'blur' }"
							>
								<a-textarea v-model:value="state.approveForm.approval_comment" placeholder="请填写审批意见"></a-textarea>
							</a-form-item>
							<a-divider></a-divider>
							<a-form-item :wrapper-col="{ span: 14, offset: 4 }">
								<a-button type="primary" html-type="submit" :loading="state.approveFetcher.loading"> 提交审批 </a-button>
								<a-button style="margin-left: 10px" @click="() => (state.showApproveModal = false)"> 关闭 </a-button>
							</a-form-item>
						</a-form>
					</template>
					<template v-else-if="['rejected', 'approved'].includes(currentTask.status)">
						<a-form :label-col="{ span: 4 }">
							<a-form-item label="审批结果">{{ statusMap[currentTask.status].text }}</a-form-item>
							<a-form-item label="审批意见">
								<a-textarea readonly :value="currentTask.comment || '无'"></a-textarea>
							</a-form-item>
							<a-form-item label="审批时间">{{ currentTask.approved_at_datetime }}</a-form-item>
						</a-form>
					</template>
				</a-card>
			</a-col>
		</a-row>
		<NewbieModal v-model:visible="state.showHistoriesModal" title="审批记录">
			<a-timeline class="mt-10">
				<a-timeline-item v-for="task in histories" :key="task.id" :color="statusMap[task.status].color">
					<!-- 时间 -->
					<a-tooltip title="操作时间">
						<a-tag v-if="['skipped', 'updated'].includes(task.status)">
							<template #icon>
								<ClockCircleOutlined></ClockCircleOutlined>
							</template>

							{{ task.updated_at_datetime }}
						</a-tag>
						<a-tag v-else-if="['approved', 'rejected'].includes(task.status)">
							<template #icon>
								<ClockCircleOutlined></ClockCircleOutlined>
							</template>
							{{ task.approved_at_datetime }}
						</a-tag>
					</a-tooltip>

					<a-tooltip title="审批结果">
						<!-- 状态文字 -->
						<a-tag class="w-24" :color="statusMap[task.status].color">
							<template #icon v-if="task.status !== 'updated'">
								<SecurityScanOutlined></SecurityScanOutlined>
							</template>
							{{ statusMap[task.status].text }}
						</a-tag>
					</a-tooltip>

					<a-tooltip title="审批者" v-if="task.status !== 'updated'">
						<a-tag>
							<template #icon>
								<UserOutlined></UserOutlined>
							</template>
							<!-- 审批人 -->
							{{ task.approver.type === "role" ? task.approver.display_name : task.approver.name }}
							<span v-if="task.executor" class="font-bold">: {{ task.executor.name }}</span>
						</a-tag>
					</a-tooltip>

					<div v-if="task.comment" class="text-sm my-2 p-2 rounded border-solid border-[1px] border-gray-200 bg-gray-100">
						<span class="font-bold">审批意见</span>：{{ task.comment }}
					</div>
				</a-timeline-item>
			</a-timeline>
		</NewbieModal>
	</div>
</template>
<script setup>
import { inject, reactive, ref } from "vue"
import { UserOutlined, ClockCircleOutlined, SecurityScanOutlined, HistoryOutlined } from "@ant-design/icons-vue"
import { NewbieModal } from "@web/components"
import { cloneDeep } from "lodash-es"
import { useFetch } from "@/js/hooks/common/network"
import { useProcessStatusSuccess } from "@/js/hooks/web/form"
import { message } from "ant-design-vue"
import { router } from "@inertiajs/vue3"

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
	},
	approveFetcher: {},
})

const approvalOptions = ref([
	{ label: "审批通过", value: "approved" },
	{ label: "审批驳回", value: "rejected" },
])

const statusMap = ref({
	approved: { color: "green", text: "审批通过" },
	rejected: { color: "red", text: "审批驳回" },
	pending: { color: "blue", text: "待审批" },
	skipped: { color: "gray", text: "跳过" },
	updated: { color: "#333", text: "审批对象更新" },
})

const onApprove = async () => {
	try {
		const form = cloneDeep(state.approveForm)
		form.id = props.currentTask.id
		const res = await useFetch(state.approveFetcher).post(route("api.manager.approval.approve"), form)
		useProcessStatusSuccess(res, () => {
			message.success("审批成功")
			router.reload()
		})
	} catch (e) {
		message.error(e.message)
	}
}
</script>
