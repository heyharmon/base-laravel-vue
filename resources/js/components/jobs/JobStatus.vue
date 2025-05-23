<template>
    <div>
        <div v-if="error" class="bg-red-100 p-3 rounded text-red-700">
            {{ error }}
        </div>

        <div>
            <!-- No jobs -->
            <div v-if="jobs.length === 0" class="py-4 text-center text-neutral-500">
                No jobs found.
            </div>

            <!-- Job list grouped by batch -->
            <div v-else>
                <!-- Batched Jobs -->
                <div v-if="processedBatches.length > 0" class="space-y-4 mb-4">
                    <div
                        v-for="batch in processedBatches"
                        :key="batch.id"
                        class="border-2 border-neutral-300 rounded-lg overflow-hidden bg-neutral-50"
                    >
                        <div class="px-4 py-3 flex items-center justify-between bg-neutral-100 border-b">
                            <div>
                                <span class="font-medium">Batch Run ({{ batch.jobs.length }}x)</span>
                                <span class="ml-2 text-sm text-neutral-500">{{ formatDate(batch.created_at) }}</span>
                            </div>
                            <div class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full">
                                {{ getBatchStatus(batch) }}
                            </div>
                        </div>

                        <div class="divide-y divide-neutral-200">
                            <div
                                v-for="job in batch.jobs"
                                :key="job.job_id"
                                class="px-4 py-3"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-sm">{{ getJobName(job) }}</span>
                                        <span class="px-2 py-0.5 text-xs rounded-full" :class="getStatusClass(job.status)">{{ job.status }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-neutral-500">
										<div v-if="job.status === 'processing'" class="spinner"></div>
                                        <span v-else>{{ formatTime(job.created_at) }}</span>
                                    </div>
                                </div>

                                <!-- Progress bar -->
                                <div v-if="job.status === 'processing'">
                                    <div class="h-1.5 bg-neutral-200 rounded-full overflow-hidden">
                                        <div
                                            class="h-1.5 bg-blue-500 transition-all duration-500"
                                            :style="{ width: job.progress + '%' }"
                                        ></div>
                                    </div>
                                    <div class="mt-1 text-xs text-right">{{ job.progress }}%</div>
                                </div>

                                <!-- Output or error message -->
                                <div v-if="job.output" class="text-xs">
                                    <div class="font-medium mb-0.5">Output:</div>
                                    <div class="text-neutral-600">{{ job.output }}</div>
                                </div>

                                <div v-if="job.error" class="text-xs mt-1">
                                    <div class="font-medium mb-0.5 text-red-700">Error:</div>
                                    <div class="text-red-600">{{ job.error }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Non-batched Jobs -->
                <div v-if="nonBatchedJobs.length > 0" class="space-y-3">
                    <div v-if="processedBatches.length > 0" class="text-sm font-medium text-neutral-500 mt-4 mb-2">
                        Individual Runs
                    </div>
                    <div
                        v-for="job in nonBatchedJobs"
                        :key="job.job_id"
                        class="border rounded-lg overflow-hidden bg-neutral-50"
                    >
                        <div class="px-4 py-3 flex items-center justify-between border-b">
                            <div>
                                <span class="font-medium">{{ getJobName(job) }}</span>
                                <span
                                    class="ml-2 px-2 py-1 text-xs rounded-full"
                                    :class="getStatusClass(job.status)"
                                >
                                    {{ job.status }}
                                </span>
                            </div>
                            <div class="text-sm text-neutral-500">
                                {{ formatDate(job.created_at) }}
                            </div>
                        </div>

                        <div class="px-4 py-3">
                            <!-- Progress bar -->
                            <div v-if="job.status === 'pending' || job.status === 'processing'" class="mb-3">
                                <div class="h-2 bg-neutral-200 rounded-full overflow-hidden">
                                    <div
                                        class="h-2 bg-blue-500 transition-all duration-500"
                                        :style="{ width: job.progress + '%' }"
                                    ></div>
                                </div>
                                <div class="mt-1 text-xs text-right">{{ job.progress }}%</div>
                            </div>

                            <!-- Output or error message -->
                            <div v-if="job.output" class="text-sm">
                                <div class="font-medium mb-1">Output:</div>
                                <div class="text-neutral-600">{{ job.output }}</div>
                            </div>

                            <div v-if="job.error" class="text-sm mt-2">
                                <div class="font-medium mb-1 text-red-700">Error:</div>
                                <div class="text-red-600">{{ job.error }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useJobStatusStore } from '@/stores/jobStatusStore';

// Define props with the defineProps macro
const props = defineProps({
  autoRefresh: {
    type: Boolean,
    default: true
  },
  refreshInterval: {
    type: Number,
    default: 1000
  }
});

// Use store
const jobStatusStore = useJobStatusStore();
const jobs = computed(() => jobStatusStore.jobs);
const loading = computed(() => jobStatusStore.loading);
const error = computed(() => jobStatusStore.error);

// Process jobs into batches and non-batched jobs
const processedBatches = computed(() => {
  const batches = [];
  const grouped = {};

  // Group jobs by batch ID
  jobs.value.forEach(job => {
    if (job.job_batch_id && job.job_batch_id !== 'null') {
      if (!grouped[job.job_batch_id]) {
        grouped[job.job_batch_id] = [];
      }
      grouped[job.job_batch_id].push(job);
    }
  });

  // Convert to array of batch objects
  Object.keys(grouped).forEach(batchId => {
    if (batchId !== 'null') {
      const batchJobs = grouped[batchId];
      batches.push({
        id: batchId,
        jobs: batchJobs,
        created_at: batchJobs[0].created_at, // Use the first job's created_at as batch created_at
      });
    }
  });

  // Sort batches by created_at, newest first
  return batches.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
});

// Get non-batched jobs
const nonBatchedJobs = computed(() => {
  return jobs.value.filter(job => !job.job_batch_id || job.job_batch_id === 'null');
});

// Fetch jobs on mount
onMounted(async () => {
  if (props.autoRefresh) {
    jobStatusStore.startAutoRefresh(props.refreshInterval);
  }
});

// Clean up on unmount
onBeforeUnmount(() => {
  jobStatusStore.stopAutoRefresh();
});

// Methods
function getJobName(job) {
  // Extract class name from full namespace
  const className = job.job_class.split('\\').pop();
  return className.replace(/Job$/, '');
}

function getStatusClass(status) {
  switch (status) {
    case 'pending':
      return 'bg-yellow-100 text-yellow-800';
    case 'processing':
      return 'bg-blue-100 text-blue-800';
    case 'completed':
      return 'bg-green-100 text-green-800';
    case 'failed':
      return 'bg-red-100 text-red-800';
    default:
      return 'bg-neutral-100 text-neutral-800';
  }
}

function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleString();
}

function formatTime(dateString) {
  const date = new Date(dateString);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function getBatchName(job) {
  // Extract class name from the first job in the batch
  const className = job.job_class.split('\\').pop();
  return className.replace(/Job$/, '') + ' Batch';
}

function getBatchStatus(batch) {
  // Calculate batch status based on jobs
  const statuses = batch.jobs.map(job => job.status);

  if (statuses.includes('failed')) {
    return 'Failed';
  } else if (statuses.includes('processing')) {
    return 'Processing';
  } else if (statuses.includes('pending')) {
    return 'Pending';
  } else if (statuses.every(status => status === 'completed')) {
    return 'Completed';
  } else {
    return 'Mixed';
  }
}
</script>

<style scoped>
.spinner {
    border: 2px solid #f3f3f3;
    border-radius: 50%;
    border-top: 2px solid #1E90FF;
    width: 15px;
    height: 15px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
