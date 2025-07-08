<script setup>
import { ref, computed } from 'vue'
import moment from 'moment'

const props = defineProps({
	startDate: {
		type: String,
		default: null
	},
	endDate: {
		type: String,
		default: null
	},
	minDate: {
		type: String,
		default: null
	},
	maxDate: {
		type: String,
		default: null
	}
})

const emit = defineEmits(['update:startDate', 'update:endDate'])

// Initialize current date properly in setup
const currentDate = ref(props.startDate ? moment(props.startDate) : moment())

const selectedStartDate = computed({
	get: () => (props.startDate ? moment(props.startDate) : null),
	set: (value) => {
		emit('update:startDate', value ? value.format('YYYY-MM-DD') : null)
	}
})

const selectedEndDate = computed({
	get: () => (props.endDate ? moment(props.endDate) : null),
	set: (value) => {
		emit('update:endDate', value ? value.format('YYYY-MM-DD') : null)
	}
})

const isDateDisabled = (date) => {
	if (props.minDate && date.isBefore(moment(props.minDate))) return true
	if (props.maxDate && date.isAfter(moment(props.maxDate))) return true
	return false
}

// Extract calendar day generation to reduce duplication
const createCalendarDay = (date, monthDate) => {
	const isStart = selectedStartDate.value && date.isSame(selectedStartDate.value, 'day')
	const isEnd = selectedEndDate.value && date.isSame(selectedEndDate.value, 'day')
	const isInRange = selectedStartDate.value && selectedEndDate.value && date.isBetween(selectedStartDate.value, selectedEndDate.value, 'day', '[]')

	return {
		date: date.clone(),
		isCurrentMonth: date.isSame(monthDate, 'month'),
		isToday: date.isSame(moment(), 'day'),
		isStart,
		isEnd,
		isInRange,
		isDisabled: isDateDisabled(date)
	}
}

// Generate calendar days for a specific month
const generateCalendarDays = (monthDate) => {
	const start = monthDate.clone().startOf('month').startOf('week')
	const end = monthDate.clone().endOf('month').endOf('week')
	const days = []

	let day = start.clone()
	while (day.isSameOrBefore(end, 'day')) {
		days.push(createCalendarDay(day, monthDate))
		day.add(1, 'day')
	}

	return days
}

// Calendar computeds
const previousMonthDate = computed(() => currentDate.value.clone().subtract(1, 'month'))
const leftCalendarDays = computed(() => generateCalendarDays(previousMonthDate.value))
const rightCalendarDays = computed(() => generateCalendarDays(currentDate.value))

const selectDate = (day) => {
	if (day.isDisabled) return

	// If no start date or both dates are set, start a new selection
	if (!selectedStartDate.value || (selectedStartDate.value && selectedEndDate.value)) {
		selectedStartDate.value = day.date
		selectedEndDate.value = null
	}
	// If start date is set but no end date
	else if (selectedStartDate.value && !selectedEndDate.value) {
		// If clicked date is before start date, make it the new start date
		if (day.date.isBefore(selectedStartDate.value)) {
			selectedEndDate.value = selectedStartDate.value
			selectedStartDate.value = day.date
		} else {
			selectedEndDate.value = day.date
		}
	}
}

const previousMonth = () => {
	currentDate.value = currentDate.value.clone().subtract(1, 'month')
}

const nextMonth = () => {
	currentDate.value = currentDate.value.clone().add(1, 'month')
}

// Shared day button classes
const getDayClasses = (day) => {
	const baseClasses = 'w-8 h-8 text-xs flex items-center justify-center transition-colors'

	const conditionalClasses = {
		'text-neutral-300': !day.isCurrentMonth,
		'bg-neutral-100': day.isToday && !day.isStart && !day.isEnd && !day.isInRange,
		'bg-blue-600 text-white': day.isStart || day.isEnd,
		'bg-blue-100 text-blue-800': day.isInRange && !day.isStart && !day.isEnd,
		'hover:bg-neutral-100': !day.isStart && !day.isEnd && !day.isInRange && !day.isDisabled,
		'cursor-not-allowed opacity-50': day.isDisabled,
		'cursor-pointer': !day.isDisabled,
		'rounded-l-md': day.isStart && day.isInRange,
		'rounded-r-md': day.isEnd && day.isInRange,
		'rounded-md': (day.isStart && !day.isInRange) || (day.isEnd && !day.isInRange)
	}

	return [baseClasses, conditionalClasses]
}
</script>

<template>
	<div class="bg-white border border-neutral-200 rounded-lg p-4 min-w-[640px]">
		<!-- Calendar Header -->
		<div class="flex items-center justify-between mb-4">
			<button @click="previousMonth" class="p-1 hover:bg-neutral-100 rounded">
				<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
				</svg>
			</button>

			<div class="flex gap-8">
				<h3 class="text-sm font-medium">
					{{ previousMonthDate.format('MMMM YYYY') }}
				</h3>
				<h3 class="text-sm font-medium">
					{{ currentDate.format('MMMM YYYY') }}
				</h3>
			</div>

			<button @click="nextMonth" class="p-1 hover:bg-neutral-100 rounded">
				<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
				</svg>
			</button>
		</div>

		<div class="flex gap-4">
			<!-- Left Month Calendar -->
			<div class="flex-1">
				<!-- Calendar Days Header -->
				<div class="grid grid-cols-7 gap-1 mb-2">
					<div v-for="day in ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']" :key="day" class="text-xs text-neutral-500 text-center p-2 font-medium">
						{{ day }}
					</div>
				</div>

				<!-- Calendar Days -->
				<div class="grid grid-cols-7 gap-1">
					<button
						v-for="day in leftCalendarDays"
						:key="day.date.format('YYYY-MM-DD')"
						@click="selectDate(day)"
						:class="getDayClasses(day)"
						:disabled="day.isDisabled"
					>
						{{ day.date.date() }}
					</button>
				</div>
			</div>

			<!-- Right Month Calendar -->
			<div class="flex-1">
				<!-- Calendar Days Header -->
				<div class="grid grid-cols-7 gap-1 mb-2">
					<div v-for="day in ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']" :key="day" class="text-xs text-neutral-500 text-center p-2 font-medium">
						{{ day }}
					</div>
				</div>

				<!-- Calendar Days -->
				<div class="grid grid-cols-7 gap-1">
					<button
						v-for="day in rightCalendarDays"
						:key="day.date.format('YYYY-MM-DD')"
						@click="selectDate(day)"
						:class="getDayClasses(day)"
						:disabled="day.isDisabled"
					>
						{{ day.date.date() }}
					</button>
				</div>
			</div>
		</div>

		<!-- Selected Range Display -->
		<div v-if="selectedStartDate || selectedEndDate" class="mt-4 pt-4 border-t border-neutral-200">
			<div class="text-xs text-neutral-600 space-y-1">
				<div v-if="selectedStartDate" class="flex justify-between">
					<span>Start:</span>
					<span class="font-medium">{{ selectedStartDate.format('MMM D, YYYY') }}</span>
				</div>
				<div v-if="selectedEndDate" class="flex justify-between">
					<span>End:</span>
					<span class="font-medium">{{ selectedEndDate.format('MMM D, YYYY') }}</span>
				</div>
				<div v-if="!selectedEndDate && selectedStartDate" class="text-neutral-400 text-center">Select end date</div>
			</div>
		</div>
	</div>
</template>
