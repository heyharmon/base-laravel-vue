<script setup>
import { onMounted, ref, computed, watch } from 'vue'
import { usePromptStore } from '@/stores/promptStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import PromptDetailSheet from '@/components/prompts/PromptDetailSheet.vue'
import PromptCreateModal from '@/components/prompts/PromptCreateModal.vue'
import GeneratePromptsModal from '@/components/GeneratePromptsModal.vue'
import PromptToolbar from '@/components/prompts/PromptToolbar.vue'
import PromptListItem from '@/components/prompts/PromptListItem.vue'
import DeletePromptModal from '@/components/prompts/DeletePromptModal.vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'

const promptStore = usePromptStore()
const jobStatusStore = useJobStatusStore()

const isPromptCreateModalOpen = ref(false)
const isPromptDetailSheetOpen = ref(false)
const isGenerateModalOpen = ref(false)

const selectedPrompt = ref(null)
const selectedPromptId = ref(null)
const sortOption = ref('default') // Default sort option

// Track if we have active jobs
const hasActiveJobs = computed(() => {
	return jobStatusStore.jobs && jobStatusStore.jobs.some((job) => job.status === 'pending' || job.status === 'processing')
})

// Track completed jobs to detect when individual jobs complete
const completedJobIds = ref(new Set())

// Track prompt deletion
const promptToDelete = ref(null)
const showDeleteConfirmation = ref(false)

const runPrompt = async (id, count = 1) => {
	await promptStore.runPrompt(id, count)
	await jobStatusStore.pollTeamJobs()
}

const runAllPrompts = async (count = 1) => {
	try {
		await promptStore.runAllPrompts(count)
		await jobStatusStore.pollTeamJobs()
	} catch (error) {
		console.error('Error running all prompts:', error)
	}
}

const confirmDeletePrompt = (prompt) => {
	promptToDelete.value = prompt
	showDeleteConfirmation.value = true
}

const deletePrompt = async () => {
	if (promptToDelete.value) {
		await promptStore.deletePrompt(promptToDelete.value.id)
		promptToDelete.value = null
		showDeleteConfirmation.value = false
	}
}

const cancelDelete = () => {
	promptToDelete.value = null
	showDeleteConfirmation.value = false
}

const sortedPrompts = computed(() => {
	if (!promptStore.prompts || promptStore.prompts.length === 0) return []

	if (sortOption.value === 'default') {
		return [...promptStore.prompts]
	} else if (sortOption.value === 'mentions-asc') {
		return [...promptStore.prompts].sort((a, b) => {
			const aPercentage = a.mentions_percentage !== undefined ? a.mentions_percentage : 0
			const bPercentage = b.mentions_percentage !== undefined ? b.mentions_percentage : 0
			return aPercentage - bPercentage
		})
	} else if (sortOption.value === 'mentions-desc') {
		return [...promptStore.prompts].sort((a, b) => {
			const aPercentage = a.mentions_percentage !== undefined ? a.mentions_percentage : 0
			const bPercentage = b.mentions_percentage !== undefined ? b.mentions_percentage : 0
			return bPercentage - aPercentage
		})
	}

	return [...promptStore.prompts]
})

const showPromptDetails = async (prompt) => {
	selectedPrompt.value = prompt
	selectedPromptId.value = prompt.id
	isPromptDetailSheetOpen.value = true
}

// Watch for changes in job statuses
watch(
	() => jobStatusStore.jobs,
	async (currentJobs, previousJobs) => {
		if (!previousJobs || !currentJobs) return

		// Check for newly completed jobs
		let shouldRefresh = false

		currentJobs.forEach((job) => {
			// If the job is completed or failed and we haven't processed it yet
			if (
				(job.status === 'completed' || job.status === 'failed') &&
				!completedJobIds.value.has(job.job_id) &&
				job.trackable_type === 'App\\Models\\Prompt'
			) {
				// Mark this job as processed
				completedJobIds.value.add(job.job_id)
				shouldRefresh = true
			}
		})

		if (shouldRefresh) {
			await promptStore.fetchPrompts()
		}

		// Check if we've gone from having active jobs to no active jobs
		const previousHasActiveJobs = previousJobs.some((job) => job.status === 'pending' || job.status === 'processing')
		const currentHasActiveJobs = currentJobs.some((job) => job.status === 'pending' || job.status === 'processing')

		if (previousHasActiveJobs && !currentHasActiveJobs) {
			await promptStore.fetchPrompts()
		}
	},
	{ immediate: false }
)

onMounted(async () => {
	await promptStore.fetchPrompts()
})
</script>

<template>
	<DefaultLayout>
		<div class="flex flex-col space-y-4 mt-6">
			<!-- Main Content -->
			<div class="flex flex-col">
				<!-- Prompts column -->
				<div class="w-full">
                                        <div class="mb-4">
                                                <PromptToolbar
                                                        :sort-option="sortOption"
                                                        @update:sort-option="(v) => (sortOption = v)"
                                                        @run-all="runAllPrompts"
                                                        @add="isPromptCreateModalOpen = true"
                                                        @generate="isGenerateModalOpen = true"
                                                />
                                        </div>

                                        <div v-if="sortedPrompts.length" class="space-y-4">
                                                <PromptListItem
                                                        v-for="prompt in sortedPrompts"
                                                        :key="prompt.id"
                                                        :prompt="prompt"
                                                        :is-selected="selectedPromptId === prompt.id"
                                                        :jobs="jobStatusStore.jobs || []"
                                                        @select="showPromptDetails"
                                                        @run="(id, count) => runPrompt(id, count)"
                                                        @delete="confirmDeletePrompt"
                                                />
                                        </div>
					<div v-else class="text-center py-4 text-neutral-500 text-sm">No prompts data available</div>
				</div>
			</div>
		</div>
	</DefaultLayout>

	<!-- Generate Modal -->
	<GeneratePromptsModal :is-open="isGenerateModalOpen" @close="isGenerateModalOpen = false" />

	<!-- Prompt Modal -->
	<PromptCreateModal :is-open="isPromptCreateModalOpen" @close="isPromptCreateModalOpen = false" />

	<!-- Prompt Detail Sheet -->
	<PromptDetailSheet
		:is-open="isPromptDetailSheetOpen"
		:prompt="selectedPrompt"
		:prompt-id="selectedPrompt?.id"
		@close="
			() => {
				isPromptDetailSheetOpen = false
				selectedPromptId = null
			}
		"
	/>

        <!-- Delete Confirmation Modal -->
        <DeletePromptModal
                :is-open="showDeleteConfirmation"
                @cancel="cancelDelete"
                @confirm="deletePrompt"
        />
</template>
