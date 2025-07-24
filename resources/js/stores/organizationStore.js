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
	async function fetchOrganizations(teamId, campaignId = null) {
		console.log('Fetching organizations...')
		try {
			const endpoint = campaignId ? `/teams/${teamId}/campaigns/${campaignId}/organizations` : `/teams/${teamId}/organizations`
			const response = await api.get(endpoint)
			organizations.value = response
		} catch (err) {
			console.error('Error fetching organizations:', err)
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

	async function createOrganization(teamId, campaignId, organizationData) {
		console.log('Creating organization...', organizationData)
		isLoading.value = true

		try {
			const endpoint = organizationData.is_competitor ? `/teams/${teamId}/campaigns/${campaignId}/organizations` : `/teams/${teamId}/organizations`

			const response = await api.post(endpoint, organizationData)

			if (organizationData.is_competitor) {
				competitorOrganizations.value.push(response)
			} else {
				ownedOrganizations.value.push(response)
			}
			return response // API interceptor already extracts response.data
		} catch (err) {
			console.error('Error creating organization:', err)
			throw err
		} finally {
			isLoading.value = false
		}
	}

	async function createAndOnboardOrganization(teamId, organizationData) {
		console.log('Creating and onboarding organization...')
		isLoading.value = true

		try {
			const response = await api.post(`/teams/${teamId}/organizations-onboard`, organizationData)
			return response
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

	async function deleteOrganization(teamId, organizationId, campaignId = null) {
		console.log('Deleting organization ID:', organizationId)
		// isLoading.value = true

		try {
			const response = await api.delete(`/organizations/${organizationId}`)
			await fetchOrganizations(teamId, campaignId)
			return response.data
		} catch (err) {
			console.error('Error deleting organization:', err)
			throw err
		} finally {
			// isLoading.value = false
		}
	}

	async function fetchVisibilityMetrics(teamId, campaignId) {
		if (!campaignId) {
			console.error('Campaign ID is required')
			return
		}

		isLoadingVisibility.value = true
		try {
			const params = {}

			if (currentDateRange.value.startDate) {
				params.start_date = currentDateRange.value.startDate
			}
			if (currentDateRange.value.endDate) {
				params.end_date = currentDateRange.value.endDate
			}

			const response = await api.get(`/teams/${teamId}/campaigns/${campaignId}/organization-visibility`, {
				params
			})

			visibilityMetrics.value = response || []
		} catch (error) {
			console.error('Error fetching visibility metrics:', error)
			visibilityMetrics.value = []
		} finally {
			isLoadingVisibility.value = false
		}
	}

	async function findCompetitors(teamId) {
		console.log('Finding competitors from past responses...')
		isLoading.value = true

		try {
			const response = await api.post(`/teams/${teamId}/organizations-find-competitors`)

			await jobStatusStore.pollTeamJobs(teamId)

			return response
		} catch (err) {
			console.error('Error finding competitors:', err)
			throw err
		} finally {
			isLoading.value = false
		}
	}

	// Function to update date range and refresh visibility data
	function setDateRange(teamId, campaignId, dateRange) {
		console.log('Setting date range:', dateRange)
		currentDateRange.value = dateRange
		return fetchVisibilityMetrics(teamId, campaignId)
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
