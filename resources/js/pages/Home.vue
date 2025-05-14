<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/services/api';
import { useConversationStore } from '@/stores/conversationStore';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import ChatMessage from '@/components/ChatMessage.vue';
import ChatInput from '@/components/ChatInput.vue';
import ChatLoadingIndicator from '@/components/ChatLoadingIndicator.vue';
import ConversationsList from '@/components/conversations/ConversationsList.vue';

const conversationStore = useConversationStore();
const isLoading = ref(false);
const chats = ref([]);

async function handleSendMessage(content) {
    if (!conversationStore.activeConversationId) return;
    
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

async function loadChats() {
    if (conversationStore.activeConversationId) {
        chats.value = await api.get(`/conversations/${conversationStore.activeConversationId}/chats`);
    }
}

watch(() => conversationStore.activeConversationId, async (newId) => {
    if (newId) {
        await loadChats();
    } else {
        chats.value = [];
    }
});

onMounted(async () => {
    await conversationStore.fetchConversations();
    await loadChats();
})
</script>

<template>
  <DefaultLayout>
    <div class="flex h-full">
      <!-- Left column - Conversations -->
      <div class="w-1/4 pr-4 border-r border-neutral-200 h-full">
        <ConversationsList />
      </div>
      
      <!-- Right column - Chat messages -->
      <div class="w-3/4 pl-4 flex flex-col h-full">
        <h2 class="text-2xl font-semibold mb-4">{{ conversationStore.activeConversation?.title || 'Untitled conversation' }}</h2>
        
        <div class="flex-grow overflow-y-auto mb-4 space-y-4">
          <ChatMessage 
            v-for="(chat, index) in chats" 
            :key="index" 
            :message="chat" 
          />
          
          <ChatLoadingIndicator v-if="isLoading" />
        </div>
        
        <div class="mt-auto">
          <ChatInput :is-loading="isLoading" @send="handleSendMessage" />
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
