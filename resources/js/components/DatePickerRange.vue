<script setup>
import { ref, computed, watch } from 'vue'
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

// Initialize current date to show the current month
const currentDate = ref(moment())

// Use local reactive state instead of computed properties
const localStartDate = ref(props.startDate ? moment(props.startDate) : null)
const localEndDate = ref(props.endDate ? moment(props.endDate) : null)

// Watch props and update local state
watch(
	() => props.startDate,
	(newStartDate) => {
		localStartDate.value = newStartDate ? moment(newStartDate) : null
	}
)

watch(
	() => props.endDate,
	(newEndDate) => {
		localEndDate.value = newEndDate ? moment(newEndDate) : null
	}
)

const isDateDisabled = (date) => {
	if (props.minDate && date.isBefore(moment(props.minDate))) return true
	if (props.maxDate && date.isAfter(moment(props.maxDate))) return true
	return false
}

// Extract calendar day generation to reduce duplication
const createCalendarDay = (date, monthDate) => {
	const isStart = localStartDate.value && date.isSame(localStartDate.value, 'day')
	const isEnd = localEndDate.value && date.isSame(localEndDate.value, 'day')
	const isInRange = localStartDate.value && localEndDate.value && date.isBetween(localStartDate.value, localEndDate.value, 'day', '[]')

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
		// Only include days that belong to the current month
		if (day.isSame(monthDate, 'month')) {
			days.push(createCalendarDay(day, monthDate))
		} else {
			// Add empty placeholder for days not in current month
			days.push(null)
		}
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
	if (!localStartDate.value || (localStartDate.value && localEndDate.value)) {
		localStartDate.value = day.date
		localEndDate.value = null
		emit('update:startDate', day.date.format('YYYY-MM-DD'))
		emit('update:endDate', null)
	}
	// If start date is set but no end date
	else if (localStartDate.value && !localEndDate.value) {
		// If clicked date is before start date, make it the new start date
		if (day.date.isBefore(localStartDate.value)) {
			localEndDate.value = localStartDate.value
			localStartDate.value = day.date
			emit('update:startDate', day.date.format('YYYY-MM-DD'))
			emit('update:endDate', localEndDate.value.format('YYYY-MM-DD'))
		} else {
			localEndDate.value = day.date
			emit('update:endDate', day.date.format('YYYY-MM-DD'))
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
	const baseClasses = 'p-4 mb-2 text-xs flex items-center justify-center transition-colors'

	const conditionalClasses = {
		'text-neutral-300': !day.isCurrentMonth,
		'bg-neutral-100': day.isToday && !day.isStart && !day.isEnd && !day.isInRange,
		'bg-blue-600 text-white': day.isStart || day.isEnd,
		'bg-blue-50 text-blue-600 font-semibold': day.isInRange && day.isCurrentMonth && !day.isStart && !day.isEnd,
		'hover:bg-neutral-100': !day.isStart && !day.isEnd && !day.isInRange && !day.isDisabled,
		'cursor-not-allowed opacity-50': day.isDisabled,
		'cursor-pointer': !day.isDisabled,
		'rounded-md': (day.isStart || day.isEnd) && day.isInRange
	}

	return [baseClasses, conditionalClasses]
}
</script>

<template>
	<div class="bg-white min-w-[640px]">
		<!-- Calendar Header -->
		<div class="flex items-center justify-between mb-4">
			<button @click="previousMonth" class="p-1 hover:bg-neutral-100 rounded cursor-pointer">
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

			<button @click="nextMonth" class="p-1 hover:bg-neutral-100 rounded cursor-pointer">
				<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
				</svg>
			</button>
		</div>

		<div class="flex gap-4">
			<!-- Left Month Calendar -->
			<div class="flex-1">
				<!-- Calendar Days Header -->
				<div class="grid grid-cols-7 mb-2">
					<div v-for="day in ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']" :key="day" class="text-xs text-neutral-500 text-center font-medium">
						{{ day }}
					</div>
				</div>

				<!-- Calendar Days -->
				<div class="grid grid-cols-7 gap-0">
					<template v-for="(day, index) in leftCalendarDays" :key="day ? day.date.format('YYYY-MM-DD') : `empty-left-${index}`">
						<button v-if="day" @click="selectDate(day)" :class="getDayClasses(day)" :disabled="day.isDisabled">
							{{ day.date.date() }}
						</button>
						<div v-else class="p-4 mb-2"></div>
					</template>
				</div>
			</div>

			<!-- Right Month Calendar -->
			<div class="flex-1">
				<!-- Calendar Days Header -->
				<div class="grid grid-cols-7 mb-2">
					<div v-for="day in ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']" :key="day" class="text-xs text-neutral-500 text-center font-medium">
						{{ day }}
					</div>
				</div>

				<!-- Calendar Days -->
				<div class="grid grid-cols-7 gap-0">
					<template v-for="(day, index) in rightCalendarDays" :key="day ? day.date.format('YYYY-MM-DD') : `empty-right-${index}`">
						<button v-if="day" @click="selectDate(day)" :class="getDayClasses(day)" :disabled="day.isDisabled">
							{{ day.date.date() }}
						</button>
						<div v-else class="p-4 mb-2"></div>
					</template>
				</div>
			</div>
		</div>
	</div>
</template>
