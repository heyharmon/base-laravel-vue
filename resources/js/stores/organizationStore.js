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
	const competitorCount = ref(0)
	const competitorLimit = ref(500)

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
			// const endpoint = campaignId ? `/teams/${teamId}/campaigns/${campaignId}/organizations` : `/teams/${teamId}/organizations`
			// console.log('endpoint', endpoint)
			const response = await api.get(`/teams/${teamId}/campaigns/${campaignId}/organizations`)

			// Handle new response format with competitor count and limit
			if (response.organizations) {
				organizations.value = response.organizations
				competitorCount.value = response.competitor_count || 0
				competitorLimit.value = response.competitor_limit || 500
			} else {
				// Fallback for old response format
				organizations.value = response
			}
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

	async function createOwnedOrganization(teamId, organizationData) {
		console.log('Creating owned organization...')
		isLoading.value = true

		try {
			const response = await api.post(`/teams/${teamId}/organizations`, organizationData)
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
		try {
			const response = await api.delete(`/organizations/${organizationId}`)
			await fetchOrganizations(teamId, campaignId)
			return response
		} catch (err) {
			console.error('Error deleting organization:', err)
			throw err
		}
	}

	async function fetchCampaignVisibilityMetrics(teamId, campaignId) {
		if (!campaignId) {
			console.error('Campaign ID is required')
			return
		}

		isLoadingVisibility.value = true
		try {
			const params = {
				timezone: Intl.DateTimeFormat().resolvedOptions().timeZone // User's timezone
			}

			// Only add date parameters if they are not null
			if (currentDateRange.value.startDate && currentDateRange.value.startDate !== null) {
				params.start_date = currentDateRange.value.startDate
			}
			if (currentDateRange.value.endDate && currentDateRange.value.endDate !== null) {
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

	async function findCompetitors(teamId, campaignId) {
		console.log('Finding competitors from past responses...')
		isLoading.value = true

		try {
			const response = await api.post(`/teams/${teamId}/campaigns/${campaignId}/organizations-find-competitors`)

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
		currentDateRange.value = dateRange
		return fetchCampaignVisibilityMetrics(teamId, campaignId)
	}

	return {
		// State
		organizations,
		currentOrganization,
		isLoading,
		isLoadingVisibility,
		visibilityMetrics,
		currentDateRange,
		competitorCount,
		competitorLimit,

		// Getters
		ownedOrganizations,
		competitorOrganizations,

		// Actions
		fetchOrganizations,
		fetchOrganization,
		createOrganization,
		createOwnedOrganization,
		updateOrganization,
		deleteOrganization,
		fetchCampaignVisibilityMetrics,
		setDateRange,
		findCompetitors
	}
})
