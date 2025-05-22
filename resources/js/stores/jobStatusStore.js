import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/services/api';

export const useJobStatusStore = defineStore('jobStatus', () => {
  // State
  const jobs = ref([]);
  const batch = ref(null);
  const loading = ref(false);
  const error = ref(null);
  let refreshTimer = ref(null);
  
  // Actions
  async function fetchModelJobs(modelType, modelId) {
    loading.value = true;
    error.value = null;

    try {
      const response = await api.get('/job-statuses', {
        params: {
          model_type: modelType,
          model_id: modelId
        }
      });
      
      jobs.value = response.data;
      return jobs.value;
    } catch (err) {
      error.value = 'Failed to load job status: ' + (err.response?.data?.error || err.message);
      throw err;
    } finally {
      loading.value = false;
    }
  }
  
  async function fetchTeamJobs() {
    console.log('Fetching team jobs...')
    loading.value = true;
    error.value = null;
    
    try {
      const response = await api.get('/team-jobs');
      jobs.value = response;
      return jobs.value;
    } catch (err) {
      error.value = 'Failed to load team jobs: ' + (err.response?.data?.error || err.message);
      throw err;
    } finally {
      loading.value = false;
    }
  }
  
  async function fetchJobStatus(jobId) {
    loading.value = true;
    error.value = null;
    
    try {
      const response = await api.get(`/job-status/${jobId}`);
      const job = response.data;
      
      // Update job in the list if it exists
      const index = jobs.value.findIndex(j => j.job_id === jobId);
      if (index !== -1) {
        jobs.value[index] = job;
      } else {
        jobs.value.push(job);
      }
      
      return job;
    } catch (err) {
      error.value = 'Failed to load job status: ' + (err.response?.data?.error || err.message);
      throw err;
    } finally {
      loading.value = false;
    }
  }
  
  async function fetchBatchInfo(batchId) {
    loading.value = true;
    error.value = null;
    
    try {
      const response = await api.get(`/job-batch/${batchId}`);
      batch.value = response.data.batch;
      jobs.value = response.data.jobs;
      return { batch: batch.value, jobs: jobs.value };
    } catch (err) {
      error.value = 'Failed to load batch info: ' + (err.response?.data?.error || err.message);
      throw err;
    } finally {
      loading.value = false;
    }
  }
  
  async function fetchActiveJobs() {
    loading.value = true;
    error.value = null;
    
    try {
      const response = await api.get('/active-jobs');
      jobs.value = response.data.data;
      return jobs.value;
    } catch (err) {
      error.value = 'Failed to load active jobs: ' + (err.response?.data?.error || err.message);
      throw err;
    } finally {
      loading.value = false;
    }
  }
  
  function hasActiveJobs() {
    return jobs.value.some(job => 
      job.status === 'pending' || job.status === 'processing'
    );
  }
  
  function startAutoRefresh(interval = 1000) {
    stopAutoRefresh();
    
    refreshTimer.value = setInterval(() => {
      if (hasActiveJobs()) {
        fetchTeamJobs();
      }
    }, interval);
  }
  
  function stopAutoRefresh() {
    if (refreshTimer.value) {
      clearInterval(refreshTimer.value);
      refreshTimer.value = null;
    }
  }
  
  function getJobById(jobId) {
    return jobs.value.find(job => job.job_id === jobId);
  }
  
  function getBatchById(batchId) {
    if (batch.value && batch.value.id === batchId) {
      return batch.value;
    }
    return null;
  }
  
  return {
    // State
    jobs: computed(() => jobs.value),
    batch: computed(() => batch.value),
    loading,
    error,
    
    // Actions
    fetchModelJobs,
    fetchTeamJobs,
    fetchJobStatus,
    fetchBatchInfo,
    fetchActiveJobs,
    hasActiveJobs,
    startAutoRefresh,
    stopAutoRefresh,
    getJobById,
    getBatchById
  };
});
