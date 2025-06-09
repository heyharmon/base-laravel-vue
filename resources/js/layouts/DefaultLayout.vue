<script setup>
import { onMounted, watch } from 'vue'
import { useConversationStore } from '@/stores/conversationStore'
import { useChatStore } from '@/stores/chatStore'
import AppNav from '@/components/AppNav.vue'
import ChatMessage from '@/components/ChatMessage.vue'
import ChatInput from '@/components/ChatInput.vue'
import ChatLoadingIndicator from '@/components/ChatLoadingIndicator.vue'
import ConversationsDropdown from '@/components/conversations/ConversationsDropdown.vue'

const conversationStore = useConversationStore()
const chatStore = useChatStore()

watch(
  () => conversationStore.activeConversationId,
  async (newId) => {
    if (newId) {
      await chatStore.fetchChats(conversationStore.activeConversationId)
    }
  }
)

onMounted(async () => {
  await conversationStore.fetchConversations()
})
</script>

<template>
  <div class="min-h-screen bg-white">
    <AppNav />
    
    <div class="flex h-[calc(100vh-4rem)]">
      <!-- Left sidebar - Chat -->
      <div class="w-1/4 border-r border-neutral-200 flex flex-col">
        <div class="p-4 border-b border-neutral-200">
          <ConversationsDropdown />
        </div>
        
        <div class="flex-grow flex flex-col p-4 h-full overflow-hidden">
          <h2 class="text-xl font-semibold mb-4">{{ conversationStore.activeConversation?.title || 'Untitled conversation' }}</h2>
          
          <div class="flex-grow mb-4 space-y-4 overflow-y-auto no-scrollbar">
            <ChatMessage v-for="(chat, index) in chatStore.chats" :key="index" :chat="chat" />
            <ChatLoadingIndicator v-if="chatStore.isLoading" />
          </div>
          
          <div class="mt-auto">
            <ChatInput :is-loading="chatStore.isLoading" @send="chatStore.sendMessage($event)" />
          </div>
        </div>
      </div>
      
      <!-- Main content area -->
      <div class="w-3/4 overflow-y-auto">
        <main class="p-6">
          <slot />
        </main>
      </div>
    </div>
  </div>
</template>
