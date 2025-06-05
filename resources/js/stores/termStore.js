import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import api from '@/services/api'

export const useTermStore = defineStore('terms', () => {
	// State
	const terms = ref([])
	const recommendedTerms = ref([])
	const isLoading = ref(false)
	const isLoadingRecommended = ref(false)
	const isLoadingDetails = ref(false)
	const isLoadingTermResponses = ref(false)
	const selectedTermDetails = ref(null)
	const selectedTermResponses = ref([])

	// Other stores
	const jobStatusStore = useJobStatusStore()

	// Actions
	async function fetchTerms(organizationId) {
		console.log('Fetching terms for organization ID:', organizationId)
		isLoading.value = true

		try {
			terms.value = await api.get(`organizations/${organizationId}/terms`)
			recommendedTerms.value = await api.get(`organizations/${organizationId}/term-recommendations`)
		} catch (error) {
			console.error('Error fetching terms:', error)
		} finally {
			isLoading.value = false
		}
	}

	async function showTerm(organizationId, id) {
		console.log('Fetching details for term ID:', id)
		isLoadingDetails.value = true
		try {
			selectedTermDetails.value = await api.get(`organizations/${organizationId}/terms/${id}?include=prompts`)
		} catch (error) {
			console.error('Error fetching term details:', error)
			throw error
		} finally {
			isLoadingDetails.value = false
		}
	}

	async function createTerm(organizationId, data) {
		console.log('Creating term for organization ID:', organizationId)
		isLoading.value = true
		try {
			const newTerm = await api.post(`organizations/${organizationId}/terms`, data)
			terms.value.unshift(newTerm)
			return newTerm
		} catch (error) {
			console.error('Error creating term:', error)
			throw error
		} finally {
			isLoading.value = false
		}
	}

	async function deleteTerm(organizationId, id) {
		console.log('Deleting term ID:', id)
		try {
			await api.delete(`organizations/${organizationId}/terms/${id}`)
			terms.value = terms.value.filter((k) => k.id !== id)
		} catch (error) {
			console.error('Error deleting term:', error)
			throw error
		} finally {
		}
	}

	// TODO: Test
	async function getTermResponses(termId, promptId) {
		console.log('Fetching term responses for term ID:', termId, 'and prompt ID:', promptId)
		isLoadingTermResponses.value = true
		selectedTermResponses.value = []
		try {
			selectedTermResponses.value = await api.get(`/terms/${termId}/prompts/${promptId}/responses`)
			return selectedTermResponses.value
		} catch (error) {
			console.error('Error fetching term responses:', error)
			throw error
		} finally {
			isLoadingTermResponses.value = false
		}
	}

	async function acceptRecommendedTerm(organizationId, id) {
		console.log('Accepting recommended term ID:', id, 'for organization ID:', organizationId)
		try {
			const term = await api.put(`organizations/${organizationId}/term-recommendations/${id}/accept`)
			// Remove from recommended list
			recommendedTerms.value = recommendedTerms.value.filter((k) => k.id !== id)
			// Add to regular terms list
			terms.value.unshift(term)

			return term
		} catch (error) {
			console.error('Error accepting recommended term:', error)
			throw error
		}
	}

	async function denyRecommendedTerm(organizationId, id) {
		console.log('Denying recommended term ID:', id, 'for organization ID:', organizationId)
		try {
			await api.delete(`organizations/${organizationId}/term-recommendations/${id}/deny`)
			// Remove from recommended list
			recommendedTerms.value = recommendedTerms.value.filter((k) => k.id !== id)
		} catch (error) {
			console.error('Error denying recommended term:', error)
			throw error
		}
	}

	return {
		// State
		terms: computed(() => terms.value),
		recommendedTerms: computed(() => recommendedTerms.value),
		isLoading,
		isLoadingRecommended,
		isLoadingDetails,
		isLoadingTermResponses,
		selectedTermDetails: computed(() => selectedTermDetails.value),
		selectedTermResponses: computed(() => selectedTermResponses.value),

		// Actions
		fetchTerms,
		acceptRecommendedTerm,
		denyRecommendedTerm,
		showTerm,
		createTerm,
		deleteTerm,
		getTermResponses
	}
})
