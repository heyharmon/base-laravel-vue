<script setup>
import { computed } from 'vue'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import SpinnerIcon from '@/components/icons/SpinnerIcon.vue'

const props = defineProps({
        filterClass: {
                type: String,
                default: null
        },
        filterTrackableType: {
                type: String,
                default: null
        },
        filterTrackableId: {
                type: Number,
                default: null
        },
        showDetails: {
                type: Boolean,
                default: true
        },
        maxItems: {
                type: Number,
                default: 3
        }
})

const jobStatusStore = useJobStatusStore()

const activeJobs = computed(() => {
        let jobs = jobStatusStore.activeJobs || []

        if (props.filterClass) {
                jobs = jobs.filter((job) => job.job_class.includes(props.filterClass))
        }

        if (props.filterTrackableType && props.filterTrackableId) {
                jobs = jobs.filter((job) => job.trackable_type === props.filterTrackableType && job.trackable_id === props.filterTrackableId)
        }

        return jobs
})

const groupedJobs = computed(() => {
        const grouped = {}
        activeJobs.value.forEach((job) => {
                const jobClass = job.job_class.split('\\').pop().replace(/Job$/, '')
                if (!grouped[jobClass]) {
                        grouped[jobClass] = []
                }
                grouped[jobClass].push(job)
        })
        return grouped
})

const totalActiveJobs = computed(() => activeJobs.value.length)
</script>

<template>
        <div v-if="totalActiveJobs > 0" class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
                <div class="flex items-center gap-2">
                        <SpinnerIcon class="animate-spin h-4 w-4 text-green-700" />
                        <span class="font-medium">
                                {{ totalActiveJobs }} {{ totalActiveJobs === 1 ? 'job' : 'jobs' }} running
                        </span>
                </div>

                <div v-if="showDetails && Object.keys(groupedJobs).length > 0" class="mt-2 space-y-1">
                        <div v-for="(jobs, jobClass) in groupedJobs" :key="jobClass" class="text-sm">
                                <div v-for="(job, index) in jobs.slice(0, maxItems)" :key="job.job_id" class="pl-6">
                                        {{ job.output || `Running ${jobClass}...` }}
                                </div>
                                <div v-if="jobs.length > maxItems" class="pl-6 text-green-600">
                                        and {{ jobs.length - maxItems }} more...
                                </div>
                        </div>
                </div>
        </div>
</template>
