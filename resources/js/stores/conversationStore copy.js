import { defineStore } from 'pinia';
import api from '@/services/api';

export const useConversationStore = defineStore('conversation', {
  state: () => ({
    conversations: [],
    activeConversation: null,
    activeConversationId: null,
    loading: false,
    error: null
  }),
  
  getters: {
    getConversations: (state) => state.conversations,
    getActiveConversation: (state) => state.activeConversation,
    getActiveConversationId: (state) => state.activeConversationId,
    isLoading: (state) => state.loading
  },
  
  actions: {
    async fetchConversations() {
      this.loading = true;
      try {
        this.conversations = await api.get('/conversations');
        this.loading = false;
        
        if (this.conversations.length > 0 && !this.activeConversationId) {
          this.setActiveConversation(this.conversations[0].id);
        }
      } catch (error) {
        this.error = error;
        this.loading = false;
      }
    },
    
    async setActiveConversation(id) {
      this.loading = true;
      this.activeConversationId = id;
      
      try {
        this.activeConversation = await api.get(`/conversations/${id}`);
        this.loading = false;
      } catch (error) {
        this.error = error;
        this.loading = false;
      }
    },
    
    async createConversation(title = 'New Conversation') {
      this.loading = true;
      
      try {
        const newConversation = await api.post('/conversations', { title });
        this.conversations.unshift(newConversation);
        this.setActiveConversation(newConversation.id);
        this.loading = false;
        return newConversation;
      } catch (error) {
        this.error = error;
        this.loading = false;
      }
    },
    
    async updateConversation(id, data) {
      this.loading = true;
      
      try {
        const updatedConversation = await api.put(`/conversations/${id}`, data);
        
        const index = this.conversations.findIndex(c => c.id === id);
        if (index !== -1) {
          this.conversations[index] = updatedConversation;
        }
        
        if (this.activeConversationId === id) {
          this.activeConversation = updatedConversation;
        }
        
        this.loading = false;
        return updatedConversation;
      } catch (error) {
        this.error = error;
        this.loading = false;
      }
    },
    
    async deleteConversation(id) {
      this.loading = true;
      
      try {
        await api.delete(`/conversations/${id}`);
        
        this.conversations = this.conversations.filter(c => c.id !== id);
        
        if (this.activeConversationId === id) {
          this.activeConversation = null;
          this.activeConversationId = null;
          
          if (this.conversations.length > 0) {
            this.setActiveConversation(this.conversations[0].id);
          }
        }
        
        this.loading = false;
      } catch (error) {
        this.error = error;
        this.loading = false;
      }
    }
  }
});
