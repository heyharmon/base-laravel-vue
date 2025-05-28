import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import api from '@/services/api'

export const useKeywordStore = defineStore('keywords', () => {
	// State
	const keywords = ref([])
	const recommendedKeywords = ref([])
	const isLoading = ref(false)
	const isLoadingRecommended = ref(false)
	const isLoadingDetails = ref(false)
	const isLoadingKeywordResponses = ref(false)
	const selectedKeywordDetails = ref(null)
	const selectedKeywordResponses = ref([])

	// Other stores
	const jobStatusStore = useJobStatusStore()

	// Actions
	async function fetchKeywords(organizationId) {
		console.log('Fetching keywords for organization ID:', organizationId)
		isLoading.value = true

		try {
			keywords.value = await api.get(`organizations/${organizationId}/keywords`)
			recommendedKeywords.value = await api.get(`organizations/${organizationId}/keyword-recommendations`)
		} catch (error) {
			console.error('Error fetching keywords:', error)
		} finally {
			isLoading.value = false
		}
	}

	async function showKeyword(organizationId, id) {
		console.log('Fetching details for keyword ID:', id)
		isLoadingDetails.value = true
		try {
			selectedKeywordDetails.value = await api.get(`organizations/${organizationId}/keywords/${id}?include=prompts`)
		} catch (error) {
			console.error('Error fetching keyword details:', error)
			throw error
		} finally {
			isLoadingDetails.value = false
		}
	}

	async function createKeyword(organizationId, data) {
		console.log('Creating keyword for organization ID:', organizationId)
		isLoading.value = true
		try {
			const newKeyword = await api.post(`organizations/${organizationId}/keywords`, data)
			keywords.value.unshift(newKeyword)
			return newKeyword
		} catch (error) {
			console.error('Error creating keyword:', error)
			throw error
		} finally {
			isLoading.value = false
		}
	}

	async function deleteKeyword(organizationId, id) {
		console.log('Deleting keyword ID:', id)
		try {
			await api.delete(`organizations/${organizationId}/keywords/${id}`)
			keywords.value = keywords.value.filter((k) => k.id !== id)
		} catch (error) {
			console.error('Error deleting keyword:', error)
			throw error
		} finally {
		}
	}

	// TODO: Test
	async function getKeywordResponses(keywordId, promptId) {
		console.log('Fetching keyword responses for keyword ID:', keywordId, 'and prompt ID:', promptId)
		isLoadingKeywordResponses.value = true
		selectedKeywordResponses.value = []
		try {
			selectedKeywordResponses.value = await api.get(`/keywords/${keywordId}/prompts/${promptId}/responses`)
			return selectedKeywordResponses.value
		} catch (error) {
			console.error('Error fetching keyword responses:', error)
			throw error
		} finally {
			isLoadingKeywordResponses.value = false
		}
	}

	async function acceptRecommendedKeyword(organizationId, id) {
		console.log('Accepting recommended keyword ID:', id, 'for organization ID:', organizationId)
		try {
			const keyword = await api.put(`organizations/${organizationId}/keyword-recommendations/${id}/accept`)
			// Remove from recommended list
			recommendedKeywords.value = recommendedKeywords.value.filter((k) => k.id !== id)
			// Add to regular keywords list
			keywords.value.unshift(keyword)

			return keyword
		} catch (error) {
			console.error('Error accepting recommended keyword:', error)
			throw error
		}
	}

	async function denyRecommendedKeyword(organizationId, id) {
		console.log('Denying recommended keyword ID:', id, 'for organization ID:', organizationId)
		try {
			await api.delete(`organizations/${organizationId}/keyword-recommendations/${id}/deny`)
			// Remove from recommended list
			recommendedKeywords.value = recommendedKeywords.value.filter((k) => k.id !== id)
		} catch (error) {
			console.error('Error denying recommended keyword:', error)
			throw error
		}
	}

	return {
		// State
		keywords: computed(() => keywords.value),
		recommendedKeywords: computed(() => recommendedKeywords.value),
		isLoading,
		isLoadingRecommended,
		isLoadingDetails,
		isLoadingKeywordResponses,
		selectedKeywordDetails: computed(() => selectedKeywordDetails.value),
		selectedKeywordResponses: computed(() => selectedKeywordResponses.value),

		// Actions
		fetchKeywords,
		acceptRecommendedKeyword,
		denyRecommendedKeyword,
		showKeyword,
		createKeyword,
		deleteKeyword,
		getKeywordResponses
	}
})
