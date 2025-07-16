import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import moment from 'moment'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import api from '@/services/api'

export const useOrganizationStore = defineStore('organization', () => {
	// State
	const organizations = ref([])
	const currentOrganization = ref(null)
	const isLoading = ref(false)
	const visibilityMetrics = ref([])
	const isLoadingVisibility = ref(false)

	// Date range for visibility metrics
	const currentDateRange = ref({
		startDate: moment().clone().startOf('year').format('YYYY-MM-DD'),
		endDate: moment().format('YYYY-MM-DD')
	})

	// Other stores
	const jobStatusStore = useJobStatusStore()

	// Getters
	const ownedOrganizations = computed(() => (organizations.value ? organizations.value.filter((org) => !org.is_competitor) : []))
	const competitorOrganizations = computed(() => (organizations.value ? organizations.value.filter((org) => org.is_competitor) : []))

	// Actions
	async function fetchOrganizations() {
		console.log('Fetching organizations...')
		// isLoading.value = true

		try {
			const response = await api.get('/organizations')
			organizations.value = response
		} catch (err) {
			console.error('Error fetching organizations:', err)
		} finally {
			// isLoading.value = false
		}
	}

	async function fetchOrganization(organizationId) {
		isLoading.value = true

		try {
			const response = await api.get(`/organizations/${organizationId}`)
			currentOrganization.value = response
			return response
		} catch (err) {
			window.location.href = '/organizations'
			console.error('Error fetching organization details:', err)
		} finally {
			isLoading.value = false
		}
	}

	async function createOrganization(organizationData) {
		console.log('Creating organization...', organizationData)
		isLoading.value = true

		try {
			const response = await api.post('/organizations', organizationData)
			await fetchOrganizations()
			return response // API interceptor already extracts response.data
		} catch (err) {
			console.error('Error creating organization:', err)
			throw err
		} finally {
			isLoading.value = false
		}
	}

	async function createAndOnboardOrganization(organizationData) {
		console.log('Creating and onboarding organization...')
		isLoading.value = true

		try {
			const response = await api.post('/organizations-onboard', organizationData)
			await fetchOrganizations()
			return response // API interceptor already extracts response.data
		} catch (err) {
			console.error('Error creating organization:', err)
			throw err
		} finally {
			isLoading.value = false
		}
	}

	async function updateOrganization(organizationId, organizationData) {
		console.log('Updating organization ID:', organizationId)
		isLoading.value = true

		try {
			const response = await api.put(`/organizations/${organizationId}`, organizationData)
			console.log('response', response)

			// Update current organization if matches
			if (currentOrganization.value && currentOrganization.value.id === organizationId) {
				currentOrganization.value = { ...currentOrganization.value, ...organizationData }
			}

			return response
		} catch (err) {
			console.error('Error updating organization:', err)
			throw err
		} finally {
			isLoading.value = false
		}
	}

	async function deleteOrganization(organizationId) {
		console.log('Deleting organization ID:', organizationId)
		// isLoading.value = true

		try {
			const response = await api.delete(`/organizations/${organizationId}`)
			await fetchOrganizations()
			return response.data
		} catch (err) {
			console.error('Error deleting organization:', err)
			throw err
		} finally {
			// isLoading.value = false
		}
	}

	async function fetchVisibilityMetrics(params = null) {
		console.log('Fetching visibility metrics...')
		isLoadingVisibility.value = true

		try {
			// Use provided params or fall back to store's currentDateRange
			const dateParams = params || currentDateRange.value

			const queryParams = new URLSearchParams()
			if (dateParams.startDate) queryParams.append('start_date', dateParams.startDate)
			if (dateParams.endDate) queryParams.append('end_date', dateParams.endDate)

			const queryString = queryParams.toString()
			// const url = `/organization-visibility${queryString ? `?${queryString}` : ''}`
			const url = `/organization-visibility`

			const response = await api.get(url)
			visibilityMetrics.value = response
			return response
		} catch (err) {
			console.error('Error fetching organization visibility metrics:', err)
		} finally {
			isLoadingVisibility.value = false
		}
	}

	async function findCompetitors() {
		console.log('Finding competitors from past responses...')
		isLoading.value = true

		try {
			const response = await api.post('/organizations-find-competitors')

			await jobStatusStore.pollTeamJobs()

			return response
		} catch (err) {
			console.error('Error finding competitors:', err)
			throw err
		} finally {
			isLoading.value = false
		}
	}

	// Function to update date range and refresh visibility data
	function setDateRange(dateRange) {
		console.log('Setting date range:', dateRange)
		currentDateRange.value = dateRange
		return fetchVisibilityMetrics()
	}

	return {
		// State
		organizations,
		currentOrganization,
		isLoading,
		isLoadingVisibility,
		visibilityMetrics,
		currentDateRange,

		// Getters
		ownedOrganizations,
		competitorOrganizations,

		// Actions
		fetchOrganizations,
		fetchOrganization,
		createOrganization,
		createAndOnboardOrganization,
		updateOrganization,
		deleteOrganization,
		fetchVisibilityMetrics,
		setDateRange,
		findCompetitors
	}
})
