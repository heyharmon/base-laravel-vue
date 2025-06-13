import { defineStore } from 'pinia'
import { ref, watch, computed } from 'vue'
import { watchDebounced } from '@vueuse/core'
import api from '@/services/api'

export const useArticleStore = defineStore('article', () => {
	const articles = ref([])
	const article = ref(null)
	const isLoading = ref(false)
	const isGenerating = ref(false)
	const error = ref(null)
	const isSaving = ref(false)

	// Chat-related state
	const chats = ref([])
	const isLoadingChats = ref(false)
	const conversationId = ref(null)
	const newMessage = ref('')

	const fetchArticles = async () => {
		isLoading.value = true
		error.value = null

		try {
			const response = await api.get('/articles')
			articles.value = response
			return response
		} catch (err) {
			error.value = err.message || 'Failed to fetch articles'
			throw err
		} finally {
			isLoading.value = false
		}
	}

	const fetchArticle = async (id) => {
		isLoading.value = true
		error.value = null

		try {
			const response = await api.get(`/articles/${id}`)
			article.value = response
			return response
		} catch (err) {
			window.location.href = '/articles'
			throw err
		} finally {
			isLoading.value = false
		}
	}

	const createArticle = async (articleData) => {
		isLoading.value = true
		error.value = null

		try {
			const response = await api.post('/articles', articleData)
			await fetchArticles()
			return response
		} catch (err) {
			error.value = err.message || 'Failed to create article'
			throw err
		} finally {
			isLoading.value = false
		}
	}

	const updateArticle = async (id, articleData) => {
		isLoading.value = true
		error.value = null

		try {
			const response = await api.put(`/articles/${id}`, articleData)

			// Update the current article if it's loaded
			if (article.value && article.value.id === id) {
				article.value = response
			}

			// Refresh the articles list
			await fetchArticles()

			return response
		} catch (err) {
			error.value = err.message || 'Failed to update article'
			throw err
		} finally {
			isLoading.value = false
		}
	}

	const deleteArticle = async (id) => {
		isLoading.value = true
		error.value = null

		try {
			await api.delete(`/articles/${id}`)

			// Clear the current article if it's the one being deleted
			if (article.value && article.value.id === id) {
				article.value = null
			}

			// Refresh the articles list
			await fetchArticles()

			return true
		} catch (err) {
			error.value = err.message || 'Failed to delete article'
			throw err
		} finally {
			isLoading.value = false
		}
	}

	/**
	 * Generate an article for a prompt
	 */
	const generateArticle = async (promptId) => {
		isGenerating.value = true
		error.value = null

		try {
			const response = await api.post(`/prompts/${promptId}/generate-article`)
			return response.data
		} catch (err) {
			error.value = err.response?.data?.message || 'Failed to generate article'
			throw err
		} finally {
			isGenerating.value = false
		}
	}

	// Setup debounced watcher for auto-saving article changes
	watchDebounced(
		article,
		async (newArticle, oldArticle) => {
			// Only proceed if article exists with an ID
			if (!newArticle || !newArticle.id) return

			// Don't auto-save if we're already in a loading state
			if (isSaving.value) return

			// Skip initial load
			if (oldArticle === null) return

			console.log('Auto-saving article changes...')
			isSaving.value = true

			try {
				await api.put(`/articles/${newArticle.id}`, newArticle)
				console.log('Article auto-saved successfully')
			} catch (err) {
				error.value = 'Auto-save failed: ' + (err.message || 'Unknown error')
			} finally {
				isSaving.value = false
			}
		},
		{ debounce: 1000, maxWait: 5000, deep: true } // 1 second debounce, max 5 seconds
	)

	// Chat-related actions
	function setConversationId(id) {
		conversationId.value = id
		// Clear chats when changing conversations
		chats.value = []
	}

	async function fetchChats(articleId) {
		const id = articleId || (article.value ? article.value.id : null)
		if (!id) return

		try {
			let url = `/articles/${id}/chats`
			let params = {}

			// If we have a specific conversation ID, add it as a parameter
			if (conversationId.value) {
				params.conversation_id = conversationId.value
			}

			const response = await api.get(url, { params })
			// Ensure we handle the response format correctly
			chats.value = Array.isArray(response) ? response : []
			// Make sure each chat has the expected properties
			chats.value = chats.value.map((chat) => ({
				id: chat.id,
				role: chat.role,
				content: chat.content,
				created_at: chat.created_at,
				annotations: chat.annotations || []
			}))
		} catch (error) {
			console.error('Error fetching article chats:', error)
		}
	}

	async function sendMessage(content) {
		if (!article.value || !article.value.id) return

		isLoadingChats.value = true
		newMessage.value = ''

		// Add user message to chat immediately
		chats.value.push({
			role: 'user',
			content: content
		})

		try {
			let url = `/articles/${article.value.id}/chats`
			let payload = { content }

			// If we have a specific conversation ID, include it in the payload
			if (conversationId.value) {
				payload.conversation_id = conversationId.value
			}

			const response = await api.post(url, payload)

			// Add AI response to chat with all fields from the updated ChatService
			chats.value.push({
				id: response.id,
				role: response.role,
				content: response.content,
				created_at: response.created_at,
				annotations: response.annotations
			})
		} catch (error) {
			console.error('Error sending message:', error)
			// Add error message
			chats.value.push({
				role: 'assistant',
				content: 'Sorry, there was an error processing your request.'
			})
		} finally {
			isLoadingChats.value = false
		}
	}

	return {
		// Article state
		articles,
		article,
		isLoading,
		isGenerating,
		isSaving,
		error,
		fetchArticles,
		fetchArticle,
		createArticle,
		updateArticle,
		deleteArticle,
		generateArticle,

		// Chat state and actions
		chats,
		isLoadingChats: computed(() => isLoadingChats.value),
		conversationId: computed(() => conversationId.value),
		newMessage,
		setConversationId,
		fetchChats,
		sendMessage
	}
})
