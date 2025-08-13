<script setup>
import { computed, ref, watch, onMounted, nextTick } from 'vue'
import moment from 'moment'
import { useDateRangeUtils } from '@/composables/useDateRangeUtils'
import { useOrganizationStore } from '@/stores/organizationStore'
import { PopoverRoot, PopoverTrigger, PopoverContent, PopoverPortal, PopoverClose } from 'reka-ui'

import DatePickerRange from '@/components/DatePickerRange.vue'
import ChevronDownIcon from '@/components/icons/ChevronDownIcon.vue'

const props = defineProps({
	startDate: {
		type: String,
		default: null
	},
	endDate: {
		type: String,
		default: null
	},
	useSharedState: {
		type: Boolean,
		default: true
	}
})

const emit = defineEmits(['dateRangeChanged'])

const organizationStore = useOrganizationStore()
const { getDateRangeForTimeframe, detectTimeframe, formatDateRange } = useDateRangeUtils()

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

// Use shared state from organization store when useSharedState is true
const customStartDate = computed({
	get: () => (props.useSharedState ? organizationStore.currentDateRange.startDate : props.startDate),
	set: (value) => {
		if (props.useSharedState) {
			organizationStore.currentDateRange.startDate = value
		}
	}
})

const customEndDate = computed({
	get: () => (props.useSharedState ? organizationStore.currentDateRange.endDate : props.endDate),
	set: (value) => {
		if (props.useSharedState) {
			organizationStore.currentDateRange.endDate = value
		}
	}
})

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

		if (props.useSharedState) {
			organizationStore.currentDateRange.startDate = range.startDate
			organizationStore.currentDateRange.endDate = range.endDate
		}

		emit('dateRangeChanged', range)
	}
}

// Fixed: Handle date updates separately and more carefully
const handleStartDateUpdate = (startDate) => {
	if (props.useSharedState) {
		organizationStore.currentDateRange.startDate = startDate
	}
	selectedTimeframe.value = 'custom'

	// Emit when we have both dates
	if (startDate && customEndDate.value) {
		emit('dateRangeChanged', {
			startDate: startDate,
			endDate: customEndDate.value
		})
	}
}

const handleEndDateUpdate = (endDate) => {
	if (props.useSharedState) {
		organizationStore.currentDateRange.endDate = endDate
	}
	selectedTimeframe.value = 'custom'

	// Emit when we have both dates OR when we're completing a range selection
	if (customStartDate.value && endDate) {
		emit('dateRangeChanged', {
			startDate: customStartDate.value,
			endDate: endDate
		})
	}
	// Also emit when end date is cleared (null) so parent knows the state changed
	else if (customStartDate.value && endDate === null) {
		// Don't emit here as we're in the middle of selecting a new range
		// The parent will get the update when both dates are selected
	}
}

onMounted(() => {
	const availableTimeframes = timeframeOptions.map((opt) => opt.value)

	// Detect timeframe based on current values
	const startDate = props.useSharedState ? organizationStore.currentDateRange.startDate : props.startDate
	const endDate = props.useSharedState ? organizationStore.currentDateRange.endDate : props.endDate

	selectedTimeframe.value = detectTimeframe(startDate, endDate, availableTimeframes) || 'this_year'

	// Initialize dates if not set
	if (!customStartDate.value || !customEndDate.value) {
		const range = getDateRangeForTimeframe(selectedTimeframe.value)

		if (props.useSharedState) {
			organizationStore.currentDateRange.startDate = range.startDate
			organizationStore.currentDateRange.endDate = range.endDate
		}

		emit('dateRangeChanged', {
			startDate: range.startDate,
			endDate: range.endDate
		})
	} else {
		// Emit current values
		emit('dateRangeChanged', {
			startDate: customStartDate.value,
			endDate: customEndDate.value
		})
	}
})

// Watch for changes in shared state
watch(
	() => organizationStore.currentDateRange,
	(newRange) => {
		if (props.useSharedState) {
			const availableTimeframes = timeframeOptions.map((opt) => opt.value)
			selectedTimeframe.value = detectTimeframe(newRange.startDate, newRange.endDate, availableTimeframes) || 'custom'
		}
	},
	{ deep: true }
)

// Watch for prop changes when not using shared state
watch([() => props.startDate, () => props.endDate], ([newStart, newEnd]) => {
	if (!props.useSharedState) {
		const availableTimeframes = timeframeOptions.map((opt) => opt.value)
		selectedTimeframe.value = detectTimeframe(newStart, newEnd, availableTimeframes) || 'custom'
	}
})
</script>

<template>
	<PopoverRoot>
		<PopoverTrigger as-child>
			<div
				class="flex items-center justify-between min-w-[200px] space-x-2 cursor-pointer px-3 py-1.5 rounded-md bg-white shadow-xs border border-neutral-400/70 hover:bg-neutral-100 transition-all"
			>
				<div class="flex flex-col pr-0.5">
					<span class="text-xs text-neutral-500">Date range</span>
					<span class="text-sm font-medium text-neutral-700 -mt-0.5">{{ selectedTimeframeLabel }}</span>
				</div>
				<ChevronDownIcon class="text-neutral-600" />
			</div>
		</PopoverTrigger>
		<PopoverPortal>
			<PopoverContent
				class="p-0 bg-white rounded shadow-lg overflow-hidden border border-neutral-300 z-50 min-w-[900px]"
				side="bottom"
				align="end"
				:side-offset="5"
			>
				<div class="flex">
					<!-- Left Column - Timeframe Options -->
					<div class="w-48 p-4 border-r border-neutral-200">
						<div class="space-y-1">
							<PopoverClose as-child v-for="option in timeframeOptions" :key="option.value">
								<button
									@click="selectTimeframe(option.value)"
									:class="{
										'bg-blue-50 text-blue-700 border-blue-200': selectedTimeframe === option.value,
										'text-neutral-700 hover:bg-neutral-50': selectedTimeframe !== option.value
									}"
									class="w-full text-left px-3 py-2 text-sm rounded-md border border-transparent transition-colors cursor-pointer"
								>
									{{ option.label }}
								</button>
							</PopoverClose>
						</div>
					</div>

					<!-- Right Column - Calendar Picker -->
					<div class="flex-1 p-4 flex justify-center">
						<DatePickerRange
							:start-date="customStartDate"
							:end-date="customEndDate"
							:max-date="moment().format('YYYY-MM-DD')"
							@update:start-date="handleStartDateUpdate"
							@update:end-date="handleEndDateUpdate"
						/>
					</div>
				</div>
			</PopoverContent>
		</PopoverPortal>
	</PopoverRoot>
</template>
