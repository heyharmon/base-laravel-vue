<script setup>
import { onMounted, ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useCampaignStore } from '@/stores/campaignStore'
import { usePromptStore } from '@/stores/promptStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import ActiveJobsIndicator from '@/components/jobs/ActiveJobsIndicator.vue'
import { useOrganizationStore } from '@/stores/organizationStore'
import CampaignSwitcher from '@/components/campaigns/CampaignSwitcher.vue'
import PromptDetailSheet from '@/components/prompts/PromptDetailSheet.vue'
import PromptCreateModal from '@/components/prompts/PromptCreateModal.vue'
import GeneratePromptsModal from '@/components/prompts/GeneratePromptsModal.vue'
import PromptToolbar from '@/components/prompts/PromptToolbar.vue'
import PromptListItem from '@/components/prompts/PromptListItem.vue'
import DeletePromptModal from '@/components/prompts/DeletePromptModal.vue'
import VisibilityScore from '@/components/VisibilityScore.vue'
import DateFilterDropdown from '@/components/DateFilterDropdown.vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'

const route = useRoute()

// Stores
const promptStore = usePromptStore()
const jobStatusStore = useJobStatusStore()
const organizationStore = useOrganizationStore()
const campaignStore = useCampaignStore()

// Route params
const teamId = computed(() => route.params.teamId)
const campaignId = computed(() => route.params.campaignId)

// State for modals
const isPromptCreateModalOpen = ref(false)
const isPromptDetailSheetOpen = ref(false)
const isGenerateModalOpen = ref(false)

// State for viewing prompt details
const selectedPrompt = ref(null)
const selectedPromptId = ref(null)

// Sorting
const sortOption = ref('default') // Default sort option

// Jobs in progress by job class
const processingJobsByClass = computed(() => jobStatusStore.processingJobsByClass)


onMounted(async () => {
        await campaignStore.fetchCampaigns(teamId.value)
        if (campaignId.value) {
                await campaignStore.switchCampaign(teamId.value, campaignId.value)
        }
        await promptStore.fetchPrompts(teamId.value, campaignId.value)
        await organizationStore.fetchVisibilityMetrics(teamId.value, campaignId.value)
        await jobStatusStore.pollJobs(teamId.value, campaignId.value)
})


// Watch for job completions and refresh data
// watch(
// 	// () => jobStatusStore.completedJobs.length,
// 	() => processingJobsByClass.length,
// 	(newCount, oldCount) => {
// 		if (newCount > oldCount) {
// 			promptStore.fetchPrompts(teamId.value, campaignId.value)
// 		}
// 	}
// )

watch(campaignId, async (newId) => {
        if (newId) {
                await campaignStore.switchCampaign(teamId.value, newId)
                await promptStore.fetchPrompts(teamId.value, newId)
                await organizationStore.fetchVisibilityMetrics(teamId.value, newId)
                await jobStatusStore.pollJobs(teamId.value, newId)
        }
})

// Track prompt deletion
// TODO: Move prompt deletion logic to the prompt list item component
const promptToDelete = ref(null)
const showDeleteConfirmation = ref(false)

const runPrompt = async (id, count = 1) => {
        await promptStore.runPrompt(id, count)
        await jobStatusStore.pollJobs(teamId.value, campaignId.value)
}

const runAllPrompts = async (count = 1) => {
	try {
                await promptStore.runAllPrompts(teamId.value, campaignId.value, count)
                await jobStatusStore.pollJobs(teamId.value, campaignId.value)
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

// Handle date range changes from dropdown
const handleDateRangeChange = (dateRange) => {
	organizationStore.setDateRange(teamId.value, campaignId.value, dateRange)
}
</script>

<template>
	<DefaultLayout>
		<div class="flex justify-between items-center py-6">
			<h1 class="text-2xl font-bold">Prompts</h1>
			<CampaignSwitcher />
		</div>

		<div class="flex flex-col space-y-6">
			<!-- Date Filter -->
			<!-- <DateFilterDropdown
				:start-date="organizationStore.currentDateRange.startDate"
				:end-date="organizationStore.currentDateRange.endDate"
				@date-range-changed="handleDateRangeChange"
			/> -->

			<!-- Visibility score -->
			<VisibilityScore v-if="ownedOrg" :organization="ownedOrg" />

			<!-- Main Content -->
			<div class="flex flex-col">
				<!-- Prompts column -->
				<div class="w-full">
					<div class="mb-4 flex justify-between items-center">
						<div class="flex items-center gap-3">
							<h1 class="text-xl font-bold">
								{{ sortedPrompts.length ? sortedPrompts.length : 'No' }} {{ sortedPrompts.length === 1 ? 'Prompt' : 'Prompts' }}
							</h1>
							<div v-if="promptStore.isLoading" class="animate-spin rounded-full size-4 border-b-2 border-neutral-800"></div>
						</div>
						<PromptToolbar
							:sort-option="sortOption"
							@update:sort-option="(v) => (sortOption = v)"
							@run-all="runAllPrompts"
							@add="isPromptCreateModalOpen = true"
							@generate="isGenerateModalOpen = true"
						/>
					</div>

                                        <ActiveJobsIndicator filter-class="Prompt" :show-details="true" class="mb-4" />

					<!-- Jobs currently processing message -->
					<!-- <div v-if="Object.keys(processingJobsByClass).length > 0" class="p-4 mb-6 bg-green-50 border border-green-200 text-green-800 rounded-lg">
						<div class="flex items-center gap-4 mb-2">
							<span class="animate-spin h-4 w-4 border-t-2 border-b-2 border-green-700 rounded-full"></span>
							<span class="font-semibold">Working</span>
						</div>
						<div class="pl-8 space-y-1">
							<div v-for="(jobs, jobClass) in processingJobsByClass" :key="jobClass">
								<div class="flex items-center justify-between">
									<span>{{ jobs[0].output }}</span>
								</div>
								<div v-if="jobs.length > 1" class="flex items-center justify-between">
									<span>{{ jobs[1].output }}</span>
								</div>
								<div v-if="jobs.length > 2" class="flex items-center justify-between">
									<span>{{ jobs[2].output }}</span>
								</div>
							</div>
						</div>
					</div> -->

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
	<GeneratePromptsModal :is-open="isGenerateModalOpen" :team-id="teamId" :campaign-id="campaignId" @close="isGenerateModalOpen = false" />

	<!-- Prompt Modal -->
	<PromptCreateModal :is-open="isPromptCreateModalOpen" :team-id="teamId" :campaign-id="campaignId" @close="isPromptCreateModalOpen = false" />

	<!-- Prompt Detail Sheet -->
	<PromptDetailSheet
		:is-open="isPromptDetailSheetOpen"
		:prompt-id="selectedPromptId"
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
