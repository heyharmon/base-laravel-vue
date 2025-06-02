<script setup>
import { ref } from 'vue'
import Sheet from '@/components/ui/Sheet.vue'
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

async function cancelJobs() {
        await jobStatusStore.cancelTeamJobs()
}
</script>

<template>
        <Sheet :is-open="isOpen" @close="closeSheet" position="right" title="Runs">
                <template #header-actions>
                        <button @click="cancelJobs" class="px-3 py-1 text-sm rounded bg-red-600 text-white hover:bg-red-700">
                                Cancel Jobs
                        </button>
                </template>
                <div class="w-full xl:w-[800px] md:p-4">
                        <JobStatusList :autoRefresh="true" :refreshInterval="1000" />
                </div>
        </Sheet>
</template>
