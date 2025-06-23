<script setup>
import TrashIcon from '@/components/icons/TrashIcon.vue'

const props = defineProps({
	term: { type: Object, required: true },
	isSelected: { type: Boolean, default: false }
})

const emit = defineEmits(['select', 'delete'])
</script>

<template>
	<div
		class="p-4 border border-neutral-200 rounded-lg hover:border-neutral-300 transition-colors cursor-pointer"
		:class="{ 'border-2 border-neutral-400 bg-neutral-50': isSelected }"
		@click="$emit('select', term)"
	>
		<div class="flex justify-between items-center">
			<div>
				<span class="text-lg font-medium text-neutral-800">{{ term.name }}</span>
				<div v-if="term.prompts_count >= 0" class="text-sm text-neutral-500 mt-1">
					Found in {{ term.prompts_count }}
					{{ term.prompts_count === 1 ? 'prompt' : 'prompts' }}
				</div>
				<div v-else class="text-sm text-neutral-500 mt-1">New term</div>
			</div>
			<button @click.stop="$emit('delete', term)" class="text-neutral-400 hover:text-neutral-600 transition-colors cursor-pointer">
				<TrashIcon />
			</button>
		</div>
	</div>
</template>
