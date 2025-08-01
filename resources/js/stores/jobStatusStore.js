import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useJobStatusStore = defineStore('jobStatus', () => {
	// State
       const jobs = ref([])
       const loading = ref(false)
       let refreshTimer = ref(null)
       const currentTeamId = ref(null)
       const currentCampaignId = ref(null)

	// Getters
	const activeJobs = computed(() => (jobs.value ? jobs.value.filter((job) => job.status === 'pending' || job.status === 'processing') : []))
	const processingJobs = computed(() => (jobs.value ? jobs.value.filter((job) => job.status === 'processing') : []))
	const completedJobs = computed(() => (jobs.value ? jobs.value.filter((job) => job.status === 'completed') : []))

	const processingJobsByClass = computed(() => {
		const grouped = {}
		if (processingJobs.value) {
			processingJobs.value.forEach((job) => {
				const jobClass = job.job_class || 'unknown'
				if (!grouped[jobClass]) {
					grouped[jobClass] = []
				}
				grouped[jobClass].push(job)
			})
		}
		return grouped
	})

	const completedJobsByClass = computed(() => {
		const grouped = {}
		if (completedJobs.value) {
			completedJobs.value.forEach((job) => {
				const jobClass = job.job_class || 'unknown'
				if (!grouped[jobClass]) {
					grouped[jobClass] = []
				}
				grouped[jobClass].push(job)
			})
		}
		return grouped
	})

	// Actions
       async function pollJobs(teamId, campaignId = null) {
               currentTeamId.value = teamId
               currentCampaignId.value = campaignId
               await fetchJobs()
               startAutoRefresh()
       }

       async function fetchJobs() {
               if (!currentTeamId.value) return

               loading.value = true

               try {
                       const endpoint = currentCampaignId.value
                               ? `/teams/${currentTeamId.value}/campaigns/${currentCampaignId.value}/jobs`
                               : `/teams/${currentTeamId.value}/jobs`

                       const response = await api.get(endpoint)
                       jobs.value = response
                       return jobs.value
               } catch (err) {
                       console.error('Error loading jobs:', err)
                       throw err
               } finally {
                       loading.value = false
               }
       }

       async function cancelJobs() {
               if (!currentTeamId.value) return

               try {
                       const endpoint = currentCampaignId.value
                               ? `/teams/${currentTeamId.value}/campaigns/${currentCampaignId.value}/jobs/cancel`
                               : `/teams/${currentTeamId.value}/jobs/cancel`

                       await api.post(endpoint)
                       await fetchJobs()
               } catch (err) {
                       console.error('Error cancelling jobs:', err)
                       throw err
               }
       }

	function hasActiveJobs() {
		return Array.isArray(jobs.value) && jobs.value.some((job) => job.status === 'pending' || job.status === 'processing')
	}

       function startAutoRefresh(interval = 2000) {
               stopAutoRefresh()
               let pollsAfterCompletion = 0
               const maxPollsAfterCompletion = 3 // Poll 3 more times after completion

               refreshTimer.value = setInterval(() => {
                       if (hasActiveJobs()) {
                               fetchJobs()
                               pollsAfterCompletion = 0 // Reset counter when active jobs exist
                       } else if (pollsAfterCompletion < maxPollsAfterCompletion) {
                               // Continue polling for a few cycles after completion to catch final updates
                               fetchJobs()
                               pollsAfterCompletion++
                       } else {
                               // Stop polling after we've checked enough times post-completion
                               stopAutoRefresh()
                       }
               }, interval)
       }

	function stopAutoRefresh() {
		if (refreshTimer.value) {
			clearInterval(refreshTimer.value)
			refreshTimer.value = null
		}
	}

	function getJobById(jobId) {
		return jobs.value.find((job) => job.job_id === jobId)
	}

       return {
               // State
               jobs: computed(() => jobs.value),
               loading,

		// Getters
		activeJobs,
		processingJobs,
		completedJobs,
		processingJobsByClass,
		completedJobsByClass,

		// Actions
               pollJobs,
               fetchJobs,
               hasActiveJobs,
               startAutoRefresh,
               stopAutoRefresh,
               getJobById,
               cancelJobs,
               // Backward compatibility
               pollTeamJobs: pollJobs,
               fetchTeamJobs: fetchJobs,
               cancelTeamJobs: cancelJobs
       }
})
