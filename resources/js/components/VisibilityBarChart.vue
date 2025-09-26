<template>
	<div class="bg-white rounded-lg border border-neutral-200 shadow-sm">
		<div class="flex items-center justify-between px-6 pt-6 mb-4">
			<div class="flex items-center gap-2">
				<h2 class="text-xl font-medium">{{ title }}</h2>
				<div v-if="isLoading" class="animate-spin rounded-full size-4 border-b-2 border-neutral-800"></div>
			</div>

			<div class="relative">
				<p class="text-sm text-neutral-400">{{ selectedIntervalLabel }}</p>
			</div>
			<!-- Interval selector -->
			<!-- <div class="relative">
				<button
					@click="isDropdownOpen = !isDropdownOpen"
					class="flex items-center justify-between gap-2 text-sm border border-neutral-200 rounded-md px-3 py-1.5 bg-white hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
				>
					<span>{{ selectedIntervalLabel }}</span>
					<ChevronDownIcon class="text-neutral-500 transition-transform" :class="{ 'rotate-180': isDropdownOpen }" />
				</button>
				<div v-if="isDropdownOpen" class="absolute top-full right-0 mt-1 bg-white border border-neutral-200 rounded-md shadow-lg z-50 min-w-[120px]">
					<button
						v-for="option in intervalOptions"
						:key="option.value"
						@click="selectInterval(option.value)"
						:disabled="option.disabled"
						:class="{
							'bg-blue-50 text-blue-700': selectedInterval === option.value && !option.disabled,
							'text-neutral-700 hover:bg-neutral-50': selectedInterval !== option.value && !option.disabled,
							'text-neutral-400 cursor-not-allowed': option.disabled
						}"
						class="w-full text-left px-3 py-2 text-sm transition-colors first:rounded-t-md last:rounded-b-md"
					>
						{{ option.label }}
					</button>
				</div>
				<div v-if="isDropdownOpen" @click="isDropdownOpen = false" class="fixed inset-0 z-40"></div>
			</div> -->
		</div>

		<div class="relative pl-3" style="height: 440px">
			<div ref="chartContainer"></div>
		</div>

		<div v-if="!isLoading && (!chartData || chartData.length === 0)" class="text-center py-8 text-neutral-500">
			No data available for the selected date range
		</div>
	</div>
</template>

<script setup>
import { ref, onMounted, watch, computed, nextTick, onUnmounted, onBeforeUnmount } from 'vue'
import { useOrganizationStore } from '@/stores/organizationStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import ApexCharts from 'apexcharts'
import api from '@/services/api'
import moment from 'moment'
import ChevronDownIcon from '@/components/icons/ChevronDownIcon.vue'

const props = defineProps({
	startDate: String,
	endDate: String,
	teamId: String,
	campaignId: String,
	promptId: {
		type: [String, Number],
		default: null
	},
	defaultInterval: {
		type: String,
		default: 'monthly'
	},
	title: {
		type: String,
		default: 'Visibility over time'
	}
})

const organizationStore = useOrganizationStore()
const jobStatusStore = useJobStatusStore()

const chartContainer = ref(null)
const chart = ref(null)
const isLoading = ref(false)
const chartData = ref([])
const selectedInterval = ref('monthly')
const isDropdownOpen = ref(false)

// Calculate appropriate interval based on date range
const calculateInterval = (startDate, endDate) => {
	// If dates are null (all_time), force monthly
	if (!startDate || !endDate) return 'monthly'

	const start = moment(startDate)
	const end = moment(endDate)
	const daysDiff = end.diff(start, 'days')

	// If date range is > 365 days, force monthly
	if (daysDiff > 365) {
		return 'monthly'
	} else if (daysDiff <= 7) {
		return 'daily'
	} else if (daysDiff <= 30) {
		return 'weekly'
	} else {
		return 'monthly'
	}
}

const intervalOptions = computed(() => {
	const isAllTime = !props.startDate || !props.endDate
	const daysDiff = getDaysDifference()
	const shouldDisableShortIntervals = isAllTime || daysDiff > 365

	const options = [
		{ value: 'daily', label: 'Daily', disabled: shouldDisableShortIntervals },
		{ value: 'weekly', label: 'Weekly', disabled: shouldDisableShortIntervals },
		{ value: 'monthly', label: 'Monthly', disabled: false }
	]
	return options
})

const getDaysDifference = () => {
	// If dates are null (all_time), return a large number to indicate unlimited range
	if (!props.startDate || !props.endDate) return Infinity
	const start = moment(props.startDate)
	const end = moment(props.endDate)
	return end.diff(start, 'days')
}

const selectedIntervalLabel = computed(() => {
	const option = intervalOptions.value.find((opt) => opt.value === selectedInterval.value)
	return option ? option.label : 'Monthly'
})

