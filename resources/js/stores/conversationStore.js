import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/services/api';

export const useConversationStore = defineStore('conversation', () => {
  // State
  const conversations = ref([]);
  const activeConversation = ref(null);
  const activeConversationId = ref(null);
  
  // Actions
  async function fetchConversations() {
    conversations.value = await api.get('/conversations');
      
    if (conversations.value.length > 0 && !activeConversationId.value) {
        setActiveConversation(conversations.value[0].id);
    }
  }
  
  async function setActiveConversation(id) {
    activeConversationId.value = id;
    activeConversation.value = await api.get(`/conversations/${id}`);
  }
  
  async function createConversation(title = 'New Conversation') {
    const newConversation = await api.post('/conversations', { title });
    conversations.value.unshift(newConversation);
    setActiveConversation(newConversation.id);

    return newConversation;
  }
  
  async function updateConversation(id, data) {
    const updatedConversation = await api.put(`/conversations/${id}`, data);
      
    const index = conversations.value.findIndex(c => c.id === id);
    if (index !== -1) {
      conversations.value[index] = updatedConversation;
    }
    
    if (activeConversationId.value === id) {
      activeConversation.value = updatedConversation;
    }
    
    return updatedConversation;
  }

  return {
    // State
    conversations,
    activeConversation,
    activeConversationId,
    
    // Actions
    fetchConversations,
    setActiveConversation,
    createConversation,
    updateConversation,
  };
});
