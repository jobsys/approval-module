<template>
    <NewbieTable ref="list" :url="route('api.manager.approval.process.items')" :columns="columns()" row-key="id">
        <template #functional>
            <NewbieButton type="primary" :icon="h(PlusOutlined)" @click="onEdit(false)">新增审批流程</NewbieButton>
        </template>
    </NewbieTable>
    <NewbieModal v-model:visible="state.showEditorModal" title="审批流程编辑" :width="1000">
        <div class="px-60">
            <a-steps :current="state.currentStep" class="!my-8">
                <a-step title="流程信息" />
                <a-step title="流程节点" />
            </a-steps>
        </div>

        <NewbieEdit
            v-show="state.currentStep === 0"
            ref="edit"
            full-width
            :data="state.processForm"
            :form="getForm()"
            :process-submit-data="onBeforeSubmit"
            submit-button-text="下一步"
        />

        <a-card v-if="state.currentStep === 1">
            <a-steps progress-dot :current="state.currentNodeStep">
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
                        <span>添加审批节点</span>
                    </template>
                </a-step>
            </a-steps>

            <a-divider></a-divider>

            <a-row>
                <a-col :offset="6">
                    <NewbieButton type="primary" :fetcher="state.submitFetcher" @click="onSubmit">确定</NewbieButton>
                    <a-button style="margin-left: 10px" @click="() => (state.showEditorModal = false)">关闭</a-button>
                </a-col>
            </a-row>
        </a-card>
    </NewbieModal>

    <a-modal title="添加审批节点" v-model:visible="state.showNodeEditor" :width="800" :footer="null" destroy-on-close>
        <a-form :model="state.currentNode" :label-col="{ span: 4 }" @finish="onAddNode">
            <a-form-item label="审批节点名称" name="name" required :rules="{ required: true, message: '请填写审批节点名称', trigger: 'blur' }">
                <a-input v-model:value="state.currentNode.name" placeholder="请填写审批节点名称"></a-input>
            </a-form-item>
            <a-form-item label="审批人类型" name="approver_type" required :rules="{ required: true, message: '请选择审批人类型', trigger: 'change' }">
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
                label="审批部门"
                name="approver_id"
                required
                v-if="state.currentNode.approver_type.includes('Department')"
                :rules="{ required: true, message: '请选择审批部门', trigger: 'change' }"
                help="该部门所有职工都可以审批"
            >
                <a-select
                    v-model:value="state.currentNode.approver_id"
                    show-search
                    :options="departmentOptions"
                    :filter-option="filterOption"
                    placeholder="请选择审批部门"
                />
            </a-form-item>

            <a-form-item
                label="审批角色"
                name="approver_id"
                required
                v-else-if="state.currentNode.approver_type.includes('Role')"
                :rules="{ required: true, message: '请选择审批角色', trigger: 'change' }"
                help="拥有该角色身份的职工可以审批"
            >
                <a-select
                    v-model:value="state.currentNode.approver_id"
                    show-search
                    :filter-option="filterOption"
                    :options="roleOptions"
                    placeholder="请选择审批角色"
                />
            </a-form-item>

            <a-form-item
                label="审批人"
                name="approver_id"
                required
                v-else-if="state.currentNode.approver_type.includes('User')"
                :rules="{ required: true, message: '请选择审批人', trigger: 'change' }"
                help="指定的用户可以审批"
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

            <a-form-item :wrapper-col="{ span: 14, offset: 4 }">
                <a-button type="primary" html-type="submit">确定</a-button>
                <a-button style="margin-left: 10px" @click="() => (state.showNodeEditor = false)">关闭</a-button>
            </a-form-item>
        </a-form>
    </a-modal>
</template>

<script setup>
import { useTableActions } from "jobsys-newbie"
import { useFetch, useModalConfirm, useProcessStatusSuccess } from "jobsys-newbie/hooks"
import { message } from "ant-design-vue"
import { h, inject, reactive, ref, watch } from "vue"
import { cloneDeep, debounce, find } from "lodash-es"
import { PlusOutlined, DeleteOutlined, UserOutlined, EditOutlined } from "@ant-design/icons-vue"

const list = ref(null)
const edit = ref(null)

const route = inject("route")

const props = defineProps({
    approvalTypes: {
        type: Array,
        default: () => [],
    },
    roleOptions: {
        type: Array,
        default: () => [],
    },
    departmentOptions: {
        type: Array,
        default: () => [],
    },
    approverOptions: {
        type: Array,
        default: () => [],
    },
    subsequentActionOptions: {
        type: Array,
        default: () => [],
    },
})

const defaultNode = {
    name: "",
    approver_id: undefined,
    approver_type: "",
    weight: 0,
}

const state = reactive({
    showEditorModal: false,
    showNodeEditor: false,
    currentStep: 0,
    currentNodeStep: -1,
    currentNode: {},
    processForm: {
        nodes: [],
    },
    userOptions: [],
    isUserLoading: false,
    submitFetcher: {},
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

const getForm = () => {
    return [
        {
            key: "name",
            title: "审批流程名称",
            required: true,
        },
        {
            key: "type",
            title: "审批类型",
            type: "select",
            options: props.approvalTypes.map((item) => ({ label: item.displayName, value: item.type })),
            required: true,
        },
        {
            key: "subsequent_action",
            title: "后续节点权限",
            type: "select",
            options: props.subsequentActionOptions,
            required: true,
            defaultValue: "invisible",
            tips: "不可见：在当前节点未审批通过之前，后续节点的审批者无法查看到该审批内容\n可见不可审批：在当前节点未审批通过之前，后续节点的审批者无法查看到该审批内容但无法审批\n可见可审批：该审批内容对后续节点可见，并可被审批",
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
        list.value.doFetch()
    }
    state.showEditorModal = false
}

const onSubmit = async () => {
    try {
        const form = cloneDeep(state.processForm)
        const res = await useFetch(state.submitFetcher).post(route("api.manager.approval.process.edit"), form)
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
                    list.value.doFetch()
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
        `您确认要删除该审批节点吗？`,
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
            title: "审批流程名称",
            width: 200,
            dataIndex: "name",
            filterable: "input",
        },
        {
            title: "审批对象类型",
            width: 200,
            key: "type",
            customRender({ record }) {
                return h("span", {}, find(props.approvalTypes, { type: record.type })?.displayName)
            },
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
            dataIndex: "created_at_datetime",
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
