<script setup>
import moment from 'moment'
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { usePromptStore } from '@/stores/promptStore'
import { useArticleStore } from '@/stores/articleStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import SparkleIcon from '@/components/icons/SparkleIcon.vue'
import Button from '@/components/ui/Button.vue'

const router = useRouter()

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

const hasActiveRunPromptJob = computed(() => {
	let jobs = jobStatusStore.jobs.filter(
		(job) =>
			job.job_class.includes('RunPromptJob') &&
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
	return moment().diff(moment(props.prompt.created_at), 'hours') <= 12
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

const createNewArticle = async () => {
	const newArticle = await articleStore.createArticle({
		title: 'Untitled article',
		prompt_id: props.prompt.id
	})
	router.push({ name: 'articles.edit', params: { id: newArticle.id } })
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

			<div v-if="isNewPrompt" class="flex items-center gap-2 text-xs mt-1">
				<span class="bg-green-100 text-green-800 rounded-full px-2 py-0.5"> Created {{ formattedCreatedAt }} </span>
			</div>
		</div>

		<div class="flex justify-end items-center space-x-2">
			<!-- Create article button -->
			<Button @click.stop="createNewArticle" class="flex items-center gap-2 mr-2" variant="outline" size="sm">
				<SparkleIcon />
				Create article
			</Button>

			<!-- Run prompt button -->
			<div class="relative flex items-center">
				<Button @click.stop="toggleRunMenu" :loading="hasActiveRunPromptJob" :disabled="isLoading" variant="outline" size="sm">
					<span>{{ hasActiveRunPromptJob ? 'Running' : 'Run' }}</span>
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
