import { defineStore } from 'pinia';
import api from '@/services/api';

export const useCategoryStore = defineStore('category', {
  state: () => ({
    categories: [],
    searchedCategories: [],
    currentCategory: null,
    isLoading: false,
    error: null
  }),

  actions: {
    async fetchCategories() {
      this.isLoading = true;
      this.error = null;

      try {
        const response = await api.get('/categories');
        this.categories = response;
      } catch (error) {
        this.error = error.message || 'Failed to fetch categories';
        console.error('Error fetching categories:', error);
      } finally {
        this.isLoading = false;
      }
    },

    async searchCategories(searchTerm) {
      try {
        const response = await api.get('/categories', {
          params: { search: searchTerm }
        });
        this.searchedCategories = response;
        return response;
      } catch (error) {
        console.error('Error searching categories:', error);
        return [];
      }
    },

    async createCategory(categoryData) {
      this.isLoading = true;
      this.error = null;

      try {
        const response = await api.post('/categories', categoryData);

        const existingIndex = this.categories.findIndex(c => c.id === response.id);
        if (existingIndex === -1) {
          this.categories.push(response);
        }

        return response;
      } catch (error) {
        this.error = error.message || 'Failed to create category';
        console.error('Error creating category:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },

    async updateCategory(categoryId, categoryData) {
      this.isLoading = true;
      this.error = null;

      try {
        const response = await api.put(`/categories/${categoryId}`, categoryData);
        const index = this.categories.findIndex(c => c.id === categoryId);
        if (index !== -1) {
          this.categories[index] = response;
        }
        return response;
      } catch (error) {
        this.error = error.message || 'Failed to update category';
        console.error('Error updating category:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },

    async deleteCategory(categoryId) {
      this.isLoading = true;
      this.error = null;

      try {
        await api.delete(`/categories/${categoryId}`);
        this.categories = this.categories.filter(c => c.id !== categoryId);
      } catch (error) {
        this.error = error.message || 'Failed to delete category';
        console.error('Error deleting category:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    }
  }
});
