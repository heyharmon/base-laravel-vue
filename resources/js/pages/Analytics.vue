<script setup>
import { ref, onMounted, computed } from 'vue'
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend } from 'chart.js'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import api from '@/services/api.js'

// Register Chart.js components
ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend)

// State
const terms = ref([])
const selectedTerm = ref(null)
const selectedPeriod = ref('all')
const timeSeriesData = ref(null)
const isLoading = ref(false)
const isTimeSeriesLoading = ref(false)

// Periods for filtering
const periods = [
	{ value: 'all', label: 'All Time' },
	{ value: 'last28days', label: 'Last 28 Days' },
	{ value: 'last7days', label: 'Last 7 Days' },
	{ value: 'yesterday', label: 'Yesterday' }
]

// Chart options
const chartOptions = {
	responsive: true,
	maintainAspectRatio: false,
	scales: {
		y: {
			beginAtZero: true,
			ticks: {
				precision: 0
			}
		}
	}
}

// Computed chart data
const chartData = computed(() => {
	if (!timeSeriesData.value) return null

	return {
		labels: timeSeriesData.value.labels,
		datasets: [
			{
				label: timeSeriesData.value.name,
				backgroundColor: 'rgba(75, 85, 99, 0.2)',
				borderColor: 'rgb(75, 85, 99)',
				data: timeSeriesData.value.data,
				tension: 0.2
			}
		]
	}
})

// Methods
const fetchTerms = async () => {
	isLoading.value = true
	try {
		terms.value = await api.get(`/analytics/terms?period=${selectedPeriod.value}`)
	} catch (error) {
		console.error('Error fetching terms:', error)
	} finally {
		isLoading.value = false
	}
}

const fetchTimeSeriesData = async (termId = null, days = 30) => {
	isTimeSeriesLoading.value = true
	try {
		let url = `/analytics/timeseries?days=${days}`
		if (termId) url += `&term_id=${termId}`

		timeSeriesData.value = await api.get(url)
	} catch (error) {
		console.error('Error fetching time series data:', error)
	} finally {
		isTimeSeriesLoading.value = false
	}
}

const selectTerm = async (term) => {
	selectedTerm.value = term
	await fetchTermDetails(term.id)
	await fetchTimeSeriesData(term.id)
}

const fetchTermDetails = async (termId) => {
	try {
		// Update the selected term with detailed data
		selectedTerm.value = await api.get(`/analytics/terms?term_id=${termId}&period=${selectedPeriod.value}`)
	} catch (error) {
		console.error('Error fetching term details:', error)
	}
}

const changePeriod = async (period) => {
	selectedPeriod.value = period
	await fetchTerms()

	if (selectedTerm.value) {
		await fetchTermDetails(selectedTerm.value.id)
		await fetchTimeSeriesData(selectedTerm.value.id)
	} else {
		await fetchTimeSeriesData()
	}
}

// Lifecycle hooks
onMounted(async () => {
	await fetchTerms()
	await fetchTimeSeriesData()
})
</script>

<template>
	<DefaultLayout>
		<div class="py-6">
			<div class="flex justify-between items-center mb-6">
				<h1 class="text-2xl font-semibold">Term Analytics</h1>

				<div class="flex space-x-2">
					<button
						v-for="period in periods"
						:key="period.value"
						@click="changePeriod(period.value)"
						class="px-3 py-1.5 text-sm rounded-md transition-colors"
						:class="selectedPeriod === period.value ? 'bg-neutral-800 text-white' : 'bg-neutral-100 text-neutral-800 hover:bg-neutral-200'"
					>
						{{ period.label }}
					</button>
				</div>
			</div>

			<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
				<!-- Terms List -->
				<div class="lg:col-span-1 bg-neutral-50 rounded-lg p-4 border border-neutral-200">
					<h2 class="text-lg font-medium mb-4">Terms</h2>

					<div v-if="isLoading" class="flex justify-center py-8">
						<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
					</div>

					<div v-else-if="terms.length === 0" class="text-center py-8 text-neutral-500">No terms found</div>

					<div v-else class="space-y-3 max-h-[500px] overflow-y-auto">
						<div
							v-for="term in terms"
							:key="term.id"
							@click="selectTerm(term)"
							class="p-3 border border-neutral-300 hover:border-neutral-400 hover:bg-white rounded-lg cursor-pointer transition-colors"
							:class="{ 'border-2 border-neutral-400 bg-white': selectedTerm?.id === term.id }"
						>
							<div class="font-medium">{{ term.name }}</div>
							<div class="text-sm text-neutral-500 mt-1">{{ term.total_occurrences }} occurrences in {{ term.prompt_count }} prompts</div>
						</div>
					</div>
				</div>

				<!-- Time Series Chart -->
				<div class="lg:col-span-2 bg-neutral-50 rounded-lg p-4 border border-neutral-200">
					<h2 class="text-lg font-medium mb-4">
						{{ selectedTerm ? `Trend for "${selectedTerm.term || selectedTerm.name}"` : 'Overall Trends' }}
					</h2>

					<div v-if="isTimeSeriesLoading" class="flex justify-center py-8">
						<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
					</div>

					<div v-else-if="!chartData" class="text-center py-8 text-neutral-500">No time series data available</div>

					<div v-else class="h-[300px]">
						<Line :data="chartData" :options="chartOptions" />
					</div>
				</div>
			</div>

			<!-- Term Details -->
			<div v-if="selectedTerm && selectedTerm.prompts" class="mt-6 bg-neutral-50 rounded-lg p-4 border border-neutral-200">
				<h2 class="text-lg font-medium mb-4">Details for "{{ selectedTerm.term }}"</h2>

				<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
					<div class="bg-white p-4 rounded-lg border border-neutral-200">
						<div class="text-sm text-neutral-500">Total Occurrences</div>
						<div class="text-2xl font-semibold">{{ selectedTerm.total_occurrences }}</div>
					</div>

					<div class="bg-white p-4 rounded-lg border border-neutral-200">
						<div class="text-sm text-neutral-500">Prompts</div>
						<div class="text-2xl font-semibold">{{ selectedTerm.prompt_count }}</div>
					</div>

					<div v-if="selectedTerm.period_occurrences !== undefined" class="bg-white p-4 rounded-lg border border-neutral-200">
						<div class="text-sm text-neutral-500">Period Occurrences</div>
						<div class="text-2xl font-semibold">{{ selectedTerm.period_occurrences }}</div>
					</div>
				</div>

				<h3 class="text-md font-medium mb-3">Prompts containing this term</h3>
				<div class="overflow-x-auto">
					<table class="min-w-full bg-white border border-neutral-200 rounded-lg">
						<thead>
							<tr class="bg-neutral-100">
								<th class="py-2 px-4 text-left text-sm font-medium text-neutral-700">Prompt</th>
								<th class="py-2 px-4 text-left text-sm font-medium text-neutral-700">Occurrences</th>
								<th class="py-2 px-4 text-left text-sm font-medium text-neutral-700">Last Found</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="prompt in selectedTerm.prompts" :key="prompt.id" class="border-t border-neutral-200">
								<td class="py-2 px-4 text-sm max-w-xs truncate">{{ prompt.content }}</td>
								<td class="py-2 px-4 text-sm">{{ prompt.count }}</td>
								<td class="py-2 px-4 text-sm">{{ new Date(prompt.last_found_at).toLocaleDateString() }}</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