const selectInterval = (value) => {
	// Don't select disabled options
	const option = intervalOptions.value.find((opt) => opt.value === value)
	if (option && option.disabled) return

	selectedInterval.value = value
	isDropdownOpen.value = false
	fetchChartData()
}

// Keep track of the latest request ID
let latestRequestId = 0

const fetchChartData = async () => {
	// Don't fetch if we're unmounting or missing required props
	if (isUnmounting.value || !props.teamId || (!props.campaignId && !props.promptId)) return

	isLoading.value = true

	try {
		const currentRequestId = Date.now()
		latestRequestId = currentRequestId

		const params = new URLSearchParams({
			interval: selectedInterval.value,
			timezone: Intl.DateTimeFormat().resolvedOptions().timeZone // User's timezone
		})

		// Only add date parameters if they are not null
		if (props.startDate) {
			params.append('start_date', props.startDate)
		}
		if (props.endDate) {
			params.append('end_date', props.endDate)
		}

		// Determine which endpoint to use
		let endpoint
		if (props.promptId) {
			// Prompt-specific visibility
			endpoint = `/prompts/${props.promptId}/visibility-chart`
		} else {
			// Overall campaign visibility
			endpoint = `/teams/${props.teamId}/campaigns/${props.campaignId}/organization-visibility/chart`

			// For campaign view, include owned org
			const ownedOrg = organizationStore.visibilityMetrics.find((org) => !org.is_competitor)
			if (ownedOrg) {
				params.append('organization_ids[]', ownedOrg.id)
			}
		}

		const response = await api.get(`${endpoint}?${params}`)

		if (currentRequestId !== latestRequestId || isUnmounting.value) {
			return
		}

		chartData.value = response.organizations || []

		if (isUnmounting.value) return

		await nextTick()

		if (!isUnmounting.value && chartContainer.value) {
			updateChart()
		}
	} catch (error) {
		console.error('Error fetching chart data:', error)
	} finally {
		isLoading.value = false
	}
}

// Flag to track if component is being unmounted
const isUnmounting = ref(false)

// Safe chart destruction
const safeDestroyChart = () => {
	try {
		if (chart.value) {
			chart.value.destroy()
			chart.value = null
		}
	} catch (error) {
		console.error('Error safely destroying chart:', error)
	}
}

