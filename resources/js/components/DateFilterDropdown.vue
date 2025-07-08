<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import moment from 'moment'
import RangeCalendarPicker from '@/components/RangeCalendarPicker.vue'

const props = defineProps({
	selectedTimeframe: {
		type: String,
		default: 'this_month'
	},
	customStartDate: {
		type: String,
		default: null
	},
	customEndDate: {
		type: String,
		default: null
	}
})

const emit = defineEmits(['update:selectedTimeframe', 'update:customStartDate', 'update:customEndDate', 'dateRangeChanged'])

const isDropdownOpen = ref(false)
const isUpdatingProgrammatically = ref(false)

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
const getDateRangeForTimeframe = (timeframe) => {
	const now = moment()

	switch (timeframe) {
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
				startDate: props.customStartDate,
				endDate: props.customEndDate
			}
		default:
			return {
				startDate: null,
				endDate: null
			}
	}
}

const dateRange = computed(() => getDateRangeForTimeframe(props.selectedTimeframe))

// Get selected timeframe label
const selectedTimeframeLabel = computed(() => {
	if (props.selectedTimeframe === 'custom' && props.customStartDate && props.customEndDate) {
		return `${moment(props.customStartDate).format('MMM D, YYYY')} - ${moment(props.customEndDate).format('MMM D, YYYY')}`
	}
	const option = timeframeOptions.find((opt) => opt.value === props.selectedTimeframe)
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
	isUpdatingProgrammatically.value = true

	emit('update:selectedTimeframe', value)

	if (value !== 'custom') {
		const range = getDateRangeForTimeframe(value)
		emit('update:customStartDate', range.startDate)
		emit('update:customEndDate', range.endDate)
		emit('dateRangeChanged', range)
	}

	isDropdownOpen.value = false

	// Reset the flag after all updates are done
	nextTick(() => {
		isUpdatingProgrammatically.value = false
	})
}

// Handle custom date updates from calendar picker
const updateCustomStartDate = (value) => {
	emit('update:customStartDate', value)

	// Only set to custom if this is a manual user interaction (not programmatic)
	if (!isUpdatingProgrammatically.value && value && props.customEndDate) {
		emit('update:selectedTimeframe', 'custom')
	}
}

const updateCustomEndDate = (value) => {
	emit('update:customEndDate', value)

	// Only set to custom if this is a manual user interaction (not programmatic)
	if (!isUpdatingProgrammatically.value && value && props.customStartDate) {
		emit('update:selectedTimeframe', 'custom')
	}
}

// Watch for timeframe changes to update dates when needed
watch(
	() => props.selectedTimeframe,
	(newTimeframe) => {
		if (newTimeframe !== 'custom' && !isUpdatingProgrammatically.value) {
			isUpdatingProgrammatically.value = true
			const range = getDateRangeForTimeframe(newTimeframe)
			emit('update:customStartDate', range.startDate)
			emit('update:customEndDate', range.endDate)
			emit('dateRangeChanged', range)
			nextTick(() => {
				isUpdatingProgrammatically.value = false
			})
		}
	}
)

// Watch for custom date changes to emit date range changes
watch([() => props.customStartDate, () => props.customEndDate], () => {
	if (props.selectedTimeframe === 'custom' && props.customStartDate && props.customEndDate) {
		emit('dateRangeChanged', {
			startDate: props.customStartDate,
			endDate: props.customEndDate
		})
	}
})

// Import nextTick
import { nextTick } from 'vue'

// Initialize on mount
onMounted(() => {
	// Only emit if we need to sync the initial state
	if (props.selectedTimeframe !== 'custom' && (!props.customStartDate || !props.customEndDate)) {
		const range = getDateRangeForTimeframe(props.selectedTimeframe)
		emit('update:customStartDate', range.startDate)
		emit('update:customEndDate', range.endDate)
	}
	// Always emit the current date range for data fetching
	emit('dateRangeChanged', dateRange.value)
})
</script>

<template>
	<div class="relative">
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
		<div v-if="isDropdownOpen" @click.stop class="absolute top-full left-0 mt-1 bg-white border border-neutral-200 rounded-lg shadow-lg z-50 min-w-[900px]">
			<div class="flex">
				<!-- Left Column - Timeframe Options -->
				<div class="w-48 p-4 border-r border-neutral-200">
					<div class="space-y-1">
						<button
							v-for="option in timeframeOptions"
							:key="option.value"
							@click="selectTimeframe(option.value)"
							:class="{
								'bg-blue-50 text-blue-700 border-blue-200': props.selectedTimeframe === option.value,
								'text-neutral-700 hover:bg-neutral-50': props.selectedTimeframe !== option.value
							}"
							class="w-full text-left px-3 py-2 text-sm rounded-md border border-transparent transition-colors"
						>
							{{ option.label }}
						</button>
					</div>
				</div>

				<!-- Right Column - Calendar Picker -->
				<div class="flex-1 p-4 flex justify-center">
					<RangeCalendarPicker
						:start-date="customStartDate"
						:end-date="customEndDate"
						:max-date="moment().format('YYYY-MM-DD')"
						@update:start-date="updateCustomStartDate"
						@update:end-date="updateCustomEndDate"
					/>
				</div>
			</div>
		</div>

		<!-- Backdrop to close dropdown -->
		<div v-if="isDropdownOpen" @click="closeDropdown" class="fixed inset-0 z-40"></div>
	</div>
</template>
