import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const useArticleStore = defineStore('article', () => {
  const articles = ref([])
  const article = ref(null)
  const isLoading = ref(false)
  const error = ref(null)

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

  return {
    articles,
    article,
    isLoading,
    error,
    fetchArticles,
    fetchArticle,
    createArticle,
    updateArticle,
    deleteArticle
  }
})
