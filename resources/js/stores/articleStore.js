import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import { watchDebounced } from '@vueuse/core'
import api from '@/services/api'

export const useArticleStore = defineStore('article', () => {
	const articles = ref([])
	const article = ref(null)
	const isLoading = ref(false)
	const isGenerating = ref(false)
	const error = ref(null)
	const autoSaveEnabled = ref(true)
	const isSaving = ref(false)

	const fetchArticles = async () => {
		isLoading.value = true
		error.value = null

		try {
			const response = await api.get('/articles')
			articles.value = response
			return response
		} catch (err) {
			error.value = err.message || 'Failed to fetch articles'
			console.error('Error fetching articles:', err)
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
			error.value = err.message || 'Failed to fetch article'
			console.error('Error fetching article:', err)
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
			console.error('Error creating article:', err)
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
			console.error('Error updating article:', err)
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
			console.error('Error deleting article:', err)
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
			// Only proceed if auto-save is enabled and article exists with an ID
			if (!autoSaveEnabled.value || !newArticle || !newArticle.id) return

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
				console.error('Error auto-saving article:', err)
				error.value = 'Auto-save failed: ' + (err.message || 'Unknown error')
			} finally {
				isSaving.value = false
			}
		},
		{ debounce: 1000, maxWait: 5000 } // 1 second debounce, max 5 seconds
	)

	// Toggle auto-save functionality
	const toggleAutoSave = () => {
		autoSaveEnabled.value = !autoSaveEnabled.value
		return autoSaveEnabled.value
	}

	return {
		articles,
		article,
		isLoading,
		isGenerating,
		isSaving,
		error,
		autoSaveEnabled,
		fetchArticles,
		fetchArticle,
		createArticle,
		updateArticle,
		deleteArticle,
		generateArticle,
		toggleAutoSave
	}
})
