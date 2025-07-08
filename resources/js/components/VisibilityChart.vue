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
			<canvas ref="chartCanvas"></canvas>
		</div>

		<div v-if="!isLoading && chartData.length === 0" class="text-center py-8 text-neutral-500">No data available for the selected date range</div>
	</div>
</template>

<script setup>
import { ref, onMounted, watch, computed, nextTick, onUnmounted } from 'vue'
import { useOrganizationStore } from '@/stores/organizationStore'
import Chart from 'chart.js/auto'
import api from '@/services/api'

const props = defineProps({
	startDate: String,
	endDate: String
})

const organizationStore = useOrganizationStore()

const chartCanvas = ref(null)
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

const fetchChartData = async () => {
	isLoading.value = true

	try {
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
		chartData.value = response.organizations

		await nextTick()
		updateChart()
	} catch (error) {
		console.error('Error fetching chart data:', error)
	} finally {
		isLoading.value = false
	}
}

const updateChart = () => {
	if (!chartCanvas.value) return

	// Destroy existing chart
	if (chart.value) {
		chart.value.destroy()
	}

	// Prepare datasets
	const datasets = chartData.value.map((org) => ({
		label: org.name,
		data: org.data.map((point) => ({
			x: point.date,
			y: point.visibility
		})),
		borderColor: org.color,
		backgroundColor: org.color + '20', // Add transparency
		borderWidth: 2,
		tension: 0.1, // Smooth lines
		pointRadius: 4,
		pointHoverRadius: 6
	}))

	// Create new chart
	chart.value = new Chart(chartCanvas.value, {
		type: 'line',
		data: {
			datasets: datasets
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			interaction: {
				mode: 'index',
				intersect: false
			},
			plugins: {
				legend: {
					position: 'bottom',
					labels: {
						padding: 15,
						usePointStyle: true
					}
				},
				tooltip: {
					callbacks: {
						afterLabel: function (context) {
							const dataIndex = context.dataIndex
							const orgData = chartData.value[context.datasetIndex]
							const point = orgData.data[dataIndex]
							return [`Mentions: ${point.mentions}`, `Total Responses: ${point.responses}`]
						}
					}
				}
			},
			scales: {
				x: {
					type: 'category',
					grid: {
						display: false
					}
				},
				y: {
					beginAtZero: true,
					max: 100,
					ticks: {
						callback: function (value) {
							return value + '%'
						}
					},
					grid: {
						borderDash: [2, 2]
					}
				}
			}
		}
	})
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

// Clean up chart on unmount
onUnmounted(() => {
	if (chart.value) {
		chart.value.destroy()
	}
})
</script>
