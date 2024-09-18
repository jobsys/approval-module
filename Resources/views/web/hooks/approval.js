import { h } from "vue"
import { Tag } from "ant-design-vue"

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
	const { approval_status: status } = item
	if (status === "pending") {
		return h(
			Tag,
			{ color: "orange" },
			{
				default: () => "待审核",
			},
		)
	}

	if (status === "processing") {
		return h(
			Tag,
			{ color: "cyan" },
			{
				default: () => "审核中",
			},
		)
	}

	if (status === "rejected") {
		return h(
			Tag,
			{ color: "red" },
			{
				default: () => "未通过",
			},
		)
	}
	if (status === "approved") {
		return h(
			Tag,
			{ color: "green" },
			{
				default: () => "已通过",
			},
		)
	}

	return null
}

/**
 * 获取审核状态文本
 * @param item
 * @returns {null|string}
 */
const useApprovalText = (item) => {
	const { approval_status: status } = item
	if (status === "pending") {
		return "待审核"
	}
	if (status === "processing") {
		return "审核中"
	}
	if (status === "rejected") {
		return "未通过"
	}
	if (status === "approved") {
		return "已通过"
	}

	return null
}

export { useApprovalOptions, useApprovalStatus, useApprovalText }
