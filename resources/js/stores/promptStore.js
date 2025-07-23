import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const usePromptStore = defineStore('prompts', () => {
	// State
	const prompts = ref([])
	const isLoading = ref(false)
	const isLoadingDetails = ref(false)
	const loadingPromptIds = ref([])
	const selectedPromptDetails = ref(null)
	const selectedPromptResponses = ref([])
	const isLoadingPromptResponses = ref(false)
	const isRunningAll = ref(false)

	// Actions
        async function fetchPrompts(teamId, campaignId) {
                if (!campaignId) {
                        console.error('Campaign ID is required')
                        return
                }
                console.log('Fetching prompts...')

                isLoading.value = true
                try {
                        prompts.value = await api.get(`/teams/${teamId}/campaigns/${campaignId}/prompts`)
                } catch (error) {
                        console.error('Error fetching prompts:', error)
                } finally {
                        isLoading.value = false
                }
        }

	async function showPrompt(id) {
		console.log('Fetching prompt details for prompt ID:', id)
		isLoadingDetails.value = true
		try {
			selectedPromptDetails.value = await api.get(`/prompts/${id}`)
		} catch (error) {
			console.error('Error fetching prompt details:', error)
			throw error
		} finally {
			isLoadingDetails.value = false
		}
	}

        async function createPrompt(teamId, campaignId, data) {
                console.log('Creating prompt...')

                isLoading.value = true
                try {
                        const newPrompt = await api.post(`/teams/${teamId}/campaigns/${campaignId}/prompts`, data)
                        prompts.value.unshift(newPrompt)
                        return newPrompt
		} catch (error) {
			console.error('Error creating prompt:', error)
			throw error
		} finally {
			isLoading.value = false
		}
	}

	async function updatePrompt(id, data) {
		console.log('Updating prompt ID:', id)
		isLoading.value = true
		try {
			const updatedPrompt = await api.put(`/prompts/${id}`, data)

			const index = prompts.value.findIndex((p) => p.id === id)
			if (index !== -1) {
				prompts.value[index] = updatedPrompt
			}

			return updatedPrompt
		} catch (error) {
			console.error('Error updating prompt:', error)
			throw error
		} finally {
			isLoading.value = false
		}
	}

	async function deletePrompt(id) {
		console.log('Deleting prompt ID:', id)
		try {
			await api.delete(`/prompts/${id}`)
			prompts.value = prompts.value.filter((p) => p.id !== id)
		} catch (error) {
			console.error('Error deleting prompt:', error)
			throw error
		} finally {
		}
	}

	async function runPrompt(id, count = 1) {
		console.log('Running prompt ID:', id)
		loadingPromptIds.value.push(id)
		try {
			return await api.post(`/prompts/${id}/run`, { count })
		} catch (error) {
			console.error('Error running prompt:', error)
			throw error
		} finally {
			loadingPromptIds.value = loadingPromptIds.value.filter((promptId) => promptId !== id)
		}
	}

	async function runAllPrompts(teamId, count = 1) {
		console.log('Running all prompts...')

		isRunningAll.value = true
		try {
			return await api.post(`/teams/${teamId}/prompt-run-batch`, { count })
		} catch (error) {
			console.error('Error running all prompts:', error)
			throw error
		} finally {
			isRunningAll.value = false
		}
	}

	async function getPromptResponses(promptId) {
		console.log('Fetching prompt responses for prompt ID:', promptId)
		isLoadingPromptResponses.value = true
		try {
			selectedPromptResponses.value = await api.get(`/prompts/${promptId}/responses`)
			return selectedPromptResponses.value
		} catch (error) {
			console.error('Error fetching prompt responses:', error)
			throw error
		} finally {
			isLoadingPromptResponses.value = false
		}
	}

	return {
		// State
		prompts: computed(() => prompts.value),
		isLoading,
		isLoadingDetails,
		loadingPromptIds,
		selectedPromptDetails: computed(() => selectedPromptDetails.value),
		selectedPromptResponses: computed(() => selectedPromptResponses.value),
		isLoadingPromptResponses,
		isRunningAll,

		// Actions
		fetchPrompts,
		showPrompt,
		createPrompt,
		updatePrompt,
		deletePrompt,
		runPrompt,
		runAllPrompts,
		getPromptResponses
	}
})
