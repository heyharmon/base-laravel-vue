import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useArticleChatStore = defineStore('articleChat', () => {
	// State
	const chats = ref([])
	const isLoading = ref(false)
	const articleId = ref(null)

	// Actions
	function setArticleId(id) {
		articleId.value = id
		// Clear chats when changing articles
		chats.value = []
	}

	async function fetchChats() {
		if (!articleId.value) return
		
		try {
			const response = await api.get(`/articles/${articleId.value}/chats`)
			chats.value = response || []
		} catch (error) {
			console.error('Error fetching article chats:', error)
		}
	}

	async function sendMessage(content) {
		if (!articleId.value) return
		
		isLoading.value = true

		// Add user message to chat immediately
		chats.value.push({
			role: 'user',
			content: content
		})

		try {
			const response = await api.post(`/articles/${articleId.value}/chats`, {
				content: content
			})

			// Add AI response to chat
			chats.value.push({
				role: 'assistant',
				content: response.content,
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
			isLoading.value = false
		}
	}

	return {
		// State
		chats: computed(() => chats.value),
		isLoading: computed(() => isLoading.value),
		articleId: computed(() => articleId.value),

		// Actions
		setArticleId,
		fetchChats,
		sendMessage
	}
})
