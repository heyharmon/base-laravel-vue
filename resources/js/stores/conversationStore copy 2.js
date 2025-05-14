import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/services/api';

export const useConversationStore = defineStore('conversation', () => {
  // State
  const conversations = ref([]);
  const activeConversation = ref(null);
  const activeConversationId = ref(null);
  const loading = ref(false);
  const error = ref(null);
  
  // Getters
  const getConversations = computed(() => conversations.value);
  const getActiveConversation = computed(() => activeConversation.value);
  const getActiveConversationId = computed(() => activeConversationId.value);
  const isLoading = computed(() => loading.value);
  
  // Actions
  async function fetchConversations() {
    loading.value = true;
    try {
      conversations.value = await api.get('/conversations');
      loading.value = false;
      
      if (conversations.value.length > 0 && !activeConversationId.value) {
        setActiveConversation(conversations.value[0].id);
      }
    } catch (err) {
      error.value = err;
      loading.value = false;
    }
  }
  
  async function setActiveConversation(id) {
    loading.value = true;
    activeConversationId.value = id;
    
    try {
      activeConversation.value = await api.get(`/conversations/${id}`);
      loading.value = false;
    } catch (err) {
      error.value = err;
      loading.value = false;
    }
  }
  
  async function createConversation(title = 'New Conversation') {
    loading.value = true;
    
    try {
      const newConversation = await api.post('/conversations', { title });
      conversations.value.unshift(newConversation);
      setActiveConversation(newConversation.id);
      loading.value = false;
      return newConversation;
    } catch (err) {
      error.value = err;
      loading.value = false;
    }
  }
  
  async function updateConversation(id, data) {
    loading.value = true;
    
    try {
      const updatedConversation = await api.put(`/conversations/${id}`, data);
      
      const index = conversations.value.findIndex(c => c.id === id);
      if (index !== -1) {
        conversations.value[index] = updatedConversation;
      }
      
      if (activeConversationId.value === id) {
        activeConversation.value = updatedConversation;
      }
      
      loading.value = false;
      return updatedConversation;
    } catch (err) {
      error.value = err;
      loading.value = false;
    }
  }

  return {
    // State
    conversations,
    activeConversation,
    activeConversationId,
    loading,
    error,
    
    // Getters
    getConversations,
    getActiveConversation,
    getActiveConversationId,
    isLoading,
    
    // Actions
    fetchConversations,
    setActiveConversation,
    createConversation,
    updateConversation,
  };
});
