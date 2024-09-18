<template>
	<a-button type="primary" @click="() => (state.showProcessModal = true)" :icon="h(SubnodeOutlined)">设置审核流程 </a-button>
	<a-divider></a-divider>
	<a-alert message="未绑定审核流程的业务将不会被审核" type="warning" show-icon class="mb-4" />
	<a-table :columns="state.bindingColumns" :pagination="false" :scroll="{ y: 400 }" :data-source="state.bindingData">
		<template #bodyCell="{ column, record }">
			<div v-if="column.dataIndex === 'auto_approve'">
				<a-switch v-model:checked="record.is_auto_approve" checked-children="开" un-checked-children="关"></a-switch>
				<div v-if="record.is_auto_approve" class="bg-gray-100 mt-4 p-2 rounded">
					<div class="flex items-center">
						<span class="w-[100px]">自动审核状态：</span>
						<a-select
							class="w-[300px]"
							placeholder="请选择自动审核状态"
							v-model:value="record.auto_approve_status"
							:options="state.approvalOptions"
						></a-select>
					</div>
					<div class="flex items-center mt-2">
						<span class="w-[100px]">自动审核说明：</span>
						<a-textarea
							class="w-[300px]"
							v-model:value="record.auto_approve_comment"
							allow-clear
							placeholder="请填写自动审核说明"
							:auto-size="{ minRows: 1, maxRows: 3 }"
						></a-textarea>
					</div>
				</div>
			</div>

			<a-select
				v-if="column.dataIndex === 'process' && !record.children"
				v-model:value="record.process"
				placeholder="请选择审核流程"
				class="w-[200px]"
				:options="processOptions"
				allow-clear
				@change="(value) => (record.process = value)"
			></a-select>
		</template>
	</a-table>

	<div class="text-center">
		<NewbieButton :fetcher="state.bindingFetcher" type="primary" class="my-3" @click="onSubmitBinding">保存 </NewbieButton>
	</div>

	<NewbieModal v-model:visible="state.showProcessModal" title="审核流程列表" type="drawer" :width="1200" @close="onCloseProcessModal">
		<NewbieTable ref="tableRef" :url="route('api.manager.approval.process.items')" :columns="columns()">
			<template #functional>
				<NewbieButton type="primary" :icon="h(PlusOutlined)" @click="onEdit(false)">新增审核流程</NewbieButton>
			</template>
		</NewbieTable>
	</NewbieModal>

	<NewbieModal v-model:visible="state.showEditorModal" title="审核流程编辑" :width="1000">
		<div class="px-60">
			<a-steps :current="state.currentStep" class="!my-8">
				<a-step title="流程信息" />
				<a-step title="流程节点" />
			</a-steps>
		</div>

		<NewbieForm
			v-show="state.currentStep === 0"
			ref="editRef"
			full-width
			:data="state.processForm"
			:form="getForm()"
			:close="() => (state.showEditorModal = false)"
			:before-submit="onBeforeSubmit"
			submit-button-text="下一步"
		/>

		<a-card v-if="state.currentStep === 1">
			<a-steps :current="state.currentNodeStep">
				<a-step v-for="(node, idx) in state.processForm.nodes" :key="idx">
					<template #subTitle>
						<a-avatar :size="64" style="background-color: #87d068">
							<template #icon>
								<UserOutlined></UserOutlined>
							</template>
						</a-avatar>
						<br />
						<span>{{ node.name }}</span>
						<a-button shape="circle" size="small" danger style="margin: 10px 0 10px 5px" @click="onDeleteNode(idx)">
							<template #icon>
								<DeleteOutlined></DeleteOutlined>
							</template>
						</a-button>
					</template>
				</a-step>

				<a-step>
					<template #subTitle>
						<a-avatar :size="64" @click="onOpenNodeEditor()">
							<PlusOutlined style="font-size: 28px">></PlusOutlined>
						</a-avatar>
						<br />
						<span>添加审核节点</span>
					</template>
				</a-step>
			</a-steps>

			<a-divider></a-divider>

			<div class="flex items-center justify-center">
				<NewbieButton type="primary" :fetcher="state.processFetcher" @click="onSubmit">确定</NewbieButton>
				<a-button class="ml-4" @click="() => (state.showEditorModal = false)">关闭</a-button>
			</div>
		</a-card>
	</NewbieModal>

	<a-modal title="添加审核节点" v-model:open="state.showNodeEditor" :width="800" :footer="null" destroy-on-close>
		<a-form :model="state.currentNode" :label-col="{ span: 4 }" @finish="onAddNode">
			<a-form-item label="审核节点名称" name="name" required :rules="{ required: true, message: '请填写审核节点名称', trigger: 'blur' }">
				<a-input v-model:value="state.currentNode.name" placeholder="请填写审核节点名称"></a-input>
			</a-form-item>
			<a-form-item label="审核人类型" name="approver_type" required :rules="{ required: true, message: '请选择审核人类型', trigger: 'change' }">
				<template #help>
					<div>“本部门” 表示审核内容所属部门中有审核权限的成员均可审核当前内容</div>
					<div>“上级部门” 表示该部门的直属上级部门中有审核权限的成员均可以审核当前内容</div>
				</template>
				<a-radio-group
					v-model:value="state.currentNode.approver_type"
					@change="
						() => {
							state.currentNode.approver_id = undefined
						}
					"
				>
					<a-radio v-for="(option, index) in approverOptions" :value="option.value" :key="index">
						{{ option.label }}
					</a-radio>
				</a-radio-group>
			</a-form-item>

			<a-form-item
				label="审核部门"
				name="approver_id"
				required
				v-if="state.currentNode.approver_type === 'designated-department'"
				:rules="{ required: true, message: '请选择审核部门', trigger: 'change' }"
				help="该部门所有职工都可以审核"
			>
				<a-select
					v-model:value="state.currentNode.approver_id"
					show-search
					:options="departmentOptions"
					:filter-option="filterOption"
					placeholder="请选择审核部门"
				/>
			</a-form-item>

			<a-form-item
				label="审核角色"
				name="approver_id"
				required
				v-else-if="state.currentNode.approver_type === 'designated-role'"
				:rules="{ required: true, message: '请选择审核角色', trigger: 'change' }"
				help="拥有该角色身份的职工可以审核"
			>
				<a-select
					v-model:value="state.currentNode.approver_id"
					show-search
					:filter-option="filterOption"
					:options="roleOptions"
					placeholder="请选择审核角色"
				/>
			</a-form-item>

			<a-form-item
				label="审核人"
				name="approver_id"
				required
				v-else-if="state.currentNode.approver_type === 'designated-user'"
				:rules="{ required: true, message: '请选择审核人', trigger: 'change' }"
				help="指定的用户可以审核"
			>
				<a-select
					v-model:value="state.currentNode.approver_id"
					placeholder="请输入职工姓名进行搜索"
					style="width: 100%"
					show-search
					:filter-option="false"
					:not-found-content="state.isUserLoading ? undefined : null"
					:options="state.userOptions"
					@search="fetchUser"
				>
					<template v-if="state.isUserLoading" #notFoundContent>
						<a-spin size="small" />
					</template>
				</a-select>
			</a-form-item>

			<!-- TODO: 下面两项暂时没有时间处理 -->
			<!--
						<a-form-item label="节点权重">
							<a-input-number :min="0" v-model:value="state.currentNode.weight"></a-input-number>
						</a-form-item>

		   -->
			<a-divider></a-divider>

			<div class="flex items-center justify-center">
				<a-button type="primary" html-type="submit">确定</a-button>
				<a-button style="margin-left: 10px" @click="() => (state.showNodeEditor = false)">关闭</a-button>
			</div>
		</a-form>
	</a-modal>
