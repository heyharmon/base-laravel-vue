<template>
	<div class="bg-white rounded-lg p-6 border border-neutral-200 shadow-sm">
		<div class="flex items-center justify-between mb-4">
			<div class="flex items-center gap-2">
				<h2 class="text-xl font-bold">Visibility Over Time</h2>
				<div v-if="isLoading" class="animate-spin rounded-full size-4 border-b-2 border-neutral-800"></div>
			</div>

			<div class="flex items-center gap-4">
				<!-- Interval selector -->
				<select v-model="selectedInterval" @change="fetchChartData" class="text-sm border border-neutral-200 rounded-md px-3 py-1.5">
					<option value="daily">Daily</option>
					<option value="weekly">Weekly</option>
					<option value="monthly">Monthly</option>
				</select>

				<!-- Competitor selector -->
				<div class="relative">
					<button
						@click="showCompetitorDropdown = !showCompetitorDropdown"
						class="text-sm border border-neutral-200 rounded-md px-3 py-1.5 flex items-center gap-2 hover:bg-neutral-50"
					>
						<span>Compare with competitors</span>
						<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
						</svg>
					</button>

					<div
						v-if="showCompetitorDropdown"
						v-click-outside="() => (showCompetitorDropdown = false)"
						class="absolute right-0 mt-2 w-64 bg-white border border-neutral-200 rounded-lg shadow-lg z-10 max-h-64 overflow-y-auto"
					>
						<div class="p-2">
							<div v-for="org in availableCompetitors" :key="org.id" class="flex items-center gap-2 p-2 hover:bg-neutral-50 rounded">
								<input
									type="checkbox"
									:id="`competitor-${org.id}`"
									v-model="selectedCompetitors"
									:value="org.id"
									@change="fetchChartData"
									class="rounded border-neutral-300"
								/>
								<label :for="`competitor-${org.id}`" class="flex items-center gap-2 cursor-pointer flex-1">
									<img
										:src="`https://cdn.brandfetch.io/${org.website}/w/400/h/400?c=1idaplhOcH8x9kYGESa`"
										:alt="org.name + ' logo'"
										class="size-5 object-contain bg-white rounded border border-neutral-200"
									/>
									<span class="text-sm">{{ org.name || 'Unnamed Competitor' }}</span>
								</label>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="relative" style="height: 400px">
			<div ref="chartContainer"></div>
		</div>

		<div v-if="!isLoading && chartData.length === 0" class="text-center py-8 text-neutral-500">No data available for the selected date range</div>
	</div>
</template>

<script setup>
import { ref, onMounted, watch, computed, nextTick, onUnmounted, onBeforeUnmount } from 'vue'
import { useOrganizationStore } from '@/stores/organizationStore'
import ApexCharts from 'apexcharts'
import api from '@/services/api'

const props = defineProps({
	startDate: String,
	endDate: String
})

const organizationStore = useOrganizationStore()

const chartContainer = ref(null)
const chart = ref(null)
const isLoading = ref(false)
const chartData = ref([])
const selectedInterval = ref('daily')
const selectedCompetitors = ref([])
const showCompetitorDropdown = ref(false)

const availableCompetitors = computed(() => {
	return organizationStore.visibilityMetrics.filter((org) => org.is_competitor)
})

// Click outside directive
const vClickOutside = {
	mounted(el, binding) {
		el.clickOutsideEvent = function (event) {
			if (!(el === event.target || el.contains(event.target))) {
				binding.value()
			}
		}
		document.addEventListener('click', el.clickOutsideEvent)
	},
	unmounted(el) {
		document.removeEventListener('click', el.clickOutsideEvent)
	}
}

// Keep track of the latest request ID
let latestRequestId = 0

const fetchChartData = async () => {
	// Don't fetch if we're unmounting
	if (isUnmounting.value) return
	
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

		// Add selected organizations
		const orgIds = [...selectedCompetitors.value]

		// Always include the owned organization
		const ownedOrg = organizationStore.visibilityMetrics.find((org) => !org.is_competitor)
		if (ownedOrg && !orgIds.includes(ownedOrg.id)) {
			orgIds.push(ownedOrg.id)
		}

		orgIds.forEach((id) => {
			params.append('organization_ids[]', id)
		})

		const response = await api.get(`/organization-visibility/chart?${params}`)
		
		// Check if a newer request has come in while we were waiting or if unmounting
		if (currentRequestId !== latestRequestId || isUnmounting.value) {
			return // Abandon this update as it's stale or component is unmounting
		}
		
		chartData.value = response.organizations

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
			
			// Format the data for ApexCharts
			const series = chartData.value.map((org) => ({
				name: org.name,
				data: org.data.map((point) => point.visibility)
			}))

			// Get categories (dates) from the first organization's data points
			const categories = chartData.value.length > 0
				? chartData.value[0].data.map((point) => point.date)
				: []

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
							return value + '%';
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
					custom: function({ series, seriesIndex, dataPointIndex, w }) {
						const orgData = chartData.value[seriesIndex];
						const point = orgData.data[dataPointIndex];
						
						return `
						<div class="p-2 bg-white border border-neutral-200 rounded shadow">
							<div class="font-bold">${orgData.name}</div>
							<div>Visibility: ${point.visibility}%</div>
							<div>Mentions: ${point.mentions}</div>
							<div>Total Responses: ${point.responses}</div>
						</div>
						`;
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
			};

			// Create new chart with robust error handling
			try {
				// Final check before creating chart
				if (!isUnmounting.value && chartContainer.value) {
					chart.value = new ApexCharts(chartContainer.value, options);
					chart.value.render();
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

// Watch for visibility metrics updates
watch(
	() => organizationStore.visibilityMetrics,
	() => {
		// Reset selected competitors if they're no longer in the list
		selectedCompetitors.value = selectedCompetitors.value.filter((id) => availableCompetitors.value.some((org) => org.id === id))
	}
)

onMounted(() => {
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
