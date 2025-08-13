import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useJobStatusStore = defineStore('jobStatus', () => {
	// State
	const jobs = ref([])
	const batch = ref(null)
	const loading = ref(false)
	let refreshTimer = ref(null)

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
	async function pollTeamJobs(teamId) {
		await fetchTeamJobs(teamId)
		startAutoRefresh(teamId, 1500)
	}

	async function fetchTeamJobs(teamId) {
		console.log('Fetching team jobs...')
		loading.value = true

		try {
			const response = await api.get(`/teams/${teamId}/jobs`)
			// console.log('jobs response', response)
			jobs.value = response
			return jobs.value
		} catch (err) {
			console.error('Error loading team jobs:', err)
			throw err
		} finally {
			loading.value = false
		}
	}

	async function cancelTeamJobs(teamId) {
		try {
			await api.post(`/teams/${teamId}/jobs/cancel`)
			await fetchTeamJobs(teamId)
		} catch (err) {
			console.error('Error cancelling jobs:', err)
			throw err
		}
	}

	function hasActiveJobs() {
		return Array.isArray(jobs.value) && jobs.value.some((job) => job.status === 'pending' || job.status === 'processing')
	}

	function startAutoRefresh(teamId, interval = 2000) {
		stopAutoRefresh()
		let pollsAfterCompletion = 0
		const maxPollsAfterCompletion = 2 // Poll 3 more times after completion

		refreshTimer.value = setInterval(() => {
			if (hasActiveJobs()) {
				fetchTeamJobs(teamId)
				pollsAfterCompletion = 0 // Reset counter when active jobs exist
			} else if (pollsAfterCompletion < maxPollsAfterCompletion) {
				// Continue polling for a few cycles after completion to catch final updates
				fetchTeamJobs(teamId)
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

	function getBatchById(batchId) {
		if (batch.value && batch.value.id === batchId) {
			return batch.value
		}
		return null
	}

	return {
		// State
		jobs: computed(() => jobs.value),
		batch: computed(() => batch.value),
		loading,

		// Getters
		activeJobs,
		processingJobs,
		completedJobs,
		processingJobsByClass,
		completedJobsByClass,

		// Actions
		pollTeamJobs,
		fetchTeamJobs,
		hasActiveJobs,
		startAutoRefresh,
		stopAutoRefresh,
		getJobById,
		getBatchById,
		cancelTeamJobs
	}
})
