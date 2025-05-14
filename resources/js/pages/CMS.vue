<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import ChatMessage from '@/components/ChatMessage.vue';
import ChatInput from '@/components/ChatInput.vue';
import ChatLoadingIndicator from '@/components/ChatLoadingIndicator.vue';

const isLoading = ref(false);

const conversations = ref([]);
const conversation = ref(null);
const chats = ref([]);
const activeConversationId = ref(1);

const pages = ref([]);
const page = ref(null);
const activePageId = ref(1);

async function handleSendMessage(content) {
    isLoading.value = true

    chats.value.push({
        role: 'user',
        content: content,
    });
    
    let response = await api.post(`/conversations/${activeConversationId.value}/chats`, {
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

async function createNewConversation() {
    const newConversation = await api.post('/conversations', {
        title: 'New Conversation'
    });
    conversations.value.push(newConversation);
    setActiveConversation(newConversation.id);
}

async function setActiveConversation(id) {
    activeConversationId.value = id;
    conversation.value = await api.get(`/conversations/${id}`);
    chats.value = await api.get(`/conversations/${id}/chats`);
}

async function createNewPage() {
    const newPage = await api.post('/websites/1/pages', {
        title: 'My New Page'
    });
    pages.value.push(newPage);
    setActivePage(newPage.id);
}

async function setActivePage(id) {
    activePageId.value = id;
    page.value = await api.get(`/websites/1/pages/${id}`);
    pages.value = await api.get(`/websites/1/pages`);
}

onMounted(async () => {
    conversations.value = await api.get('/conversations');
    setActiveConversation(activeConversationId.value);

    pages.value = await api.get('/websites/1/pages');
    setActivePage(activePageId.value);
});
</script>

<template>
  <DefaultLayout v-if="conversation && chats">
    <div class="flex h-full">
      <!-- Left column - Conversation list -->
      <div class="w-1/4 pr-4 border-r border-neutral-200 h-full space-y-4">
        <!-- Conversations -->
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
                    v-for="conv in conversations" 
                    :key="conv.id" 
                    @click="setActiveConversation(conv.id)" 
                    class="p-3 rounded-md cursor-pointer transition-colors" 
                    :class="activeConversationId === conv.id ? 'bg-neutral-200' : 'hover:bg-neutral-100'"
                >
                    <div class="font-medium truncate">{{ conv.title || 'Untitled' }}</div>
                    <div class="text-xs text-neutral-500 truncate">{{ new Date(conv.created_at).toLocaleString() }}</div>
                </div>
            </div>
        </div>

        <!-- Pages -->
        <div>
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Pages</h2>
                <button 
                    @click="createNewPage" 
                    class="bg-neutral-800 text-white px-3 py-1 rounded-md text-sm hover:bg-neutral-700"
                >
                    New 
                </button>
            </div>
            
            <div class="space-y-2 overflow-y-auto max-h-[calc(100vh-12rem)]">
                <div 
                    v-for="page in pages" 
                    :key="page.id" 
                    @click="setActivePage(page.id)"
                    class="p-3 rounded-md cursor-pointer transition-colors" 
                    :class="activePageId === page.id ? 'bg-neutral-200' : 'hover:bg-neutral-100'"
                >
                    <div class="font-medium truncate">{{ page.title || 'Untitled' }}</div>
                </div>
            </div>
        </div>
      </div>
      
      <!-- Right column - Chat messages -->
      <div class="w-3/4 pl-4 flex flex-col h-full">
        <h2 class="text-2xl font-semibold mb-4">{{ conversation.title || 'Conversation' }}</h2>
        
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
