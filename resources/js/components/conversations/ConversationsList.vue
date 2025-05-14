<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';

const conversation = ref(null);
const conversations = ref([]);
const activeConversationId = ref(null);

async function createNewConversation() {
    const newConversation = await api.post('/conversations', {
        title: 'New Conversation'
    });

    conversations.value.unshift(newConversation);
    setActiveConversation(newConversation.id);
}

async function setActiveConversation(id) {
    activeConversationId.value = id;
    conversation.value = await api.get(`/conversations/${id}`);
    // chats.value = await api.get(`/conversations/${id}/chats`);
}

onMounted(async () => {
    conversations.value = await api.get('/conversations');
    setActiveConversation(conversations.value[0].id);
});
</script>

<template>
  <div>
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-semibold">Conversations</h2>
      <button 
        @click="createNewConversation" 
        class="bg-neutral-800 text-white px-3 py-1 rounded-md text-sm hover:bg-neutral-700"
      >
        New
      </button>
    </div>
    
    <div class="space-y-2 overflow-y-auto max-h-[calc(100vh-12rem)]">
      <div 
        v-for="conversation in conversations" 
        :key="conversation.id" 
        @click="setActiveConversation(conversation.id)" 
        class="p-3 rounded-md cursor-pointer transition-colors" 
        :class="activeConversationId === conversation.id ? 'bg-neutral-200' : 'hover:bg-neutral-100'"
      >
        <div class="font-medium truncate">{{ conversation.title || 'Untitled' }}</div>
        <div class="text-xs text-neutral-500 truncate">{{ new Date(conversation.created_at).toLocaleString() }}</div>
      </div>
    </div>
  </div>
</template>
