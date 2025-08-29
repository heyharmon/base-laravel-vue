<script setup>
import moment from 'moment'
import { onMounted, watch, computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useCampaignStore } from '@/stores/campaignStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import { useOrganizationStore } from '@/stores/organizationStore'
import { useUsageStore } from '@/stores/usageStore'
import VisibilityScore from '@/components/VisibilityScore.vue'
import VisibilityBarChart from '@/components/VisibilityBarChart.vue'
import DateFilterDropdown from '@/components/DateFilterDropdown.vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import CampaignSwitcher from '@/components/campaigns/CampaignSwitcher.vue'

const route = useRoute()
const jobStatusStore = useJobStatusStore()
const organizationStore = useOrganizationStore()
const campaignStore = useCampaignStore()
const usageStore = useUsageStore()

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

const usage = computed(() => usageStore.usage)

// Price-based usage only
const usageLimit = computed(() => {
	if (!usage.value) return null
	if (usage.value.limit_price !== null && usage.value.limit_price !== undefined) {
		return usage.value.limit_price
	}
	return null // Unlimited
})

const usageAmount = computed(() => {
	if (!usage.value) return 0
	return usage.value.usage_price || 0
})

const usagePercent = computed(() => {
	if (!usageLimit.value) return 0
	const denom = usageLimit.value || 0
	if (!denom) return 0
	return Math.min((usageAmount.value / denom) * 100, 100)
})

onMounted(async () => {
	if (teamId.value) {
		await campaignStore.fetchCampaigns(teamId.value)
		if (campaignId.value) {
			await campaignStore.switchCampaign(teamId.value, campaignId.value)
			fetchVisibilityData()
		}
		await usageStore.fetchUsage(teamId.value)
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

watch(teamId, async (newTeamId) => {
	if (newTeamId) {
		await usageStore.fetchUsage(newTeamId)
	}
})

const fetchVisibilityData = () => {
	if (teamId.value && campaignId.value) {
		organizationStore.fetchVisibilityMetrics(teamId.value, campaignId.value)
	}
}

// Handle date range changes from dropdown
const handleDateRangeChange = (dateRange) => {
	if (teamId.value && campaignId.value) {
		organizationStore.setDateRange(teamId.value, campaignId.value, dateRange)
	}
}
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
		<!-- Usage information -->
		<div v-if="usage" class="p-4 mt-6 bg-neutral-50 border border-neutral-200 rounded">
			<div class="flex justify-between mb-2 text-sm">
				<span>Monthly Usage</span>
				<span v-if="usageLimit !== null"> ${{ usageAmount.toFixed(2) }} / ${{ usageLimit.toFixed(2) }} </span>
				<span v-else> ${{ (usage.usage_price || 0).toFixed(2) }} / Unlimited </span>
			</div>
			<div class="w-full bg-neutral-200 rounded h-2">
				<div class="h-2 bg-neutral-700 rounded" :style="{ width: usagePercent + '%' }"></div>
			</div>
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
