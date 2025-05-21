<template>
    <div class="job-batch-container">
        <h3 class="text-lg font-medium" v-if="title">{{ title }}</h3>
        
        <div v-if="loading" class="py-4 text-center">
            <div class="spinner"></div>
            <p>Loading batch status...</p>
        </div>
        
        <div v-else-if="error" class="bg-red-100 p-3 rounded text-red-700">
            {{ error }}
        </div>
        
        <div v-else-if="batch">
            <div class="border rounded-lg overflow-hidden bg-neutral-50">
                <!-- Batch header -->
                <div class="px-4 py-3 bg-neutral-100 border-b">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-medium">{{ batch.name || 'Batch #' + batch.id }}</span>
                            <span 
                                class="ml-2 px-2 py-1 text-xs rounded-full"
                                :class="getBatchStatusClass()"
                            >
                                {{ getBatchStatus() }}
                            </span>
                        </div>
                        <div class="text-sm text-neutral-500">
                            {{ formatDate(batch.created_at) }}
                        </div>
                    </div>
                </div>
                
                <!-- Batch progress -->
                <div class="px-4 py-3 border-b">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm">Progress</div>
                        <div class="text-sm font-medium">{{ batch.progress }}%</div>
                    </div>
                    
                    <!-- Progress bar -->
                    <div class="h-2 bg-neutral-200 rounded-full overflow-hidden">
                        <div 
                            class="h-2 transition-all duration-500"
                            :class="getProgressBarClass()"
                            :style="{ width: batch.progress + '%' }"
                        ></div>
                    </div>
                </div>
                
                <!-- Batch stats -->
                <div class="px-4 py-3 border-b grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <div class="text-neutral-500">Total Jobs</div>
                        <div class="font-medium">{{ batch.total_jobs }}</div>
                    </div>
                    <div>
                        <div class="text-neutral-500">Processed</div>
                        <div class="font-medium">{{ batch.processed_jobs }}</div>
                    </div>
                    <div>
                        <div class="text-neutral-500">Pending</div>
                        <div class="font-medium">{{ batch.pending_jobs }}</div>
                    </div>
                    <div>
                        <div class="text-neutral-500">Failed</div>
                        <div class="font-medium">{{ batch.failed_jobs }}</div>
                    </div>
                </div>
                
                <!-- Job list -->
                <div class="px-4 py-3">
                    <h4 class="font-medium mb-3">Jobs in this batch</h4>
                    
                    <div class="space-y-3">
                        <div 
                            v-for="job in jobs" 
                            :key="job.job_id" 
                            class="border rounded p-3"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <span class="font-medium">{{ getJobName(job) }}</span>
                                    <span 
                                        class="ml-2 px-2 py-1 text-xs rounded-full"
                                        :class="getStatusClass(job.status)"
                                    >
                                        {{ job.status }}
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Progress bar for pending/processing jobs -->
                            <div v-if="job.status === 'pending' || job.status === 'processing'" class="mb-2">
                                <div class="h-1 bg-neutral-200 rounded-full overflow-hidden">
                                    <div 
                                        class="h-1 bg-blue-500 transition-all duration-500"
                                        :style="{ width: job.progress + '%' }"
                                    ></div>
                                </div>
                                <div class="mt-1 text-xs text-right">{{ job.progress }}%</div>
                            </div>
                            
                            <!-- Output or error message -->
                            <div v-if="job.output" class="text-xs mt-1 text-neutral-600">
                                {{ job.output }}
                            </div>
                            
                            <div v-if="job.error" class="text-xs mt-1 text-red-600">
                                {{ job.error }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div v-else class="py-4 text-center text-neutral-500">
            No batch information available.
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useJobStatusStore } from '@/stores/jobStatusStore';

const props = defineProps({
  batchId: {
    type: String,
    required: true
  },
  title: {
    type: String,
    default: 'Batch Status'
  },
  autoRefresh: {
    type: Boolean,
    default: true
  },
  refreshInterval: {
    type: Number,
    default: 5000
  }
});

// Use store
const jobStatusStore = useJobStatusStore();
const batch = computed(() => jobStatusStore.batch);
const jobs = computed(() => jobStatusStore.jobs);
const loading = computed(() => jobStatusStore.loading);
const error = computed(() => jobStatusStore.error);

// Fetch batch info on mount
onMounted(async () => {
  await fetchBatchInfo();
  
  if (props.autoRefresh) {
    jobStatusStore.startAutoRefresh(fetchBatchInfo, props.refreshInterval);
  }
});

// Clean up on unmount
onBeforeUnmount(() => {
  jobStatusStore.stopAutoRefresh();
});

// Watch for changes in batchId
watch(() => props.batchId, () => {
  fetchBatchInfo();
});

// Methods
async function fetchBatchInfo() {
  try {
    await jobStatusStore.fetchBatchInfo(props.batchId);
  } catch (err) {
    console.error('Error fetching batch info:', err);
  }
}

function getBatchStatus() {
  if (!batch.value) return '';
  
  if (batch.value.cancelled) {
    return 'Cancelled';
  } else if (batch.value.finished) {
    return batch.value.failed_jobs > 0 ? 'Completed with failures' : 'Completed';
  } else {
    return 'Processing';
  }
}

function getBatchStatusClass() {
  const status = getBatchStatus();
  
  switch (status) {
    case 'Processing':
      return 'bg-blue-100 text-blue-800';
    case 'Completed':
      return 'bg-green-100 text-green-800';
    case 'Completed with failures':
      return 'bg-yellow-100 text-yellow-800';
    case 'Cancelled':
      return 'bg-red-100 text-red-800';
    default:
      return 'bg-neutral-100 text-neutral-800';
  }
}

function getProgressBarClass() {
  const status = getBatchStatus();
  
  switch (status) {
    case 'Completed':
      return 'bg-green-500';
    case 'Completed with failures':
      return 'bg-yellow-500';
    case 'Cancelled':
      return 'bg-red-500';
    default:
      return 'bg-blue-500';
  }
}

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
</script>

<style scoped>
.spinner {
    border: 2px solid #f3f3f3;
    border-radius: 50%;
    border-top: 2px solid #3498db;
    width: 20px;
    height: 20px;
    animation: spin 1s linear infinite;
    margin: 0 auto 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