</template>

<script setup>
import { useTableActions } from "jobsys-newbie"
import { useFetch, useModalConfirm, useProcessStatusSuccess } from "jobsys-newbie/hooks"
import { message } from "ant-design-vue"
import { h, inject, reactive, ref, watch } from "vue"
import { cloneDeep, debounce, find } from "lodash-es"
import { DeleteOutlined, EditOutlined, PlusOutlined, SubnodeOutlined, UserOutlined } from "@ant-design/icons-vue"
import { router } from "@inertiajs/vue3"

const tableRef = ref()
const editRef = ref()

const route = inject("route")

const props = defineProps({
	roleOptions: { type: Array, default: () => [] },
	departmentOptions: { type: Array, default: () => [] },
	approverOptions: { type: Array, default: () => [] },
	subsequentActionOptions: { type: Array, default: () => [] },
	bindingItems: { type: Array, default: () => [] },
	processOptions: { type: Array, default: () => [] },
})

const defaultNode = {
	name: "",
	approver_id: undefined,
	approver_type: "",
	weight: 0,
}

const state = reactive({
	showEditorModal: false,
	showProcessModal: false,
	showNodeEditor: false,
	currentStep: 0,
	currentNodeStep: -1,
	currentNode: {},
	processForm: {
		nodes: [],
	},
	userOptions: [],
	isUserLoading: false,
	bindingFetcher: {},
	processFetcher: {},
	approvalOptions: [
		{ label: "审核通过", value: "approved" },
		{ label: "审核驳回", value: "rejected" },
	],
	bindingData: cloneDeep(props.bindingItems),
	bindingColumns: [
		{ title: "业务类型", dataIndex: "service_name" },
		/*{ title: "自动审核设置", dataIndex: "auto_approve" },*/
		{ title: "审核流程绑定", dataIndex: "process" },
	],
})

