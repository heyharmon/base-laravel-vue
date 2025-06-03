<script setup>
import { ref, computed } from 'vue'
import { usePromptStore } from '@/stores/promptStore'
import moment from 'moment'

const promptStore = usePromptStore()

const props = defineProps({
	prompt: { type: Object, required: true },
	isSelected: { type: Boolean, default: false },
	jobs: { type: Array, default: () => [] }
})

const emit = defineEmits(['select', 'run', 'delete'])
const isRunMenuOpen = ref(false)

const isLoading = computed(() => promptStore.loadingPromptIds.includes(props.prompt.id))
const hasActiveJob = computed(() => props.jobs.some((job) => job.trackable_id === props.prompt.id && (job.status === 'pending' || job.status === 'processing')))

const formattedCreatedAt = computed(() => {
	if (!props.prompt.created_at) return ''
	return moment(props.prompt.created_at).fromNow()
})

const isNewPrompt = computed(() => {
	if (!props.prompt.created_at) return false
	return moment().diff(moment(props.prompt.created_at), 'hours') <= 24
})

const toggleRunMenu = () => {
	isRunMenuOpen.value = !isRunMenuOpen.value
}
const closeRunMenu = () => {
	isRunMenuOpen.value = false
}
const runPrompt = (count) => {
	emit('run', props.prompt.id, count)
	closeRunMenu()
}
const confirmDelete = () => emit('delete', props.prompt)
</script>

<template>
	<div
		class="flex items-start justify-between p-4 border border-neutral-300 hover:border-neutral-400 hover:bg-neutral-50 rounded-lg cursor-pointer"
		:class="{ 'border-2 border-neutral-400 bg-neutral-50': isSelected }"
		@click="$emit('select', prompt)"
	>
		<div>
			<p class="text-neutral-800 text-lg">{{ prompt.content }}</p>
			<div v-if="prompt.terms_count >= 0" class="flex items-center gap-2 text-sm text-neutral-500 mt-1">
				<p v-if="prompt.mentions_percentage !== undefined">
					Mentioned {{ prompt.mentions_percentage }}% of the time out of
					{{ prompt.responses_count }}
					{{ prompt.responses_count === 1 ? 'response' : 'responses' }}
				</p>
				<p>•</p>
				<p>
					{{ prompt.terms_count }} term
					{{ prompt.terms_count === 1 ? 'occurrence' : 'occurrences' }}
				</p>
			</div>
			<div v-else class="text-sm text-neutral-500 mt-1">New prompt</div>

			<div class="flex items-center gap-2 text-sm mt-1">
				<span :class="{ 'bg-green-100 text-green-800 rounded-full px-2 py-0.5': isNewPrompt, 'text-neutral-500': !isNewPrompt }">
					Created {{ formattedCreatedAt }}
				</span>
			</div>

			<div v-if="hasActiveJob" class="mt-2 flex items-center text-sm text-blue-600">
				<div class="animate-spin h-3 w-3 border-b-2 border-blue-600 rounded-full mr-2"></div>
				<span>Processing...</span>
			</div>
		</div>
		<div class="flex justify-end space-x-2">
			<div class="relative flex items-center">
				<button
					@click.stop="toggleRunMenu"
					class="px-3 py-1 bg-white text-neutral-800 border border-neutral-400 rounded-md text-xs font-medium hover:bg-neutral-100 transition-colors cursor-pointer flex items-center justify-center min-w-[40px]"
					:disabled="isLoading"
				>
					<div v-if="isLoading" class="animate-spin h-3 w-3 border-b-2 border-neutral-800 rounded-full"></div>
					<span v-else>Run</span>
				</button>
				<div
					v-if="isRunMenuOpen"
					class="absolute right-0 mt-1 w-20 bg-white border border-neutral-300 rounded-md shadow-lg z-10 overflow-hidden"
					@click.stop
				>
					<button @click.stop="runPrompt(1)" class="w-full px-3 py-1.5 text-left text-xs hover:bg-neutral-100 transition-colors cursor-pointer">
						Run 1x
					</button>
					<button @click.stop="runPrompt(3)" class="w-full px-3 py-1.5 text-left text-xs hover:bg-neutral-100 transition-colors cursor-pointer">
						Run 3x
					</button>
					<button @click.stop="runPrompt(5)" class="w-full px-3 py-1.5 text-left text-xs hover:bg-neutral-100 transition-colors cursor-pointer">
						Run 5x
					</button>
				</div>
			</div>
			<button
				@click.stop="confirmDelete"
				class="-mr-2 p-1.5 text-neutral-400 hover:text-neutral-600 transition-colors cursor-pointer"
				aria-label="Delete prompt"
			>
				<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
					<path
						fill-rule="evenodd"
						d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
						clip-rule="evenodd"
					/>
				</svg>
			</button>
		</div>
	</div>
</template>
