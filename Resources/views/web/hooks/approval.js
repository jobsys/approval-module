import { h } from "vue"
import { Tag, Tooltip } from "ant-design-vue"
import {
	ArrowRightOutlined,
	CheckCircleOutlined,
	ClockCircleOutlined,
	CloseCircleOutlined,
	FieldTimeOutlined,
	QuestionCircleOutlined,
	RedoOutlined,
	ReloadOutlined,
	SyncOutlined,
} from "@ant-design/icons-vue"

/**
 * 获取审核状态配置
 * @param item
 * @returns {*|null}
 */
const useApprovalStatusConfig = (item) => {
	const status = item.approval_status || item.status

	const config = {
		pending: { color: "#8f8e8d", text: "待审核", icon: ClockCircleOutlined },
		skipped: { color: "#ca9f71", text: "跳过审核", icon: ReloadOutlined },
		processing: { color: "#08979c", text: "审核中", icon: FieldTimeOutlined },
		rejected: { color: "#cf1322", text: "未通过", icon: CloseCircleOutlined },
		approved: { color: "#389e0d", text: "已通过", icon: CheckCircleOutlined },
		updated: { color: "#1677ff", text: "已更新", icon: SyncOutlined },
		reset: { color: "#000", text: "已重置", icon: RedoOutlined },
	}

	return config[status] || null
}

/**
 * 获取审核状态选项
 * @returns {[{label: string, value: string},{label: string, value: string},{label: string, value: string}]}
 */
const useApprovalOptions = () => {
	return [
		{ label: "待审核", value: "pending" },
		{ label: "审核中", value: "processing" },
		{ label: "已通过", value: "approved" },
		{ label: "未通过", value: "rejected" },
	]
}

/**
 * 获取审核状态标签
 * @param item
 * @returns {VNode|null}
 */
const useApprovalStatus = (item) => {
	if (!item) {
		return null
	}
	const config = useApprovalStatusConfig(item)
	return config
		? h(
				Tag,
				{ color: config.color },
				{
					default: () => config.text,
				},
			)
		: null
}

const useApprovalNodes = ({ record, tasks }) => {
	tasks = tasks || []
	return h("div", { class: "flex items-center" }, [
		useApprovalStatus(record),
		tasks.map((item, index) => {
			const config = useApprovalStatusConfig(item)

			let title = ""
			let icon = config?.icon
			let color = config?.color
			if (!item.approver_id) {
				title = item.node ? `${item.node.name}: 未设定审核人员` : "未设定审核人员"
				icon = QuestionCircleOutlined
				color = "#91caff"
			} else {
				title = item.node ? `${item.node.name}: ${config?.text}` : config?.text
			}

			return h("div", { class: "flex items-center" }, [
				h(Tooltip, { title }, () =>
					h(
						"div",
						{
							style: {
								width: "20px",
								height: "20px",
								backgroundColor: color,
							},
							class: "flex items-center justify-center rounded-xl",
						},
						h(icon, { class: "text-white", style: { fontSize: "18px" } }),
					),
				),
				index < tasks.length - 1
					? h(ArrowRightOutlined, {
							style: {
								fontSize: "12px",
								margin: "0 2px",
								color: "#999",
							},
						})
					: null,
			])
		}),
	])
}

/**
 * 获取审核状态文本
 * @param item
 * @returns {null|string}
 */
const useApprovalText = (item) => {
	return useApprovalStatusConfig(item)?.text || null
}

export { useApprovalOptions, useApprovalStatus, useApprovalText, useApprovalNodes, useApprovalStatusConfig }
