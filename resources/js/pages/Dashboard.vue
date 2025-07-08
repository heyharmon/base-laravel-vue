<script setup>
import { onMounted, watch, computed, ref } from 'vue'
import moment from 'moment'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import { useOrganizationStore } from '@/stores/organizationStore'
import VisibilityScore from '@/components/VisibilityScore.vue'
import DatePicker from '@/components/DatePicker.vue'
import Button from '@/components/ui/Button.vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import TrashIcon from '../components/icons/TrashIcon.vue'

const jobStatusStore = useJobStatusStore()
const organizationStore = useOrganizationStore()

// Date filtering state
const selectedTimeframe = ref('last_28_days')
const customStartDate = ref(null)
const customEndDate = ref(null)

// Timeframe options
const timeframeOptions = [
	{ value: 'last_7_days', label: 'Last 7 days' },
	{ value: 'last_14_days', label: 'Last 14 days' },
	{ value: 'last_28_days', label: 'Last 28 days' },
	{ value: 'last_90_days', label: 'Last 90 days' },
	{ value: 'this_year', label: 'This year' },
	{ value: 'custom', label: 'Custom range' }
]

// Computed date range based on selected timeframe
const dateRange = computed(() => {
	const now = moment()

	switch (selectedTimeframe.value) {
		case 'last_7_days':
			return {
				startDate: now.clone().subtract(7, 'days').format('YYYY-MM-DD'),
				endDate: now.format('YYYY-MM-DD')
			}
		case 'last_14_days':
			return {
				startDate: now.clone().subtract(14, 'days').format('YYYY-MM-DD'),
				endDate: now.format('YYYY-MM-DD')
			}
		case 'last_28_days':
			return {
				startDate: now.clone().subtract(28, 'days').format('YYYY-MM-DD'),
				endDate: now.format('YYYY-MM-DD')
			}
		case 'last_90_days':
			return {
				startDate: now.clone().subtract(90, 'days').format('YYYY-MM-DD'),
				endDate: now.format('YYYY-MM-DD')
			}
		case 'this_year':
			return {
				startDate: now.clone().startOf('year').format('YYYY-MM-DD'),
				endDate: now.format('YYYY-MM-DD')
			}
		case 'custom':
			return {
				startDate: customStartDate.value,
				endDate: customEndDate.value
			}
		default:
			return {
				startDate: null,
				endDate: null
			}
	}
})

// Fetch visibility metrics with date filters
const fetchVisibilityMetrics = async () => {
	const params = {}
	if (dateRange.value.startDate) params.startDate = dateRange.value.startDate
	if (dateRange.value.endDate) params.endDate = dateRange.value.endDate

	await organizationStore.fetchVisibilityMetrics(params)
}

// Watch for timeframe changes
watch(selectedTimeframe, () => {
	if (selectedTimeframe.value !== 'custom') {
		fetchVisibilityMetrics()
	}
})

// Watch for custom date changes
watch([customStartDate, customEndDate], () => {
	if (selectedTimeframe.value === 'custom' && customStartDate.value && customEndDate.value) {
		fetchVisibilityMetrics()
	}
})

// Apply custom date range
const applyCustomDateRange = () => {
	if (customStartDate.value && customEndDate.value) {
		fetchVisibilityMetrics()
	}
}

const processingJobsByClass = computed(() => jobStatusStore.processingJobsByClass)

// Watch for job status changes
watch(
	() => jobStatusStore.jobs,
	() => {
		// Check if there are any newly completed jobs
		const hasCompletedJobs = jobStatusStore.completedJobs.length > 0
		if (hasCompletedJobs) {
			console.log('Jobs completed, refreshing visibility metrics')
			fetchVisibilityMetrics()
		}
	},
	{ deep: true }
)

// Computed property for the owned organization
const ownedOrg = computed(() => {
	if (!organizationStore.visibilityMetrics.length) return null
	return organizationStore.visibilityMetrics.find((org) => !org.is_competitor)
})

onMounted(async () => {
	await fetchVisibilityMetrics()
})

const deleteOrganization = async (organizationId) => {
	try {
		await organizationStore.deleteOrganization(organizationId)
		await fetchVisibilityMetrics()
	} catch (error) {
		console.error('Error deleting organization:', error)
	}
}
</script>

<template>
	<DefaultLayout>
		<!-- Jobs currently processing message -->
		<div v-if="Object.keys(processingJobsByClass).length > 0" class="p-4 my-6 bg-green-50 border border-green-200 text-green-800 rounded-lg">
			<div class="flex items-center gap-4 mb-2">
				<span class="animate-spin h-4 w-4 border-t-2 border-b-2 border-green-700 rounded-full"></span>
				<span class="font-semibold">Setting up your team</span>
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

		<!-- Date Filter Controls -->
		<div class="mt-6 bg-white rounded-lg p-6 border border-neutral-200 shadow-sm">
			<h3 class="text-lg font-semibold mb-4">Time Period</h3>

			<!-- Timeframe Selection -->
			<div class="flex flex-wrap gap-2 mb-4">
				<button
					v-for="option in timeframeOptions"
					:key="option.value"
					@click="selectedTimeframe = option.value"
					:class="{
						'bg-blue-600 text-white': selectedTimeframe === option.value,
						'bg-neutral-100 text-neutral-700 hover:bg-neutral-200': selectedTimeframe !== option.value
					}"
					class="px-3 py-2 text-sm font-medium rounded-md transition-colors cursor-pointer"
				>
					{{ option.label }}
				</button>
			</div>

			<!-- Custom Date Range -->
			<div v-if="selectedTimeframe === 'custom'" class="flex gap-4 items-end">
				<div class="flex-1">
					<label class="block text-sm font-medium text-neutral-700 mb-1">Start Date</label>
					<DatePicker v-model="customStartDate" placeholder="Select start date" :max-date="customEndDate || moment().format('YYYY-MM-DD')" />
				</div>
				<div class="flex-1">
					<label class="block text-sm font-medium text-neutral-700 mb-1">End Date</label>
					<DatePicker v-model="customEndDate" placeholder="Select end date" :min-date="customStartDate" :max-date="moment().format('YYYY-MM-DD')" />
				</div>
				<Button @click="applyCustomDateRange" :disabled="!customStartDate || !customEndDate" class="px-4 py-2"> Apply </Button>
			</div>
		</div>

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
