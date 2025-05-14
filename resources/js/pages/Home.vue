<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import ChatMessage from '@/components/ChatMessage.vue';
import ChatInput from '@/components/ChatInput.vue';
import ChatLoadingIndicator from '@/components/ChatLoadingIndicator.vue';
import ConversationsList from '@/components/conversations/ConversationsList.vue';

const isLoading = ref(false);
const chats = ref([]);

async function handleSendMessage(content) {
    isLoading.value = true

    chats.value.push({
        role: 'user',
        content: content,
    });
    
    let response = await api.post(`/conversations/1/chats`, {
        content: content,
    });
    
    chats.value.push({
        role: 'assistant',
        content: response.content,
    })

    isLoading.value = false
}

function handleAction(action) {
    // Handle different actions (search, research, image)
    console.log(`Action triggered: ${action}`);
}

onMounted(async () => {
    chats.value = await api.get('/conversations/1/chats');
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
        <!-- <h2 class="text-2xl font-semibold mb-4">{{ conversation.title || 'Conversation' }}</h2> -->
        <h2 class="text-2xl font-semibold mb-4">Conversation title</h2>
        
        <div class="flex-grow overflow-y-auto mb-4 space-y-4">
          <ChatMessage 
            v-for="(chat, index) in chats" 
            :key="index" 
            :message="chat" 
          />
          
          <ChatLoadingIndicator v-if="isLoading" />
        </div>
        
        <div class="mt-auto">
          <ChatInput :is-loading="isLoading" @send="handleSendMessage" @action="handleAction" />
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
