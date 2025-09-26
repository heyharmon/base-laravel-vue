<script setup>
import moment from 'moment'
import { onMounted, onUnmounted, watch, computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useCampaignStore } from '@/stores/campaignStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import { useOrganizationStore } from '@/stores/organizationStore'
import { usePromptStore } from '@/stores/promptStore'
import VisibilityScore from '@/components/VisibilityScore.vue'
import VisibilityBarChart from '@/components/VisibilityBarChart.vue'
import DateFilterDropdown from '@/components/DateFilterDropdown.vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import CampaignSwitcher from '@/components/campaigns/CampaignSwitcher.vue'

const route = useRoute()
const jobStatusStore = useJobStatusStore()
const organizationStore = useOrganizationStore()
const campaignStore = useCampaignStore()
const promptStore = usePromptStore()

// Route params
const teamId = computed(() => route.params.teamId)
const campaignId = computed(() => route.params.campaignId)

// Jobs in progress by job class
const processingJobsByClass = computed(() => jobStatusStore.processingJobsByClass)

// The owned organization
const ownedOrg = computed(() => {
	if (!organizationStore.visibilityMetrics.length) return null
	return organizationStore.visibilityMetrics.find((org) => !org.is_competitor)
})

onMounted(async () => {
	if (teamId.value) {
		await campaignStore.fetchCampaigns(teamId.value)
		if (campaignId.value) {
			await campaignStore.switchCampaign(teamId.value, campaignId.value)
			// Load prompts so we can detect in-progress responses
			await promptStore.fetchPrompts(teamId.value, campaignId.value, organizationStore.currentDateRange)
			fetchVisibilityData()
		}
	}
})

// Watch for job completions and refresh data
watch(
	() => jobStatusStore.completedJobs.length,
	(newCount, oldCount) => {
		console.log(`Jobs completed: ${newCount}, Previous count: ${oldCount}`)
		if (newCount > oldCount) {
			console.log('Jobs completed, refreshing visibility metrics')
			fetchVisibilityData()
		}
	}
)

watch(campaignId, async (newId) => {
	if (newId) {
		await campaignStore.switchCampaign(teamId.value, newId)
		await promptStore.fetchPrompts(teamId.value, newId, organizationStore.currentDateRange)
		fetchVisibilityData()
	}
})

const fetchVisibilityData = () => {
	if (teamId.value && campaignId.value) {
		organizationStore.fetchCampaignVisibilityMetrics(teamId.value, campaignId.value)
	}
}

// Handle date range changes from dropdown
const handleDateRangeChange = (dateRange) => {
	if (teamId.value && campaignId.value) {
		organizationStore.setDateRange(teamId.value, campaignId.value, dateRange)
	}
}

// ---- In-progress prompt responses detection and polling ----
const hasInProgressResponses = computed(() => {
	return (promptStore.prompts || []).some((p) => Array.isArray(p?.in_progress_responses) && p.in_progress_responses.length > 0)
})

const inProgressResponsesCount = computed(() => {
	return (promptStore.prompts || []).reduce((sum, p) => sum + (p?.in_progress_responses?.length || 0), 0)
})

let inProgressRefreshTimer = null

watch(hasInProgressResponses, async (hasAny) => {
	if (hasAny && !inProgressRefreshTimer) {
		inProgressRefreshTimer = setInterval(async () => {
			await promptStore.fetchPrompts(teamId.value, campaignId.value, organizationStore.currentDateRange)
			await organizationStore.fetchCampaignVisibilityMetrics(teamId.value, campaignId.value)
		}, 3000)
	} else if (!hasAny && inProgressRefreshTimer) {
		clearInterval(inProgressRefreshTimer)
		inProgressRefreshTimer = null
		await promptStore.fetchPrompts(teamId.value, campaignId.value, organizationStore.currentDateRange)
		await organizationStore.fetchCampaignVisibilityMetrics(teamId.value, campaignId.value)
	}
})

onUnmounted(() => {
	if (inProgressRefreshTimer) {
		clearInterval(inProgressRefreshTimer)
		inProgressRefreshTimer = null
	}
})
</script>

<template>
	<DefaultLayout>
		<div class="flex justify-between items-center pt-6">
			<h1 class="text-2xl font-bold">Dashboard</h1>
			<div class="flex items-center gap-3">
				<DateFilterDropdown @date-range-changed="handleDateRangeChange" />
				<CampaignSwitcher />
			</div>
		</div>

		<!-- Prompt responses in progress indicator -->
		<div v-if="hasInProgressResponses" class="p-4 my-4 bg-green-50 border border-green-200 text-green-800 rounded-lg flex items-center gap-2">
			<span class="animate-spin h-4 w-4 mr-2 border-t-2 border-b-2 border-green-700 rounded-full"></span>
			<span>
				{{ inProgressResponsesCount }}
				{{ inProgressResponsesCount === 1 ? 'prompt response is being processed' : 'prompt responses are being processed' }}
				— metrics will update automatically.
			</span>
		</div>
		<!-- Jobs currently processing message -->
		<div v-if="Object.keys(processingJobsByClass).length > 0" class="p-4 my-6 bg-green-50 border border-green-200 text-green-800 rounded-lg">
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
		</div>

		<!-- Visibility score -->
		<VisibilityScore v-if="ownedOrg" :organization="ownedOrg" class="mt-6" />

		<!-- Visibility chart -->
		<div class="mt-6">
			<VisibilityBarChart
				v-if="organizationStore.visibilityMetrics.length > 0"
				:start-date="organizationStore.currentDateRange.startDate"
				:end-date="organizationStore.currentDateRange.endDate"
				:team-id="teamId"
				:campaign-id="campaignId"
				class="mt-6"
			/>
		</div>
	</DefaultLayout>
</template>
