<template>
	<a-modal v-model:open="state.visible" :title="title" :width="1000" :mask-closable="false" :footer="null" @cancel="onClose">
		<a-alert type="info" show-icon class="my-4!">
			<template #message>
				<div>已被审核的任务节点将不会被重新分配审核员</div>
				<div v-for="(tip, index) in tips" :key="index">{{ tip }}}</div>
			</template>
		</a-alert>

		<a-form :model="state.form" class="bg-gray-50 p-6! rounded">
			<a-form-item label="选择审核节点">
				<a-select
					v-model:value="state.form.node_id"
					:options="state.nodeOptions"
					:placeholder="`当前流程有${state.nodeOptions.length}个可手动指定节点`"
					style="width: 300px"
				></a-select>
			</a-form-item>
			<a-form-item label="指定审核用户" help="仅拥有审核权限的用户可选">
				<a-transfer
					:list-style="{ width: '600px' }"
					v-model:target-keys="state.form.user_ids"
					:data-source="state.userOptions"
					:titles="['可选用户', '已选用户']"
					:render="(item) => item.title"
				></a-transfer>
			</a-form-item>
			<a-form-item label="指定数据范围">
				<a-radio-group v-model:value="state.form.scope">
					<a-radio value="all">所有数据</a-radio>
					<a-radio value="selection">勾选数据</a-radio>
					<a-radio value="page">当前页数据</a-radio>
				</a-radio-group>
			</a-form-item>
			<a-form-item label="任务分配方式">
				<a-radio-group v-model:value="state.form.method">
					<a-radio value="avg">平均分配</a-radio>
					<a-radio value="specified">指定数目</a-radio>
				</a-radio-group>
				<template #help>
					<div>平均分配：将会按指定数据范围的<span class="font-bold"> 数据总数 / 已选用户数量 </span>进行自动分配</div>
					<div>指定数目：将为已选用户按顺序每个人分配指定数目的审核任务</div>
				</template>
			</a-form-item>
			<a-form-item v-if="state.form.method === 'specified'" label="指定分配数目">
				<a-input-number v-model:value="state.form.number" addon-after="条" :min="1" placeholder="请填写"></a-input-number>
			</a-form-item>
		</a-form>

		<div class="flex justify-center items-center mt-4">
			<a-button type="primary" :loading="state.assignSubmitter.loading" @click="onSubmit">保存</a-button>
			<a-button class="ml-2" @click="onClose">关闭</a-button>
		</div>
	</a-modal>
</template>

<script setup>
import { computed, inject, reactive } from "vue"
import { message } from "ant-design-vue"
import { useFetch, useProcessStatusSuccess } from "jobsys-newbie/hooks"

const props = defineProps({
	approvalProcessId: { type: [Number, String], default: "", required: true }, //审核流程ID
	slug: { type: String, default: "", required: true }, //审核对象 SLUG
	permission: { type: String, default: "" }, //当前对象对应的审核权限，
	tableRef: { type: [Object, null], default: () => null, required: true }, // 表格实例
	title: { type: String, default: "审核流程节点指定" }, // 标题
	tips: { type: Array, default: () => [] }, // 提示
})

const route = inject("route")

const emits = defineEmits(["success", "close"])

const state = reactive({
	visible: false,
	process: {},
	userOptions: [],
	nodeOptions: [],
	assignSubmitter: {},
	form: {
		node_id: undefined,
		slug: props.slug,
		scope: "selection",
		method: "avg",
		user_ids: [],
		number: 0,
	},
})

const selectionRows = computed(() => {
	return props.tableRef ? props.tableRef.getSelection() : []
})

const isTableEmpty = computed(() => {
	return props.tableRef ? !props.tableRef.getPagination()?.totalSize : true
})

const onClose = () => {
	state.visible = false
	emits("close")
}

const onOpen = async () => {
	if (isTableEmpty.value) {
		message.error("当前表格无数据")
		return
	}

	const res = await useFetch().get(
		route("api.manager.approval.customize.detail", {
			approval_process_id: props.approvalProcessId,
			permission: props.permission,
		}),
	)

	useProcessStatusSuccess(res, () => {
		const { result } = res
		state.process = result.process
		state.nodeOptions = result.nodeOptions
		state.userOptions = result.userOptions
		state.visible = true
	})
}

const onSubmit = async () => {
	if (state.form.scope === "selection" && !selectionRows.value?.length) {
		message.error("请选择数据后再进行分配")
		return
	}

	if (isTableEmpty.value) {
		message.error("当前表格无数据，请选择数据后再进行分配")
		return
	}

	if (!state.form.node_id) {
		message.error("请选择审核节点")
		return
	}

	if (!state.form.user_ids?.length) {
		message.error("请指定审核用户")
		return
	}

	if (state.form.method === "specified" && !state.form.number) {
		message.error("请指定分配数目")
		return
	}

	const data = {
		...state.form,
	}

	if (data.scope === "selection") {
		data.scope = selectionRows.value.map((item) => item.id)
	} else if (data.scope === "page") {
		data.scope = props.tableRef.getData().map((item) => item.id)
	}

	const res = await useFetch(state.assignSubmitter).post(route("api.manager.approval.customize.assign"), data)

	useProcessStatusSuccess(res, () => {
		message.success("设置成功")
		state.visible = false
		emits("success")
	})
}

defineExpose({ open: onOpen })
</script>

<style scoped></style>
