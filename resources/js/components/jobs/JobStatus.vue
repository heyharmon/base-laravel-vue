<template>
    <div class="job-status-container">
        <h3 class="flex items-center gap-2 text-lg font-medium mb-2" v-if="title">
            {{ title }}
            <div v-if="loading" class="spinner"></div>
        </h3>
        
        <div v-else-if="error" class="bg-red-100 p-3 rounded text-red-700">
            {{ error }}
        </div>
        
        <div>
            <!-- No jobs -->
            <div v-if="jobs.length === 0" class="py-4 text-center text-neutral-500">
                No jobs found.
            </div>
            
            <!-- Job list -->
            <div v-else class="space-y-3">
                <div 
                    v-for="job in jobs" 
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
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useJobStatusStore } from '@/stores/jobStatusStore';

// Define props with the defineProps macro
const props = defineProps({
  title: {
    type: String,
    default: 'Recent Jobs'
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
const jobs = computed(() => jobStatusStore.jobs);
const loading = computed(() => jobStatusStore.loading);
const error = computed(() => jobStatusStore.error);

// Fetch jobs on mount
onMounted(async () => {
  await fetchJobs();
  
  if (props.autoRefresh) {
    jobStatusStore.startAutoRefresh(fetchJobs, props.refreshInterval);
  }
});

// Clean up on unmount
onBeforeUnmount(() => {
  jobStatusStore.stopAutoRefresh();
});

// Methods
async function fetchJobs() {
  try {
    await jobStatusStore.fetchTeamJobs();
  } catch (err) {
    console.error('Error fetching team jobs:', err);
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
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
