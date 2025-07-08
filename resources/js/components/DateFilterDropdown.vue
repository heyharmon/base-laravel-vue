<script setup>
import { computed, ref, watch, onMounted, nextTick } from 'vue'
import moment from 'moment'
import { useDateRangeUtils } from '@/composables/useDateRangeUtils'

import RangeCalendarPicker from '@/components/RangeCalendarPicker.vue'

const props = defineProps({
	startDate: {
		type: String,
		default: null
	},
	endDate: {
		type: String,
		default: null
	}
})

const emit = defineEmits(['dateRangeChanged'])

const { getDateRangeForTimeframe, detectTimeframe, formatDateRange } = useDateRangeUtils()

const isDropdownOpen = ref(false)
const selectedTimeframe = ref('last_30_days')

const timeframeOptions = [
	{ value: 'today', label: 'Today' },
	{ value: 'yesterday', label: 'Yesterday' },
	{ value: 'last_7_days', label: 'Last 7 days' },
	{ value: 'last_30_days', label: 'Last 30 days' },
	{ value: 'this_year', label: 'This year' },
	{ value: 'last_year', label: 'Last year' },
	{ value: 'all_time', label: 'All time' }
]

const customStartDate = ref(props.startDate)
const customEndDate = ref(props.endDate)

const selectedTimeframeLabel = computed(() => {
	if (selectedTimeframe.value === 'custom' && customStartDate.value && customEndDate.value) {
		return formatDateRange(customStartDate.value, customEndDate.value)
	}
	const option = timeframeOptions.find((opt) => opt.value === selectedTimeframe.value)
	return option ? option.label : 'Select timeframe'
})

const selectTimeframe = (value) => {
	selectedTimeframe.value = value

	if (value !== 'custom') {
		const range = getDateRangeForTimeframe(value)
		customStartDate.value = range.startDate
		customEndDate.value = range.endDate
		emit('dateRangeChanged', range)
	}

	isDropdownOpen.value = false
}

const updateCustomDate = (startDate, endDate) => {
	customStartDate.value = startDate
	customEndDate.value = endDate
	selectedTimeframe.value = 'custom'

	if (startDate && endDate) {
		emit('dateRangeChanged', { startDate, endDate })
	}
}

onMounted(() => {
	const availableTimeframes = timeframeOptions.map(opt => opt.value)
	selectedTimeframe.value = detectTimeframe(props.startDate, props.endDate, availableTimeframes) || 'last_30_days'

	if (!customStartDate.value || !customEndDate.value) {
		const range = getDateRangeForTimeframe(selectedTimeframe.value)
		customStartDate.value = range.startDate
		customEndDate.value = range.endDate
	}

	emit('dateRangeChanged', {
		startDate: customStartDate.value,
		endDate: customEndDate.value
	})
})

watch([() => props.startDate, () => props.endDate], ([newStart, newEnd]) => {
	if (newStart !== customStartDate.value || newEnd !== customEndDate.value) {
		customStartDate.value = newStart
		customEndDate.value = newEnd
		const availableTimeframes = timeframeOptions.map(opt => opt.value)
		selectedTimeframe.value = detectTimeframe(newStart, newEnd, availableTimeframes)
	}
})
</script>

<template>
	<div class="relative">
		<!-- Dropdown Trigger -->
		<button
			@click="isDropdownOpen = !isDropdownOpen"
			class="flex items-center justify-between w-full max-w-xs px-4 py-2 text-sm font-medium text-neutral-700 bg-white border border-neutral-300 rounded-lg hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
		>
			<span class="flex items-center gap-2">
				<svg class="w-4 h-4 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path
						stroke-linecap="round"
						stroke-linejoin="round"
						stroke-width="2"
						d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2z"
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
								'bg-blue-50 text-blue-700 border-blue-200': selectedTimeframe === option.value,
								'text-neutral-700 hover:bg-neutral-50': selectedTimeframe !== option.value
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
						@update:start-date="(date) => updateCustomDate(date, customEndDate)"
						@update:end-date="(date) => updateCustomDate(customStartDate, date)"
					/>
				</div>
			</div>
		</div>

		<!-- Backdrop -->
		<div v-if="isDropdownOpen" @click="isDropdownOpen = false" class="fixed inset-0 z-40"></div>
	</div>
</template>