watch(
	() => state.currentNode.approver_type,
	(val) => {
		if (val.includes("User")) {
			state.currentNode.approver_id = undefined
			state.userOptions = []
		}
	},
)

const onCloseProcessModal = () => {
	router.reload({ only: ["processOptions"] })
}

const getForm = () => {
	return [
		{
			key: "name",
			title: "审核流程名称",
			required: true,
		},
		{
			key: "subsequent_action",
			title: "后续节点权限",
			type: "radio",
			options: props.subsequentActionOptions,
			required: true,
			defaultValue: "invisible",
			tips: "不可见：在当前节点未审核通过之前，后续节点的审核者无法查看到该审核内容\n可见不可审核：在当前节点未审核通过之前，后续节点的审核者无法查看到该审核内容但无法审核\n可见可审核：该审核内容对后续节点可见，并可被审核",
		},
		{
			key: "is_active",
			title: "是否启用",
			type: "switch",
			defaultValue: true,
		},
		{
			key: "remark",
			title: "备注",
			type: "textarea",
		},
	]
}

const onEdit = (item) => {
	state.currentStep = 0
	if (!item) {
		state.showEditorModal = true
	} else {
		useFetch()
			.get(route("api.manager.approval.process.item", { id: item.id }))
			.then((res) => {
				state.processForm = res.result
				state.showEditorModal = true
			})
	}
}

const closeEditor = (isRefresh) => {
	if (isRefresh) {
		tableRef.value.doFetch()
	}
	state.processForm = {
		nodes: [],
	}
	editRef.value.reset()
	state.showEditorModal = false
}

const onSubmitBinding = async () => {
	try {
		const approvables = cloneDeep(state.bindingData)

		const items = []

		for (let n = 0; n < approvables.length; n += 1) {
			const { children } = approvables[n]
			for (let i = 0; i < children.length; i += 1) {
				const child = children[i]

				if (child.is_auto_approve) {
					if (!child.process) {
						message.error(`${child.service_name}未绑定审核流程`)
						return
					}

					if (!child.auto_approve_status) {
						message.error(`${child.service_name}未设置自动审核状态`)
						return
					}

					if (child.auto_approve_status === "rejected" && !child.auto_approve_comment) {
						message.error(`请为${child.service_name}设置审核驳回说明`)
						return
					}
				}

				items.push(child)
			}
		}

		const res = await useFetch(state.bindingFetcher).post(route("api.manager.approval.binding.edit"), { items })
		useProcessStatusSuccess(res, () => {
			message.success("保存成功")
		})
	} catch (e) {
		message.error(e.message)
	}
}

