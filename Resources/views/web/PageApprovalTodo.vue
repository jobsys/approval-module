<template>
	<div class="mb-4 shadow rounded-lg" v-for="approvable in approvables" :key="approvable.slug">
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
</template>

<script setup>
import { router } from "@inertiajs/vue3"
import { inject } from "vue"

defineProps({
	approvables: { type: Array, default: () => [] },
})

const route = inject("route")

const onChangeType = (item) => {
	router.visit(route("page.manager.todo.approval.list", { slug: item.slug }))
}
</script>
