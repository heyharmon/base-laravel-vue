<script setup>
import { computed } from 'vue'

const props = defineProps({
	organization: {
		type: Object,
		required: true
	}
})

const visibility = computed(() => {
	if (props.organization?.total_responses === 0) return 'No data'
	return props.organization?.visibility || 0
})

const rank = computed(() => {
	if (props.organization?.total_responses === 0) return 'No data'
	return props.organization?.visibility_rank || '-'
})

const hasData = computed(() => props.organization?.total_responses > 0)
</script>

<template>
	<div class="bg-white rounded-lg p-4 border border-neutral-200 shadow-sm">
		<div class="flex items-center gap-6">
			<div class="w-1/3 flex items-center gap-3">
				<img
					:src="`https://cdn.brandfetch.io/${organization?.website}/w/400/h/400?c=1idaplhOcH8x9kYGESa`"
					:alt="organization?.name + ' logo'"
					class="size-12 object-contain bg-white rounded-lg border border-neutral-200"
				/>
				<div>
					<h1 class="text-lg font-bold">{{ organization?.name }}</h1>
					<p class="text-neutral-500">{{ organization?.website }}</p>
				</div>
			</div>

			<div class="w-1/3 flex flex-col items-center p-3 bg-neutral-50 rounded-lg">
				<div class="text-sm font-medium text-neutral-500 mb-1">Rank</div>
				<div v-if="hasData" class="text-4xl font-medium text-neutral-700">#{{ rank }}</div>
				<div v-else class="text-2xl font-medium text-neutral-400">{{ rank }}</div>
			</div>

			<div class="w-1/3 flex flex-col items-center p-3 bg-neutral-50 rounded-lg">
				<div class="text-sm font-medium text-neutral-500 mb-1">Visibility</div>
				<div v-if="hasData" class="text-4xl font-medium text-green-600 flex items-start gap-0.5">
					{{ visibility }}
					<span class="text-xl">%</span>
				</div>
				<div v-else class="text-2xl font-medium text-neutral-400">{{ visibility }}</div>
			</div>
		</div>
	</div>
</template>
