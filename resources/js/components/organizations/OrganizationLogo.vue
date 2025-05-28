<script setup>
import { computed } from 'vue'

const props = defineProps({
	organization: {
		type: Object,
		required: true
	},
	size: {
		type: String,
		default: 'md'
	}
})

const initials = computed(() => {
	if (!props.organization.name) return ''

	return props.organization.name
		.split(' ')
		.map((word) => word.charAt(0))
		.join('')
		.toUpperCase()
		.substring(0, 2)
})

const sizeClasses = computed(() => {
	const sizes = {
		sm: 'h-8 w-8 text-sm',
		md: 'h-16 w-16 text-xl',
		lg: 'h-24 w-24 text-2xl'
	}

	return sizes[props.size] || sizes.md
})
</script>

<template>
	<div :class="sizeClasses">
		<img
			v-if="organization.website"
			:src="`https://cdn.brandfetch.io/${organization.website}/w/400/h/400?c=1idaplhOcH8x9kYGESa`"
			:alt="organization.name + ' logo'"
			class="h-full w-full object-contain bg-white rounded-md border border-neutral-200"
		/>
		<div v-else class="h-full w-full flex items-center justify-center bg-neutral-100 rounded-md border border-neutral-200 text-neutral-600 font-medium">
			{{ initials }}
		</div>
	</div>
</template>
