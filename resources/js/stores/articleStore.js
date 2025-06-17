import { defineStore } from 'pinia'
import { ref, watch, computed } from 'vue'
import { useDebounceFn } from '@vueuse/core'
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
		isLoading.value = true

		try {
			const response = await api.get(`/articles/${id}`)
			article.value = response
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
		try {
			let response = await api.post(`/articles/${articleId}/versions/${versionId}/revert`)
			// window.location.reload()
			article.value = response
		} catch (err) {
			console.error('Error reverting article version:', err)
			throw err
		}
	}

	/**
	 * Generate an article for a prompt
	 */
	const generateArticle = async (promptId) => {
		isGenerating.value = true

		try {
			const response = await api.post(`/prompts/${promptId}/generate-article`)
			return response.data
		} catch (err) {
			console.error('Error generating article:', err)
			throw err
		} finally {
			isGenerating.value = false
		}
	}

	// Create a debounced save function
	const saveArticle = async (articleData) => {
		if (isSaving.value) return
		isSaving.value = true

		try {
			const response = await api.put(`/articles/${articleData.id}`, articleData)
			article.value = response
			console.log('Article auto-saved successfully')
		} finally {
			isSaving.value = false
		}
	}

	// Create a debounced version of the save function (4 second debounce)
	const debouncedSave = useDebounceFn(saveArticle, 4000)

	// Track previous values of important fields
	const previousValues = ref({
		title: '',
		meta_title: '',
		meta_description: '',
		schema: '',
		content: ''
	})

	// Setup watcher for auto-saving article changes
	watch(
		article,
		(newArticle, oldArticle) => {
			// Only proceed if article exists with an ID
			if (!newArticle || !newArticle.id) return

			// Skip initial load
			if (oldArticle === null) {
				// Initialize previous values
				previousValues.value = {
					title: newArticle.title || '',
					meta_title: newArticle.meta_title || '',
					meta_description: newArticle.meta_description || '',
					schema: newArticle.schema || '',
					content: newArticle.content || ''
				}
				return
			}

			// Check if important fields have changed
			const hasImportantChanges =
				newArticle.title !== previousValues.value.title ||
				newArticle.meta_title !== previousValues.value.meta_title ||
				newArticle.meta_description !== previousValues.value.meta_description ||
				newArticle.schema !== previousValues.value.schema ||
				newArticle.content !== previousValues.value.content

			// Update previous values
			previousValues.value = {
				title: newArticle.title || '',
				meta_title: newArticle.meta_title || '',
				meta_description: newArticle.meta_description || '',
				schema: newArticle.schema || '',
				content: newArticle.content || ''
			}

			if (hasImportantChanges) {
				console.log('Important fields changed, triggering auto-save')
				debouncedSave(newArticle)
			} else {
				console.log('No changes to important fields, skipping auto-save')
			}
		},
		{ deep: true }
	)

	// Chat-related actions
	function setConversationId(id) {
		conversationId.value = id
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
		fetchArticles,
		fetchArticle,
		createArticle,
		updateArticle,
		deleteArticle,
		generateArticle,

		// Version state and actions
		articleVersions,
		isLoadingVersions,
		revertToVersion,

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
