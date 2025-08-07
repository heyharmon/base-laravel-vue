<script setup>
import moment from 'moment'
import { onMounted, watch, computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useCampaignStore } from '@/stores/campaignStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import { useOrganizationStore } from '@/stores/organizationStore'
import VisibilityScore from '@/components/VisibilityScore.vue'
import VisibilityChart from '@/components/VisibilityChart.vue'
import DateFilterDropdown from '@/components/DateFilterDropdown.vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import CampaignSwitcher from '@/components/campaigns/CampaignSwitcher.vue'

const route = useRoute()
const jobStatusStore = useJobStatusStore()
const organizationStore = useOrganizationStore()
const campaignStore = useCampaignStore()

// Route params
const teamId = computed(() => route.params.teamId)
const campaignId = computed(() => route.params.campaignId)

// Chart interval based on date range
const chartInterval = ref('monthly')

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
			fetchVisibilityData()

			// Set initial chart interval based on current date range
			if (organizationStore.currentDateRange.startDate && organizationStore.currentDateRange.endDate) {
				chartInterval.value = calculateInterval(organizationStore.currentDateRange.startDate, organizationStore.currentDateRange.endDate)
			}
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
		fetchVisibilityData()
	}
})

const fetchVisibilityData = () => {
	if (teamId.value && campaignId.value) {
		organizationStore.fetchVisibilityMetrics(teamId.value, campaignId.value)
	}
}

// Calculate appropriate interval based on date range
const calculateInterval = (startDate, endDate) => {
	const start = moment(startDate)
	const end = moment(endDate)
	const daysDiff = end.diff(start, 'days')

	if (daysDiff <= 7) {
		return 'daily'
	} else if (daysDiff <= 30) {
		return 'weekly'
	} else {
		return 'monthly'
	}
}

// Handle date range changes from dropdown
const handleDateRangeChange = (dateRange) => {
	if (teamId.value && campaignId.value) {
		// Calculate and set the appropriate interval
		chartInterval.value = calculateInterval(dateRange.startDate, dateRange.endDate)
		organizationStore.setDateRange(teamId.value, campaignId.value, dateRange)
	}
}
</script>

<template>
	<DefaultLayout>
		<div class="flex justify-between items-center pt-6">
			<h1 class="text-2xl font-bold">Dashboard</h1>
			<CampaignSwitcher />
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

		<!-- Date Filter -->
		<div class="mt-6">
			<DateFilterDropdown
				:start-date="organizationStore.currentDateRange.startDate"
				:end-date="organizationStore.currentDateRange.endDate"
				@date-range-changed="handleDateRangeChange"
			/>
		</div>

		<!-- Visibility score -->
		<VisibilityScore v-if="ownedOrg" :organization="ownedOrg" class="mt-6" />

		<!-- Visibility chart -->
		<div class="mt-6">
			<VisibilityChart
				v-if="organizationStore.visibilityMetrics.length > 0"
				:start-date="organizationStore.currentDateRange.startDate"
				:end-date="organizationStore.currentDateRange.endDate"
				:team-id="teamId"
				:campaign-id="campaignId"
				:default-interval="chartInterval"
				class="mt-6"
			/>
		</div>
	</DefaultLayout>
</template>
