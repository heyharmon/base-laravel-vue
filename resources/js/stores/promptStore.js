import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/services/api';

export const usePromptStore = defineStore('prompts', () => {
  // State
  const prompts = ref([]);
  const isLoading = ref(false);
  
  // Actions
  async function fetchPrompts() {
    isLoading.value = true;
    try {
      prompts.value = await api.get('/prompts');
    } catch (error) {
      console.error('Error fetching prompts:', error);
    } finally {
      isLoading.value = false;
    }
  }
  
  async function createPrompt(data) {
    isLoading.value = true;
    try {
      const newPrompt = await api.post('/prompts', data);
      prompts.value.unshift(newPrompt);
      return newPrompt;
    } catch (error) {
      console.error('Error creating prompt:', error);
      throw error;
    } finally {
      isLoading.value = false;
    }
  }
  
  async function updatePrompt(id, data) {
    isLoading.value = true;
    try {
      const updatedPrompt = await api.put(`/prompts/${id}`, data);
      
      const index = prompts.value.findIndex(p => p.id === id);
      if (index !== -1) {
        prompts.value[index] = updatedPrompt;
      }
      
      return updatedPrompt;
    } catch (error) {
      console.error('Error updating prompt:', error);
      throw error;
    } finally {
      isLoading.value = false;
    }
  }
  
  async function deletePrompt(id) {
    isLoading.value = true;
    try {
      await api.delete(`/prompts/${id}`);
      prompts.value = prompts.value.filter(p => p.id !== id);
    } catch (error) {
      console.error('Error deleting prompt:', error);
      throw error;
    } finally {
      isLoading.value = false;
    }
  }
  
  async function runPrompt(id) {
    isLoading.value = true;
    try {
      return await api.post(`/prompts/${id}/run`);
    } catch (error) {
      console.error('Error running prompt:', error);
      throw error;
    } finally {
      isLoading.value = false;
    }
  }

  return {
    // State
    prompts: computed(() => prompts.value),
    isLoading,
    
    // Actions
    fetchPrompts,
    createPrompt,
    updatePrompt,
    deletePrompt,
    runPrompt,
  };
});
