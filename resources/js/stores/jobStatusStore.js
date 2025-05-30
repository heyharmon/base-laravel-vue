import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useJobStatusStore = defineStore('jobStatus', () => {
	// State
	const jobs = ref([])
	const batch = ref(null)
	const loading = ref(false)
	const error = ref(null)
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
	async function pollTeamJobs() {
		await fetchTeamJobs()
		startAutoRefresh(1000)
	}

	async function fetchTeamJobs() {
		console.log('Fetching team jobs...')
		loading.value = true
		error.value = null

		try {
			const response = await api.get('/team-jobs')
			jobs.value = response
			return jobs.value
		} catch (err) {
			error.value = 'Failed to load team jobs: ' + (err.response?.data?.error || err.message)
			throw err
		} finally {
			loading.value = false
		}
	}

	function hasActiveJobs() {
		return jobs.value.some((job) => job.status === 'pending' || job.status === 'processing')
	}

	function startAutoRefresh(interval = 1000) {
		stopAutoRefresh()

		refreshTimer.value = setInterval(() => {
			if (hasActiveJobs()) {
				fetchTeamJobs()
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
		error,

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
		getBatchById
	}
})
