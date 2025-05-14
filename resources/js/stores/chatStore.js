import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { useConversationStore } from '@/stores/conversationStore';
import api from '@/services/api';

export const useChatStore = defineStore('chats', () => {
  // State
  const chats = ref([]);
  const isLoading = ref(false);

  // Shared state
  const conversationStore = useConversationStore()
  
  // Actions
  async function fetchChats(conversationId) {
    console.log('fetching chats...')
    chats.value = await api.get(`/conversations/${conversationId}/chats`);
  }
  
  async function sendMessage(content) {
    isLoading.value = true;

    chats.value.push({
        role: 'user',
        content: content,
    });
    
    let response = await api.post(`/conversations/${conversationStore.activeConversationId}/chats`, {
        content: content,
    });
    
    chats.value.push({
        role: 'assistant',
        content: response.content,
    });

    isLoading.value = false;
  }

  return {
    // State
    chats: computed(() => chats.value),
    isLoading: computed(() => isLoading.value),
    
    // Actions
    fetchChats,
    sendMessage,
  };
});