const updateChart = () => {
	// Don't update if unmounting or container is gone
	if (!chartContainer.value || isUnmounting.value) return

	try {
		// Safely destroy existing chart
		safeDestroyChart()

		// Wait for the next tick to ensure DOM is updated
		nextTick(() => {
			// Make sure container is still available and we're not unmounting
			if (!chartContainer.value || isUnmounting.value) return

			// Check if chartData is valid before proceeding
			if (!chartData.value || !Array.isArray(chartData.value) || chartData.value.length === 0) {
				return
			}

			// Format the data for ApexCharts bar chart
			// Use null for points with no responses to indicate no data
			const series = chartData.value.map((org) => ({
				name: org.name,
				data:
					org.data?.map((point) => {
						// If there are no responses, return null to indicate no data
						return point.responses === 0 ? null : point.visibility
					}) || []
			}))

			// Get categories (dates) from the first organization's data points
			const categories = chartData.value.length > 0 && chartData.value[0].data ? chartData.value[0].data.map((point) => point.date) : []

			// Prepare colors array
			const colors = chartData.value.map((org) => org.color)

			// Create ApexCharts options for bar chart
			const options = {
				chart: {
					type: 'bar',
					height: 400,
					fontFamily: 'inherit',
					toolbar: {
						show: false
					},
					animations: {
						enabled: true,
						easing: 'easeinout',
						speed: 800
					},
					zoom: {
						enabled: false
					}
				},
				plotOptions: {
					bar: {
						horizontal: false,
						columnWidth: '55%',
						endingShape: 'rounded',
						dataLabels: {
							position: 'top'
						}
					}
				},
				colors: colors,
				series: series,
				dataLabels: {
					enabled: true,
					formatter: function (val, opts) {
						// Show "No Data" indicator for null values
						if (val === null) {
							return 'No data'
						}
						return ''
					},
					offsetY: -30,
					style: {
						fontSize: '13px',
						colors: ['#999']
					}
				},
				stroke: {
					show: true,
					width: 2,
					colors: ['transparent']
				},
				xaxis: {
					categories: categories,
					// labels: {
					// 	formatter: function (value, timestamp, opts) {
					// 		// Check if any series has data for this category index
					// 		const categoryIndex = opts.dataPointIndex !== undefined ? opts.dataPointIndex : categories.indexOf(value)

					// 		// Check if all series have null values for this category
					// 		const hasNoData = series.every((serie) => !serie.data[categoryIndex] || serie.data[categoryIndex] === null)

					// 		if (hasNoData) {
					// 			return value + '\n(no data)'
					// 		}
					// 		return value
					// 	},
					// 	style: {
					// 		fontSize: '12px'
					// 	}
					// },
					tooltip: {
						enabled: false
					}
				},
				yaxis: {
					min: 0,
					max: 100,
					decimals: 0,
					labels: {
						formatter: function (value) {
							return value + '%'
						}
					}
				},
				fill: {
					opacity: 1
				},
				tooltip: {
					shared: true,
					intersect: false,
					y: {
						formatter: function (value) {
							return value === null ? 'No data' : value + '%'
						}
					},
					custom: function ({ series, seriesIndex, dataPointIndex, w }) {
						if (
							!chartData.value ||
							!chartData.value[seriesIndex] ||
							!chartData.value[seriesIndex].data ||
							!chartData.value[seriesIndex].data[dataPointIndex]
						) {
							return '<div class="p-2 bg-white border border-neutral-200 rounded shadow">No data available</div>'
						}

						const orgData = chartData.value[seriesIndex]
						const point = orgData.data[dataPointIndex]

						// Special handling for no responses
						if (point.responses === 0) {
							return `
							<div class="p-2 bg-white border border-neutral-200 rounded shadow">
								<div class="font-bold">${orgData.name}</div>
								<div class="text-neutral-500">No data for this period</div>
								<div class="text-sm text-neutral-400">0 responses</div>
							</div>
							`
						}

						return `
						<div class="p-2 bg-white border border-neutral-200 rounded shadow">
							<div class="font-bold">${orgData.name}</div>
							<div>Visibility: ${point.visibility}%</div>
							<div>Mentions: ${point.mentions}</div>
							<div>Total Responses: ${point.responses}</div>
						</div>
						`
					}
				},
				legend: {
					position: 'bottom',
					horizontalAlign: 'center',
					offsetY: 8,
					markers: {
						width: 10,
						height: 10,
						radius: 2
					}
				},
				grid: {
					borderColor: '#e0e0e0',
					row: {
						colors: ['#f3f3f3', 'transparent'],
						opacity: 0.5
					}
				}
			}

			// Create new chart with robust error handling
			try {
				// Final check before creating chart
				if (!isUnmounting.value && chartContainer.value) {
					chart.value = new ApexCharts(chartContainer.value, options)
					chart.value.render()
				}
			} catch (chartError) {
				console.error('Error creating chart:', chartError)
			}
		})
	} catch (error) {
		console.error('Error updating chart:', error)
	}
}

// Watch for date changes and auto-calculate interval
watch(
	() => [props.startDate, props.endDate],
	([newStartDate, newEndDate]) => {
		// Auto-calculate interval based on date range
		const calculatedInterval = calculateInterval(newStartDate, newEndDate)
		if (calculatedInterval !== selectedInterval.value) {
			selectedInterval.value = calculatedInterval
		}

		// If currently selected interval becomes disabled, switch to monthly
		const currentOption = intervalOptions.value.find((opt) => opt.value === selectedInterval.value)
		if (currentOption && currentOption.disabled) {
			selectedInterval.value = 'monthly'
		}

		fetchChartData()
	}
)

// Watch for campaign changes
watch(
	() => props.campaignId,
	() => {
		fetchChartData()
	}
)

// Watch for job completions and refresh data
watch(
	() => jobStatusStore.completedJobs.length,
	(newCount, oldCount) => {
		if (newCount > oldCount) {
			fetchChartData()
		}
	}
)

// When visibility metrics refresh (e.g., while responses are processing), refresh chart
watch(
	() => organizationStore.visibilityMetrics,
	() => {
		fetchChartData()
	}
)

// Watch for defaultInterval changes and update selectedInterval
watch(
	() => props.defaultInterval,
	(newInterval) => {
		if (newInterval && newInterval !== selectedInterval.value) {
			selectedInterval.value = newInterval
			fetchChartData()
		}
	}
)

onMounted(() => {
	// Calculate interval based on date range, fallback to defaultInterval
	const calculatedInterval = calculateInterval(props.startDate, props.endDate)
	selectedInterval.value = calculatedInterval !== 'monthly' ? calculatedInterval : props.defaultInterval
	fetchChartData()
})

// Set unmounting flag before component is unmounted
onBeforeUnmount(() => {
	isUnmounting.value = true
})

// Clean up chart on unmount with error handling
onUnmounted(() => {
	try {
		// The isUnmounting flag ensures no new charts will be created during unmount
		safeDestroyChart()
	} catch (error) {
		console.error('Error destroying chart on unmount:', error)
	}
})
</script>