const onSubmit = async () => {
	try {
		const form = cloneDeep(state.processForm)
		const res = await useFetch(state.processFetcher).post(route("api.manager.approval.process.edit"), form)
		useProcessStatusSuccess(res, () => {
			message.success("保存成功")
			closeEditor(true)
		})
	} catch (e) {
		message.error(e.message)
	}
}

const onBeforeSubmit = ({ originalForm }) => {
	state.processForm = { ...state.processForm, ...originalForm }
	if (state.currentStep === 2) {
		onSubmit()
	} else {
		state.currentStep += 1
	}
	return new Promise((resolve) => resolve(false))
}

const onDelete = (item) => {
	const modal = useModalConfirm(
		`您确认要删除 ${item.name} 吗？`,
		async () => {
			try {
				const res = await useFetch().post(route("api.manager.approval.process.delete"), { id: item.id })
				modal.destroy()
				useProcessStatusSuccess(res, () => {
					message.success("删除成功")
					tableRef.value.doFetch()
				})
			} catch (e) {
				modal.destroy(e)
			}
		},
		true,
	)
}

const onAddNode = () => {
	state.processForm.nodes.push(state.currentNode)
	state.currentNodeStep += 1
	state.showNodeEditor = false
}
const onDeleteNode = (idx) => {
	const modal = useModalConfirm(
		`您确认要删除该审核节点吗？`,
		() => {
			state.processForm.nodes.splice(idx, 1)
			modal.destroy()
		},
		true,
	)
}

const onOpenNodeEditor = () => {
	state.currentNode = cloneDeep(defaultNode)
	state.showNodeEditor = true
}

const filterOption = (input, option) => {
	return option.label.toLowerCase().indexOf(input) >= 0
}

const fetchUser = debounce((value) => {
	state.isUserLoading = true
	useFetch()
		.get(route("api.manager.user.items"), { name: value })
		.then((res) => {
			state.userOptions = res.result.data.map((item) => ({
				label: `${item.name || item.phone}[${item.work_num || "无工号"}]`,
				value: item.id,
			}))
			state.isUserLoading = false
		})
}, 300)

const columns = () => {
	return [
		{
			title: "审核流程名称",
			width: 200,
			dataIndex: "name",
			filterable: "input",
		},
		{
			title: "后续节点权限",
			width: 200,
			key: "subsequent_action",
			customRender({ record }) {
				return h("span", {}, find(props.subsequentActionOptions, { value: record.subsequent_action })?.label)
			},
		},
		{
			title: "是否启用",
			key: "is_active",
			width: 80,
			customRender({ record }) {
				return useTableActions({
					type: "a-tag",
					name: record.is_active ? "启用" : "关闭",
					props: { color: record.is_active ? "green" : "red" },
				})
			},
		},
		{
			title: "创建时间",
			width: 200,
			dataIndex: "created_at",
		},

		{
			title: "备注",
			width: 120,
			dataIndex: "remark",
			ellipsis: true,
		},
		{
			title: "操作",
			width: 160,
			key: "operation",
			fixed: "right",
			customRender({ record }) {
				return useTableActions([
					{
						name: "编辑",
						props: {
							icon: h(EditOutlined),
							size: "small",
						},
						action() {
							onEdit(record)
						},
					},
					{
						name: "删除",
						props: {
							icon: h(DeleteOutlined),
							size: "small",
						},
						action() {
							onDelete(record)
						},
					},
				])
			},
		},
	]
}
</script>
