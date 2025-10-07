<script setup>
const props = defineProps({
	isOpen: { type: Boolean, required: true }
})
const emit = defineEmits(['confirm', 'cancel'])
</script>

<template>
	<div v-if="isOpen" class="fixed inset-0 bg-neutral-300/50 flex items-center justify-center z-50" @click="$emit('cancel')">
		<div class="bg-white rounded-lg p-6 max-w-md w-full mx-4" @click.stop>
			<h3 class="text-lg font-medium text-neutral-900 mb-3">Heads up</h3>
			<p class="text-sm text-neutral-700 mb-6">Keep prompts balanced. Running single prompts can skew results. If you must, do it sparingly.</p>
			<div class="flex justify-end space-x-3">
				<button
					@click="$emit('cancel')"
					class="inline-flex justify-center px-4 py-2 bg-neutral-200 hover:bg-neutral-100 text-neutral-800 rounded-md cursor-pointer"
				>
					Cancel
				</button>
				<button
					@click="$emit('confirm')"
					class="inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-900 text-white rounded-md cursor-pointer"
				>
					I Understand
				</button>
			</div>
		</div>
	</div>
</template>
