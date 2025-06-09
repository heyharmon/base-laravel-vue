<script setup>
import { ref, computed } from 'vue'
import { useConversationStore } from '@/stores/conversationStore'

const conversationStore = useConversationStore()
const isOpen = ref(false)

const toggleDropdown = () => {
  isOpen.value = !isOpen.value
}

const closeDropdown = () => {
  isOpen.value = false
}

const selectConversation = (id) => {
  conversationStore.setActiveConversation(id)
  closeDropdown()
}
</script>

<template>
  <div class="relative">
    <!-- Dropdown trigger button -->
    <div 
      @click="toggleDropdown" 
      class="flex items-center justify-between w-full p-3 bg-neutral-100 hover:bg-neutral-200 rounded-md cursor-pointer transition-colors"
    >
      <div class="flex items-center space-x-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-message-square"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span class="font-medium truncate">{{ conversationStore.activeConversation?.title || 'Untitled conversation' }}</span>
      </div>
      <svg 
        xmlns="http://www.w3.org/2000/svg" 
        width="18" 
        height="18" 
        viewBox="0 0 24 24" 
        fill="none" 
        stroke="currentColor" 
        stroke-width="2" 
        stroke-linecap="round" 
        stroke-linejoin="round" 
        :class="['transition-transform', isOpen ? 'rotate-180' : '']"
      >
        <polyline points="6 9 12 15 18 9"></polyline>
      </svg>
    </div>

    <!-- Dropdown menu -->
    <div 
      v-if="isOpen" 
      class="absolute z-10 w-full mt-1 bg-white border border-neutral-200 rounded-md shadow-lg max-h-60 overflow-y-auto no-scrollbar"
    >
      <div class="p-2 border-b border-neutral-200">
        <button 
          @click="conversationStore.createConversation('')" 
          class="flex items-center w-full p-2 text-left hover:bg-neutral-100 rounded-md"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
          New conversation
        </button>
      </div>
      
      <div class="p-2">
        <div 
          v-for="conversation in conversationStore.conversations" 
          :key="conversation.id"
          @click="selectConversation(conversation.id)"
          :class="conversation.id === conversationStore.activeConversationId ? 'bg-neutral-200' : 'hover:bg-neutral-100'"
          class="p-2 rounded-md cursor-pointer transition-colors"
        >
          <div class="font-medium truncate">{{ conversation.title || 'Untitled' }}</div>
          <div class="text-xs text-neutral-500 truncate">{{ new Date(conversation.created_at).toLocaleString() }}</div>
        </div>
      </div>
    </div>
  </div>
</template>
