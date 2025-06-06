<script setup>
import { ref, computed } from 'vue'
import { usePromptStore } from '@/stores/promptStore'
import { useArticleStore } from '@/stores/articleStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import moment from 'moment'
import Button from '@/components/ui/Button.vue'

const promptStore = usePromptStore()
const articleStore = useArticleStore()
const jobStatusStore = useJobStatusStore()

const props = defineProps({
	prompt: { type: Object, required: true },
	isSelected: { type: Boolean, default: false },
	jobs: { type: Array, default: () => [] }
})

const emit = defineEmits(['select', 'run', 'delete', 'generate-article'])
const isRunMenuOpen = ref(false)

const isLoading = computed(() => promptStore.loadingPromptIds.includes(props.prompt.id))
const hasActiveJob = computed(() => props.jobs.some((job) => job.trackable_id === props.prompt.id && (job.status === 'pending' || job.status === 'processing')))

const activeArticleJobForThisPrompt = computed(() => {
	let jobs = jobStatusStore.jobs.filter(
		(job) =>
			job.job_class.includes('GenerateArticleJob') &&
			job.trackable_type === 'App\\Models\\Prompt' &&
			job.trackable_id === props.prompt.id &&
			(job.status === 'pending' || job.status === 'processing')
	)

	return jobs.length > 0
})

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

const generateArticle = async () => {
	if (!props.prompt.id) return

	try {
		await articleStore.generateArticle(props.prompt.id)
		jobStatusStore.pollTeamJobs()
		emit('generate-article', props.prompt.id)
	} catch (error) {
		console.error('Error generating article:', error)
	}
}
</script>

<template>
	<div
		class="flex items-start justify-between p-4 border border-neutral-300 hover:border-neutral-500 rounded-lg cursor-pointer"
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

			<div class="flex items-center gap-2 text-xs mt-1">
				<span :class="{ 'bg-green-100 text-green-800 rounded-full px-2 py-0.5': isNewPrompt, 'text-neutral-500': !isNewPrompt }">
					Created {{ formattedCreatedAt }}
				</span>
			</div>

			<div v-if="hasActiveJob" class="mt-2 flex items-center text-sm text-blue-600">
				<div class="animate-spin h-3 w-3 border-b-2 border-blue-600 rounded-full mr-2"></div>
				<span>Processing...</span>
			</div>
		</div>

		<div class="flex justify-end items-center space-x-2">
			<Button @click.stop="generateArticle" class="flex items-center gap-2 mr-2" :disabled="activeArticleJobForThisPrompt" variant="outline" size="sm">
				<svg
					v-if="!activeArticleJobForThisPrompt"
					xmlns="http://www.w3.org/2000/svg"
					width="16"
					height="16"
					viewBox="0 0 24 24"
					fill="none"
					stroke="currentColor"
					stroke-width="2"
					stroke-linecap="round"
					stroke-linejoin="round"
					class="lucide lucide-sparkles"
				>
					<path
						d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"
					/>
					<path d="M5 3v4" />
					<path d="M19 17v4" />
					<path d="M3 5h4" />
					<path d="M17 19h4" />
				</svg>
				<div v-else-if="activeArticleJobForThisPrompt" class="animate-spin rounded-full h-4 w-4 border border-b-transparent border-neutral-800"></div>
				<span>{{ activeArticleJobForThisPrompt ? 'Generating article' : 'Generate article' }}</span>
			</Button>

			<div class="relative flex items-center">
				<Button @click.stop="toggleRunMenu" :disabled="isLoading" variant="outline" size="sm">
					<div v-if="isLoading" class="animate-spin h-3 w-3 border-b-2 border-neutral-800 rounded-full"></div>
					<span v-else>Run</span>
				</Button>

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
