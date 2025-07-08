<template>
	<NewbieModal v-model:visible="state.showApproveModal" title="批量审核">
		<a-alert show-icon type="warning" class="mb-4!">
			<template #message>
				<ul class="mb-0!">
					<li>仅对有【审核权限】且审核状态为：【待审核】和【审核中】的记录进行操作</li>
					<li v-for="(tip, index) in props.tips" :key="index">{{ tip }}</li>
				</ul>
			</template>
		</a-alert>
		<a-alert show-icon class="mb-4!">
			<template #message
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
				<a-button class="ml-2" @click="() => (state.showApproveModal = false)">关闭</a-button>
			</div>
		</a-form>
	</NewbieModal>
</template>
<script setup>
import { reactive, inject, ref } from "vue"
import { useFetch, useProcessStatusSuccess } from "jobsys-newbie/hooks"
import { message, notification } from "ant-design-vue"

const props = defineProps({
	slug: { type: String, required: true },
	tips: { type: Array, default: () => [] },
})
const emits = defineEmits(["success"])

const route = inject("route")

const state = reactive({
	selectedIds: [],
	showApproveModal: false,
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

const open = (selectedIds) => {
	if (!selectedIds?.length) {
		message.warning("请先勾选数据")
		return
	}
	state.selectedIds = selectedIds

	state.approveForm = {
		approval_status: "",
		approval_comment: "",
		approval_remark: "",
	}
	state.showApproveModal = true
}

const onSubmit = async () => {
	const res = await useFetch(state.approveFetcher).post(route("api.manager.approval.approve.batch"), {
		ids: state.selectedIds,
		slug: props.slug,
		...state.approveForm,
	})
	useProcessStatusSuccess(res, () => {
		const { total_count: totalCount, success_count: successCount } = res.result
		notification.success({
			message: "批量审核成功",
			description: `共审核【${totalCount}】条记录，成功【${successCount}】条`,
			placement: "top",
		})
		emits("success")
		state.showApproveModal = false
	})
}

defineExpose({ open })
</script>
