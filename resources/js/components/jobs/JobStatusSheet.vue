<script setup>
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import Sheet from '@/components/ui/Sheet.vue'
import Button from '@/components/ui/Button.vue'
import JobStatusList from '@/components/jobs/JobStatusList.vue'
import SpinnerIcon from '@/components/icons/SpinnerIcon.vue'
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
const route = useRoute()
const teamId = computed(() => route.params.teamId)
const isCancelling = ref(false)

async function cancelJobs() {
        if (!teamId.value) return
        isCancelling.value = true
        try {
                await jobStatusStore.cancelTeamJobs(teamId.value)
        } finally {
                isCancelling.value = false
        }
}
</script>

<template>
	<Sheet :is-open="isOpen" @close="closeSheet" position="right" title="Runs">
		<template #header-actions>
			<Button @click="cancelJobs" variant="destructive" size="sm" :disabled="isCancelling" class="flex items-center gap-1">
				<SpinnerIcon v-if="isCancelling" class="-ml-1 mr-1 h-4 w-4 text-white" />
				{{ isCancelling ? 'Cancelling...' : 'Cancel Jobs' }}
			</Button>
		</template>
		<div class="w-full xl:w-[800px] md:p-4">
			<JobStatusList :autoRefresh="true" :refreshInterval="1000" />
		</div>
	</Sheet>
</template>
