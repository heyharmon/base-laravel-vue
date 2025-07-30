p
<script setup>
import { onMounted, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useCampaignStore } from '@/stores/campaignStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import { useOrganizationStore } from '@/stores/organizationStore'
import VisibilityScore from '@/components/VisibilityScore.vue'
import VisibilityChart from '@/components/VisibilityChart.vue'
import DateFilterDropdown from '@/components/DateFilterDropdown.vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import TrashIcon from '../components/icons/TrashIcon.vue'
import CampaignSwitcher from '@/components/campaigns/CampaignSwitcher.vue'

const route = useRoute()
const jobStatusStore = useJobStatusStore()
const organizationStore = useOrganizationStore()
const campaignStore = useCampaignStore()

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
		fetchVisibilityData()
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

const deleteOrganization = async (organizationId) => {
	try {
		await organizationStore.deleteOrganization(teamId.value, organizationId, campaignId.value)
		// Refresh visibility data
		organizationStore.fetchVisibilityMetrics(teamId.value, campaignId.value)
	} catch (error) {
		console.error('Error deleting organization:', error)
	}
}
</script>

<template>
	<DefaultLayout>
		<div class="flex justify-between items-center pt-6">
			<h1 class="text-2xl font-bold">Rankings</h1>
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

		<!-- Simplified Date Filter -->
		<!-- <div class="mt-6">
			<DateFilterDropdown
				:start-date="organizationStore.currentDateRange.startDate"
				:end-date="organizationStore.currentDateRange.endDate"
				@date-range-changed="handleDateRangeChange"
			/>
		</div> -->

		<!-- Visibility score -->
		<VisibilityScore v-if="ownedOrg" :organization="ownedOrg" class="mt-6" />

		<!-- Visibility chart -->
		<!-- <div class="mt-6">
			<VisibilityChart
				v-if="organizationStore.visibilityMetrics.length > 0"
				:start-date="organizationStore.currentDateRange.startDate"
				:end-date="organizationStore.currentDateRange.endDate"
				class="mt-6"
			/>
		</div> -->

		<!-- Rankings -->
		<div class="mt-6 bg-white rounded-lg p-6 border border-neutral-200 shadow-sm">
			<div class="flex items-center gap-2 mb-4">
				<h2 class="text-xl font-bold">Rankings</h2>
				<div v-if="organizationStore.isLoadingVisibility" class="animate-spin rounded-full size-4 border-b-2 border-neutral-800"></div>
			</div>

			<div v-if="organizationStore.visibilityMetrics && organizationStore.visibilityMetrics.length">
				<table class="min-w-full divide-y divide-neutral-200">
					<thead>
						<tr>
							<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider w-1/12">Rank</th>
							<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider w-1/10">Org</th>
							<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider w-1/3">Visibility</th>
							<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider w-1/12"></th>
							<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider w-1/12">Mentions</th>
							<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider w-1/12">Total responses</th>
						</tr>
					</thead>
					<tbody class="bg-white divide-y divide-neutral-200">
						<tr v-for="org in organizationStore.visibilityMetrics" :key="org.id" class="group">
							<td class="px-3 py-2 text-left whitespace-nowrap font-medium text-neutral-500">#{{ org.visibility_rank }}</td>
							<td class="px-3 py-2 flex items-center gap-2 whitespace-nowrap font-medium">
								<img
									:src="`https://cdn.brandfetch.io/${org.website}/w/400/h/400?c=1idaplhOcH8x9kYGESa`"
									:alt="org.name + ' logo'"
									class="size-6 object-contain bg-white rounded-md border border-neutral-200"
								/>
								<span>{{ org.name || (org.is_competitor ? 'Unnamed Competitor' : 'Your Organization') }}</span>
								<span v-if="!org.is_competitor" class="bg-green-100 text-green-800 text-xs px-2 py-0.5 rounded-md">You</span>
							</td>
							<td class="pl-3 pr-4 py-2 whitespace-nowrap text-sm">
								<div class="w-full bg-neutral-200 rounded-full h-2 mr-2">
									<div
										class="h-2 rounded-full"
										:class="org.is_competitor ? 'bg-red-500' : 'bg-green-500'"
										:style="{ width: `${org.visibility}%` }"
									></div>
								</div>
							</td>
							<td class="py-2 whitespace-nowrap text-sm flex items-start gap-0.5">{{ org.visibility }}<span class="text-xs">%</span></td>
							<td class="px-3 py-2 whitespace-nowrap text-sm">{{ org.total_mentions }}</td>
							<td class="px-3 py-2 whitespace-nowrap text-sm">
								{{ org.total_responses }}

								<button
									v-if="org.is_competitor"
									@click="deleteOrganization(org.id)"
									class="float-right group-hover:block hidden text-neutral-300 hover:text-red-500 focus:outline-none cursor-pointer"
								>
									<TrashIcon />
								</button>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div v-else class="text-center py-4 text-neutral-500 text-sm">No organization data available</div>
		</div>
	</DefaultLayout>
</template>
