<script setup>
import { ref } from 'vue'
import Sheet from '@/components/ui/Sheet.vue'
import Button from '@/components/ui/Button.vue'
import JobStatusList from '@/components/jobs/JobStatusList.vue'
import { useJobStatusStore } from '@/stores/jobStatusStore'

const props = defineProps({
	isOpen: {
		type: Boolean,
		required: true
	}
})

const emit = defineEmits(['close'])

const closeSheet = () => {
	emit('close')
}

const jobStatusStore = useJobStatusStore()
const isCancelling = ref(false)

async function cancelJobs() {
	isCancelling.value = true
	try {
		await jobStatusStore.cancelTeamJobs()
	} finally {
		isCancelling.value = false
	}
}
</script>

<template>
	<Sheet :is-open="isOpen" @close="closeSheet" position="right" title="Runs">
		<template #header-actions>
			<Button @click="cancelJobs" variant="destructive" size="sm" :disabled="isCancelling" class="flex items-center gap-1">
				<svg v-if="isCancelling" class="animate-spin -ml-1 mr-1 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
					<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
					<path
						class="opacity-75"
						fill="currentColor"
						d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
					></path>
				</svg>
				{{ isCancelling ? 'Cancelling...' : 'Cancel Jobs' }}
			</Button>
		</template>
		<div class="w-full xl:w-[800px] md:p-4">
			<JobStatusList :autoRefresh="true" :refreshInterval="1000" />
		</div>
	</Sheet>
</template>
