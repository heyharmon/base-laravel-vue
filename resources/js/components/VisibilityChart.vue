<template>
	<div class="bg-white rounded-lg p-6 border border-neutral-200 shadow-sm">
		<div class="flex items-center justify-between mb-4">
			<div class="flex items-center gap-2">
				<h2 class="text-xl font-bold">Visibility Over Time</h2>
				<div v-if="isLoading" class="animate-spin rounded-full size-4 border-b-2 border-neutral-800"></div>
			</div>

			<!-- Interval selector -->
			<div class="relative">
				<button
					@click="isDropdownOpen = !isDropdownOpen"
					class="flex items-center justify-between gap-2 text-sm border border-neutral-200 rounded-md px-3 py-1.5 bg-white hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
				>
					<span>{{ selectedIntervalLabel }}</span>
					<ChevronDownIcon class="text-neutral-500 transition-transform" :class="{ 'rotate-180': isDropdownOpen }" />
				</button>

				<!-- Dropdown Content -->
				<div v-if="isDropdownOpen" class="absolute top-full right-0 mt-1 bg-white border border-neutral-200 rounded-md shadow-lg z-50 min-w-[120px]">
					<button
						v-for="option in intervalOptions"
						:key="option.value"
						@click="selectInterval(option.value)"
						:class="{
							'bg-blue-50 text-blue-700': selectedInterval === option.value,
							'text-neutral-700 hover:bg-neutral-50': selectedInterval !== option.value
						}"
						class="w-full text-left px-3 py-2 text-sm transition-colors cursor-pointer first:rounded-t-md last:rounded-b-md"
					>
						{{ option.label }}
					</button>
				</div>

				<!-- Backdrop -->
				<div v-if="isDropdownOpen" @click="isDropdownOpen = false" class="fixed inset-0 z-40"></div>
			</div>
		</div>

		<div class="relative" style="height: 400px">
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
import ApexCharts from 'apexcharts'
import api from '@/services/api'
import ChevronDownIcon from '@/components/icons/ChevronDownIcon.vue'

const props = defineProps({
	startDate: String,
	endDate: String,
	teamId: String,
	campaignId: String,
	defaultInterval: {
		type: String,
		default: 'monthly'
	}
})

const organizationStore = useOrganizationStore()

const chartContainer = ref(null)
const chart = ref(null)
const isLoading = ref(false)
const chartData = ref([])
const selectedInterval = ref('monthly')
const isDropdownOpen = ref(false)

const intervalOptions = [
	{ value: 'daily', label: 'Daily' },
	{ value: 'weekly', label: 'Weekly' },
	{ value: 'monthly', label: 'Monthly' }
]

const selectedIntervalLabel = computed(() => {
	const option = intervalOptions.find((opt) => opt.value === selectedInterval.value)
	return option ? option.label : 'Monthly'
})

const selectInterval = (value) => {
	selectedInterval.value = value
	isDropdownOpen.value = false
	fetchChartData()
}

// Keep track of the latest request ID
let latestRequestId = 0

const fetchChartData = async () => {
	// Don't fetch if we're unmounting or missing required props
	if (isUnmounting.value || !props.teamId || !props.campaignId) return

	isLoading.value = true

	try {
		// Prevent multiple simultaneous requests with a more robust ID system
		const currentRequestId = Date.now()
		latestRequestId = currentRequestId

		const params = new URLSearchParams({
			start_date: props.startDate,
			end_date: props.endDate,
			interval: selectedInterval.value
		})

		// Always include the owned organization
		const ownedOrg = organizationStore.visibilityMetrics.find((org) => !org.is_competitor)
		if (ownedOrg) {
			params.append('organization_ids[]', ownedOrg.id)
		}

		const response = await api.get(`/teams/${props.teamId}/campaigns/${props.campaignId}/organization-visibility/chart?${params}`)

		// Check if a newer request has come in while we were waiting or if unmounting
		if (currentRequestId !== latestRequestId || isUnmounting.value) {
			return // Abandon this update as it's stale or component is unmounting
		}

		chartData.value = response.organizations || []

		// If component is unmounting, don't proceed with chart update
		if (isUnmounting.value) return

		// Ensure DOM is updated before updating chart
		await nextTick()

		// Final check before updating chart
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

			// Format the data for ApexCharts
			const series = chartData.value.map((org) => ({
				name: org.name,
				data: org.data?.map((point) => point.visibility) || []
			}))

			// Get categories (dates) from the first organization's data points
			const categories = chartData.value.length > 0 && chartData.value[0].data ? chartData.value[0].data.map((point) => point.date) : []

			// Prepare colors array
			const colors = chartData.value.map((org) => org.color)

			// Create ApexCharts options
			const options = {
				chart: {
					type: 'line',
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
				colors: colors,
				series: series,
				dataLabels: {
					enabled: false
				},
				stroke: {
					curve: 'smooth',
					width: 2
				},
				xaxis: {
					categories: categories,
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
				tooltip: {
					shared: true,
					intersect: false,
					y: {
						formatter: function (value) {
							return value + '%'
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
						radius: 100
					}
				},
				grid: {
					borderColor: '#e0e0e0',
					row: {
						colors: ['#f3f3f3', 'transparent'],
						opacity: 0.5
					}
				},
				markers: {
					size: 4,
					hover: {
						size: 6
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

// Watch for date changes
watch(
	() => [props.startDate, props.endDate],
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
	// Initialize selectedInterval with defaultInterval prop
	selectedInterval.value = props.defaultInterval
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
