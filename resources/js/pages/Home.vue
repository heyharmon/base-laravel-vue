<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import ChatMessage from '@/components/ChatMessage.vue';
import ChatInput from '@/components/ChatInput.vue';
import ChatLoadingIndicator from '@/components/ChatLoadingIndicator.vue';

const isLoading = ref(false);
const conversation = ref(null);
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
    conversation.value = await api.get(`/conversations/1`);
    chats.value = await api.get(`/conversations/1/chats`);
});
</script>

<template>
  <DefaultLayout v-if="conversation && chats">
    <h2 class="text-2xl font-semibold mb-4">Conversation</h2>

    <ChatMessage 
        v-for="(chat, index) in chats" 
        :key="index" 
        :message="chat" 
    />
    
    <ChatLoadingIndicator v-if="isLoading" />

    <ChatInput :is-loading="isLoading" @send="handleSendMessage" @action="handleAction" />
  </DefaultLayout>
</template>
