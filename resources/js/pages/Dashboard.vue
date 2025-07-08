<script setup>
import { onMounted, watch, computed, ref } from 'vue'
import moment from 'moment'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import { useOrganizationStore } from '@/stores/organizationStore'
import VisibilityScore from '@/components/VisibilityScore.vue'
import CalendarPicker from '@/components/CalendarPicker.vue'
import Button from '@/components/ui/Button.vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import TrashIcon from '../components/icons/TrashIcon.vue'

const jobStatusStore = useJobStatusStore()
const organizationStore = useOrganizationStore()

// Date filtering state
const selectedTimeframe = ref('this_month')
const customStartDate = ref(null)
const customEndDate = ref(null)
const isDropdownOpen = ref(false)

// Timeframe options
const timeframeOptions = [
	{ value: 'today', label: 'Today' },
	{ value: 'yesterday', label: 'Yesterday' },
	{ value: 'this_week', label: 'This week' },
	{ value: 'last_week', label: 'Last week' },
	{ value: 'this_month', label: 'This month' },
	{ value: 'last_month', label: 'Last month' },
	{ value: 'this_year', label: 'This year' },
	{ value: 'last_year', label: 'Last year' },
	{ value: 'all_time', label: 'All time' }
]

// Computed date range based on selected timeframe
const dateRange = computed(() => {
	const now = moment()

	switch (selectedTimeframe.value) {
		case 'today':
			return {
				startDate: now.format('YYYY-MM-DD'),
				endDate: now.format('YYYY-MM-DD')
			}
		case 'yesterday':
			return {
				startDate: now.clone().subtract(1, 'day').format('YYYY-MM-DD'),
				endDate: now.clone().subtract(1, 'day').format('YYYY-MM-DD')
			}
		case 'this_week':
			return {
				startDate: now.clone().startOf('week').format('YYYY-MM-DD'),
				endDate: now.format('YYYY-MM-DD')
			}
		case 'last_week':
			return {
				startDate: now.clone().subtract(1, 'week').startOf('week').format('YYYY-MM-DD'),
				endDate: now.clone().subtract(1, 'week').endOf('week').format('YYYY-MM-DD')
			}
		case 'this_month':
			return {
				startDate: now.clone().startOf('month').format('YYYY-MM-DD'),
				endDate: now.format('YYYY-MM-DD')
			}
		case 'last_month':
			return {
				startDate: now.clone().subtract(1, 'month').startOf('month').format('YYYY-MM-DD'),
				endDate: now.clone().subtract(1, 'month').endOf('month').format('YYYY-MM-DD')
			}
		case 'this_year':
			return {
				startDate: now.clone().startOf('year').format('YYYY-MM-DD'),
				endDate: now.format('YYYY-MM-DD')
			}
		case 'last_year':
			return {
				startDate: now.clone().subtract(1, 'year').startOf('year').format('YYYY-MM-DD'),
				endDate: now.clone().subtract(1, 'year').endOf('year').format('YYYY-MM-DD')
			}
		case 'all_time':
			return {
				startDate: null,
				endDate: null
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
	if (customStartDate.value && customEndDate.value) {
		selectedTimeframe.value = 'custom'
		fetchVisibilityMetrics()
	}
})

// Apply custom date range
const applyCustomDateRange = () => {
	if (customStartDate.value && customEndDate.value) {
		fetchVisibilityMetrics()
	}
}

// Apply custom date range and close dropdown
const applyCustomDateRangeAndClose = () => {
	applyCustomDateRange()
	closeDropdown()
}

// Get selected timeframe label
const selectedTimeframeLabel = computed(() => {
	if (selectedTimeframe.value === 'custom' && customStartDate.value && customEndDate.value) {
		return `${moment(customStartDate.value).format('MMM D, YYYY')} - ${moment(customEndDate.value).format('MMM D, YYYY')}`
	}
	const option = timeframeOptions.find((opt) => opt.value === selectedTimeframe.value)
	return option ? option.label : 'Select timeframe'
})

// Toggle dropdown
const toggleDropdown = () => {
	isDropdownOpen.value = !isDropdownOpen.value
}

// Close dropdown when clicking outside
const closeDropdown = () => {
	isDropdownOpen.value = false
}

// Select timeframe and close dropdown
const selectTimeframe = (value) => {
	selectedTimeframe.value = value
	// Clear custom dates when selecting a preset timeframe
	if (value !== 'custom') {
		customStartDate.value = null
		customEndDate.value = null
		isDropdownOpen.value = false
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

		<!-- Date Filter Dropdown -->
		<div class="mt-6 relative">
			<!-- Dropdown Trigger -->
			<button
				@click="toggleDropdown"
				class="flex items-center justify-between w-full max-w-xs px-4 py-2 text-sm font-medium text-neutral-700 bg-white border border-neutral-300 rounded-lg hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
			>
				<span class="flex items-center gap-2">
					<svg class="w-4 h-4 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path
							stroke-linecap="round"
							stroke-linejoin="round"
							stroke-width="2"
							d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
						></path>
					</svg>
					{{ selectedTimeframeLabel }}
				</span>
				<svg
					class="w-4 h-4 text-neutral-500 transition-transform"
					:class="{ 'rotate-180': isDropdownOpen }"
					fill="none"
					stroke="currentColor"
					viewBox="0 0 24 24"
				>
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
				</svg>
			</button>

			<!-- Dropdown Content -->
			<div
				v-if="isDropdownOpen"
				@click.stop
				class="absolute top-full left-0 mt-1 bg-white border border-neutral-200 rounded-lg shadow-lg z-50 min-w-[700px]"
			>
				<div class="flex">
					<!-- Left Column - Timeframe Options -->
					<div class="w-48 p-4 border-r border-neutral-200">
						<div class="space-y-1">
							<button
								v-for="option in timeframeOptions"
								:key="option.value"
								@click="selectTimeframe(option.value)"
								:class="{
									'bg-blue-50 text-blue-700 border-blue-200': selectedTimeframe === option.value,
									'text-neutral-700 hover:bg-neutral-50': selectedTimeframe !== option.value
								}"
								class="w-full text-left px-3 py-2 text-sm rounded-md border border-transparent transition-colors"
							>
								{{ option.label }}
							</button>
						</div>
					</div>

					<!-- Right Column - Calendar Pickers -->
					<div class="flex-1 p-4">
						<div class="flex gap-4">
							<div class="flex-1">
								<div class="mb-2 text-sm font-medium text-neutral-700">Start Date</div>
								<CalendarPicker v-model="customStartDate" :max-date="customEndDate || moment().format('YYYY-MM-DD')" />
							</div>
							<div class="flex-1">
								<div class="mb-2 text-sm font-medium text-neutral-700">End Date</div>
								<CalendarPicker v-model="customEndDate" :min-date="customStartDate" :max-date="moment().format('YYYY-MM-DD')" />
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Backdrop to close dropdown -->
			<div v-if="isDropdownOpen" @click="closeDropdown" class="fixed inset-0 z-40"></div>
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
