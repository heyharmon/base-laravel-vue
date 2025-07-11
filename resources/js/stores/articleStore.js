import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useArticleStore = defineStore('article', () => {
	const articles = ref([])
	const article = ref(null)
	const isLoading = ref(false)
	const isGenerating = ref(false)
	const isSaving = ref(false)

	// Version-related state
	const articleVersions = ref([])
	const isLoadingVersions = ref(false)

	// Chat-related state
	const chats = ref([])
	const isLoadingChats = ref(false)
	const conversationId = ref(null)
	const newMessage = ref('')

	const fetchArticles = async () => {
		console.log('Fetching articles...')
		isLoading.value = true

		try {
			const response = await api.get('/articles')
			articles.value = response
			return response
		} catch (err) {
			console.error('Error fetching articles:', err)
			throw err
		} finally {
			isLoading.value = false
		}
	}

	const fetchArticle = async (id) => {
		console.log('Fetching article...')
		isLoading.value = true

		try {
			const response = await api.get(`/articles/${id}`)
			article.value = response

			// Reset conversation ID when switching to a new article
			conversationId.value = null
			chats.value = []

			return response
		} catch (err) {
			window.location.href = '/articles'
			console.error('Error fetching article:', err)
			throw err
		} finally {
			isLoading.value = false
		}
	}

	const createArticle = async (articleData) => {
		console.log('Creating article...')
		isLoading.value = true

		try {
			const response = await api.post('/articles', articleData)
			await fetchArticles()
			return response
		} catch (err) {
			console.error('Error creating article:', err)
			throw err
		} finally {
			isLoading.value = false
		}
	}

	const updateArticle = async (id, articleData) => {
		console.log('Updating article...')
		isLoading.value = true

		try {
			const response = await api.put(`/articles/${id}`, articleData)

			// Update article versions ref
			if (article.value && article.value.id === id) {
				articleVersions.value = response.versions
			}

			return response
		} catch (err) {
			console.error('Error updating article:', err)
			throw err
		} finally {
			isLoading.value = false
		}
	}

	const deleteArticle = async (id) => {
		console.log('Deleting article...')
		isLoading.value = true

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
			console.error('Error deleting article:', err)
			throw err
		} finally {
			isLoading.value = false
		}
	}

	/**
	 * Revert an article to a specific version
	 */
	const revertToVersion = async (articleId, versionId) => {
		console.log('Reverting article to version...')
		try {
			let response = await api.post(`/articles/${articleId}/versions/${versionId}/revert`)
			// window.location.reload()
			article.value = response
		} catch (err) {
			console.error('Error reverting article version:', err)
			throw err
		}
	}

	// Auto-save content only (to be called from components)
	const autoSaveContent = async (articleId, content) => {
		console.log('Auto-saving article content...')
		if (isSaving.value) return
		isSaving.value = true

		try {
			const response = await api.put(`/articles/${articleId}`, { content })

			// Only update if the article exists and the content is actually different
			if (article.value && article.value.id === articleId) {
				// Only update content if it's actually different (shouldn't happen for auto-save)
				if (article.value.content !== response.content) {
					article.value.content = response.content
				}

				// Only update fields that might have changed on the server
				// Don't update content since it should already match what we sent
				article.value.current_version = response.current_version
				article.value.versions = response.versions
				article.value.updated_at = response.updated_at
			}

			return response
		} catch (err) {
			console.error('Error auto-saving article content:', err)
			throw err
		} finally {
			setTimeout(() => {
				isSaving.value = false
			}, 500)
		}
	}

	// Chat-related actions
	function setConversationId(id) {
		console.log('Setting conversation ID...')
		conversationId.value = id
		chats.value = []
	}

	async function fetchChats(articleId) {
		console.log('Fetching article chats...')
		const id = articleId || (article.value ? article.value.id : null)
		if (!id) return

		try {
			let params = {}

			// If we have a specific conversation ID, add it as a parameter
			if (conversationId.value) {
				params.conversation_id = conversationId.value
			}

			const response = await api.get(`/articles/${id}/chats`, { params })
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

	async function sendMessage(content, context = null) {
		console.log('Sending message...')
		if (!article.value || !article.value.id) return

		// Set loading state immediately when sending
		isLoadingChats.value = true
		newMessage.value = ''

		// Add user message to chat immediately
		chats.value.push({
			role: 'user',
			content: content
		})

		try {
			let payload = { content }

			// If we have a specific conversation ID, include it in the payload
			if (conversationId.value) {
				payload.conversation_id = conversationId.value
			}

			// Add context if provided
			if (context) {
				payload.context = context
			}

			const response = await api.post(`/articles/${article.value.id}/chats`, payload)

			// Note: Don't set isLoadingChats to false here
			// It will be reset by the ArticleChatAgentFinished event
		} catch (error) {
			console.error('Error sending message:', error)

			// Reset loading state on API error
			isLoadingChats.value = false

			// Add error message
			chats.value.push({
				role: 'assistant',
				content: 'Sorry, there was an error processing your request.'
			})
		}
	}

	return {
		// Article state
		articles,
		article,
		isLoading,
		isGenerating,
		isSaving,
		fetchArticles,
		fetchArticle,
		createArticle,
		updateArticle,
		deleteArticle,
		autoSaveContent,

		// Version state and actions
		articleVersions,
		isLoadingVersions,
		revertToVersion,

		// Chat state and actions
		chats,
		isLoadingChats,
		conversationId,
		newMessage,
		setConversationId,
		fetchChats,
		sendMessage
	}
})
