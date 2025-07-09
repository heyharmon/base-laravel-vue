<script setup>
import { ref, computed } from 'vue'
import moment from 'moment'

const props = defineProps({
	modelValue: {
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

const emit = defineEmits(['update:modelValue'])

const currentDate = ref(moment())
const selectedDate = computed({
	get: () => (props.modelValue ? moment(props.modelValue) : null),
	set: (value) => {
		emit('update:modelValue', value ? value.format('YYYY-MM-DD') : null)
	}
})

const calendarDays = computed(() => {
	const start = currentDate.value.clone().startOf('month').startOf('week')
	const end = currentDate.value.clone().endOf('month').endOf('week')
	const days = []

	let day = start.clone()
	while (day.isSameOrBefore(end, 'day')) {
		days.push({
			date: day.clone(),
			isCurrentMonth: day.isSame(currentDate.value, 'month'),
			isToday: day.isSame(moment(), 'day'),
			isSelected: selectedDate.value && day.isSame(selectedDate.value, 'day'),
			isDisabled: isDateDisabled(day)
		})
		day.add(1, 'day')
	}

	return days
})

const isDateDisabled = (date) => {
	if (props.minDate && date.isBefore(moment(props.minDate))) return true
	if (props.maxDate && date.isAfter(moment(props.maxDate))) return true
	return false
}

const selectDate = (day) => {
	if (day.isDisabled) return
	selectedDate.value = day.date
}

const previousMonth = () => {
	currentDate.value = currentDate.value.clone().subtract(1, 'month')
}

const nextMonth = () => {
	currentDate.value = currentDate.value.clone().add(1, 'month')
}

// Initialize current date to selected date if available
if (selectedDate.value) {
	currentDate.value = selectedDate.value.clone()
}
</script>

<template>
	<div class="bg-white border border-neutral-200 rounded-lg p-4 min-w-[280px]">
		<!-- Calendar Header -->
		<div class="flex items-center justify-between mb-4">
			<button @click="previousMonth" class="p-1 hover:bg-neutral-100 rounded">
				<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
				</svg>
			</button>

			<h3 class="text-sm font-medium">
				{{ currentDate.format('MMMM YYYY') }}
			</h3>

			<button @click="nextMonth" class="p-1 hover:bg-neutral-100 rounded">
				<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
				</svg>
			</button>
		</div>

		<!-- Calendar Days Header -->
		<div class="grid grid-cols-7 gap-1 mb-2">
			<div v-for="day in ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']" :key="day" class="text-xs text-neutral-500 text-center p-2 font-medium">
				{{ day }}
			</div>
		</div>

		<!-- Calendar Days -->
		<div class="grid grid-cols-7 gap-1">
			<button
				v-for="day in calendarDays"
				:key="day.date.format('YYYY-MM-DD')"
				@click="selectDate(day)"
				:class="{
					'text-neutral-300': !day.isCurrentMonth,
					'bg-neutral-100': day.isToday && !day.isSelected,
					'bg-blue-600 text-white': day.isSelected,
					'hover:bg-neutral-100': !day.isSelected && !day.isDisabled,
					'cursor-not-allowed opacity-50': day.isDisabled,
					'cursor-pointer': !day.isDisabled
				}"
				class="w-8 h-8 text-xs rounded flex items-center justify-center"
				:disabled="day.isDisabled"
			>
				{{ day.date.date() }}
			</button>
		</div>
	</div>
</template>
