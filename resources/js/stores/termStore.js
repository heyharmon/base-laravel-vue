import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import api from '@/services/api'

export const useTermStore = defineStore('terms', () => {
	// State
	const terms = ref([])
	const isLoading = ref(false)
	const isLoadingDetails = ref(false)
	const isLoadingTermResponses = ref(false)
	const selectedTermDetails = ref(null)
	const selectedTermResponses = ref([])

	// Other stores
	const jobStatusStore = useJobStatusStore()

	// Actions
        async function fetchTerms(teamId, organizationId) {
		console.log('Fetching terms for organization ID:', organizationId)
		isLoading.value = true

		try {
                        terms.value = await api.get(`teams/${teamId}/organizations/${organizationId}/terms`)
		} catch (error) {
			console.error('Error fetching terms:', error)
		} finally {
			isLoading.value = false
		}
	}

        async function showTerm(teamId, organizationId, id) {
                console.log('Fetching details for term ID:', id)
                isLoadingDetails.value = true
                try {
                        selectedTermDetails.value = await api.get(`teams/${teamId}/organizations/${organizationId}/terms/${id}?include=prompts`)
                } catch (error) {
                        console.error('Error fetching term details:', error)
                        throw error
                } finally {
                        isLoadingDetails.value = false
                }
        }

        async function createTerm(teamId, organizationId, data) {
		console.log('Creating term for organization ID:', organizationId)
		isLoading.value = true
		try {
                        const newTerm = await api.post(`teams/${teamId}/organizations/${organizationId}/terms`, data)
			terms.value.unshift(newTerm)
			return newTerm
		} catch (error) {
			console.error('Error creating term:', error)
			throw error
		} finally {
			isLoading.value = false
		}
	}

        async function deleteTerm(teamId, organizationId, id) {
                console.log('Deleting term ID:', id)
                try {
                        await api.delete(`teams/${teamId}/organizations/${organizationId}/terms/${id}`)
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

	return {
		// State
		terms: computed(() => terms.value),
		isLoading,
		isLoadingDetails,
		isLoadingTermResponses,
		selectedTermDetails: computed(() => selectedTermDetails.value),
		selectedTermResponses: computed(() => selectedTermResponses.value),

		// Actions
		fetchTerms,
		showTerm,
		createTerm,
		deleteTerm,
		getTermResponses
	}
})
