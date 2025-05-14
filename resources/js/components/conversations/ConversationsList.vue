<script setup>
import { onMounted } from 'vue';
import { useConversationStore } from '@/stores/conversationStore';

const conversationStore = useConversationStore();

onMounted(async () => {
    await conversationStore.fetchConversations();
});
</script>

<template>
  <div>
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-semibold">Conversations</h2>
      <button @click="conversationStore.createConversation('')" class="bg-neutral-800 text-white px-3 py-1 rounded-md text-sm hover:bg-neutral-700">
        New
      </button>
    </div>
    
    <div class="space-y-2 overflow-y-auto max-h-[calc(100vh-12rem)]">
      <div 
        v-for="conversation in conversationStore.conversations" :key="conversation.id" 
        @click="conversationStore.setActiveConversation(conversation.id)" 
        :class="conversationStore.activeConversationId === conversation.id ? 'bg-neutral-200' : 'hover:bg-neutral-100'"
        class="p-3 rounded-md cursor-pointer transition-colors" 
      >
        <div class="font-medium truncate">{{ conversation.title || 'Untitled' }}</div>
        <div class="text-xs text-neutral-500 truncate">{{ new Date(conversation.created_at).toLocaleString() }}</div>
      </div>
    </div>
  </div>
</template>
