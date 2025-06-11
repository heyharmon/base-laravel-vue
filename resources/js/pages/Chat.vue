<script setup>
import { onMounted, watch } from 'vue'
import { useConversationStore } from '@/stores/conversationStore'
import { useChatStore } from '@/stores/chatStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import ChatMessage from '@/components/ChatMessage.vue'
import ChatInput from '@/components/ChatInput.vue'
import ChatLoadingIndicator from '@/components/ChatLoadingIndicator.vue'
import ConversationsList from '@/components/conversations/ConversationsList.vue'

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
</script>

<template>
	<DefaultLayout>
		<div class="flex h-[calc(100vh-4rem)]">
			<!-- Left column - Conversations -->
			<div class="w-1/4 pr-4 py-4 border-r border-neutral-200 h-full">
				<ConversationsList />
			</div>

			<!-- Right column - Chat messages -->
			<div class="w-3/4 pl-4 py-4 flex flex-col">
				<h2 class="text-2xl font-semibold mb-4">{{ conversationStore.activeConversation?.title || 'Untitled conversation' }}</h2>

				<div class="flex-grow mb-4 space-y-4 overflow-y-auto no-scrollbar">
					<ChatMessage v-for="(chat, index) in chatStore.chats" :key="index" :chat="chat" />

					<ChatLoadingIndicator v-if="chatStore.isLoading" />
				</div>

				<div class="mt-auto">
					<ChatInput :is-loading="chatStore.isLoading" @send="chatStore.sendMessage($event)" />
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
