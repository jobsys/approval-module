<template>
	<div v-if="!state.activeKey">
		<div class="mb-4 shadow rounded-lg" v-for="approvable in state.approvables" :key="approvable.slug">
			<a-card :title="approvable.name">
				<a-row :gutter="16" wrap>
					<a-col :xxl="4" :xl="6" :lg="8" :md="12" :xs="24" hoverable v-for="item in approvable.children" :key="item.name">
						<a-card
							class="mb-4 shadow"
							:class="[item.count ? 'border-2 border-orange-400' : 'bg-gray-100']"
							hoverable
							@click="onChangeType(item)"
						>
							<a-statistic :value="item.count">
								<template #title>
									<span class="text-nowrap" :class="[item.count ? 'text-black' : '']">{{ item.name }}</span>
								</template>
							</a-statistic>
						</a-card>
					</a-col>
				</a-row>
			</a-card>
		</div>
	</div>
	<div v-else>
		<NewbieTable
			ref="tableRef"
			row-selection
			:filterable="false"
			:url="route('api.manager.approval.task.items', { approvable: state.currentItem.approvable })"
			:columns="tableColumns()"
			row-key="id"
		></NewbieTable>
	</div>
</template>

<script setup>
import { h, inject, nextTick, reactive, ref } from "vue"
import { useFetch, useProcessStatusSuccess } from "jobsys-newbie/hooks"
import { useTableActions } from "jobsys-newbie"
import { useApprovalOptions, useApprovalStatus } from "../hooks/approval"
import { AuditOutlined } from "@ant-design/icons-vue"
import { router } from "@inertiajs/vue3"

const route = inject("route")
const tableRef = ref()

const state = reactive({
	activeKey: "",
	approvables: [],
	currentItem: {},
})

const fetchGroup = async () => {
	const res = await useFetch().get(route("api.manager.approval.task.group"))
	useProcessStatusSuccess(res, () => {
		state.approvables = res.result
	})
}

fetchGroup()

const onChangeType = (item) => {
	state.currentItem = item
	nextTick(() => {
		state.activeKey = item.approvable
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
