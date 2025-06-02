<script setup>
import { onMounted, ref, computed, watch } from 'vue'
import { usePromptStore } from '@/stores/promptStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import { useOrganizationStore } from '@/stores/organizationStore'
import PromptDetailSheet from '@/components/prompts/PromptDetailSheet.vue'
import PromptCreateModal from '@/components/prompts/PromptCreateModal.vue'
import GeneratePromptsModal from '@/components/GeneratePromptsModal.vue'
import PromptToolbar from '@/components/prompts/PromptToolbar.vue'
import PromptListItem from '@/components/prompts/PromptListItem.vue'
import DeletePromptModal from '@/components/prompts/DeletePromptModal.vue'
import VisibilityScore from '@/components/VisibilityScore.vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'

const promptStore = usePromptStore()
const jobStatusStore = useJobStatusStore()
const organizationStore = useOrganizationStore()

const isPromptCreateModalOpen = ref(false)
const isPromptDetailSheetOpen = ref(false)
const isGenerateModalOpen = ref(false)

const selectedPrompt = ref(null)
const selectedPromptId = ref(null)
const sortOption = ref('default') // Default sort option

const activePromptJobs = computed(() => {
	const promptJobClasses = ['RunPromptJob', 'FindCompetitorsInResponseJob']
	return jobStatusStore.jobs.filter((job) => {
		return promptJobClasses.some((className) => job.job_class.includes(className)) && (job.status === 'pending' || job.status === 'processing')
	})
})

watch(
	activePromptJobs,
	(newJobs, oldJobs) => {
		if (oldJobs.length > newJobs.length || newJobs.length === 0) {
			// At least one job completed, or all jobs are done
			promptStore.fetchPrompts()
		}
	},
	{ deep: true }
)

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

const ownedOrg = computed(() => {
	if (!organizationStore.visibilityMetrics.length) return null
	return organizationStore.visibilityMetrics.find((org) => !org.is_competitor)
})

onMounted(async () => {
	await promptStore.fetchPrompts()
	await organizationStore.fetchVisibilityMetrics()
})
</script>

<template>
	<DefaultLayout>
		<div class="flex flex-col space-y-4 mt-6">
			<!-- Visibility score -->
			<VisibilityScore v-if="ownedOrg" :organization="ownedOrg" width="w-2/5" />

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

					<!-- Active jobs message -->
					<div
						v-if="activePromptJobs.length > 0"
						class="p-4 mb-4 bg-green-50 border border-green-200 text-green-800 rounded-lg flex items-center gap-2"
					>
						<span class="animate-spin h-4 w-4 mr-2 border-t-2 border-b-2 border-green-700 rounded-full"></span>
						<span>
							{{ activePromptJobs.length }} {{ activePromptJobs.length === 1 ? 'job' : 'jobs' }} are running prompts and looking for competitors.
						</span>
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
	<DeletePromptModal :is-open="showDeleteConfirmation" @cancel="cancelDelete" @confirm="deletePrompt" />
</template>
