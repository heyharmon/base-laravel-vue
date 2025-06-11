<script setup>
import { ref, watch } from 'vue'
import { useArticleChatStore } from '@/stores/articleChatStore'
import { DropdownMenuRoot, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuPortal, DropdownMenuItem } from 'reka-ui'
import api from '@/services/api'

const props = defineProps({
	articleId: {
		type: [Number, String, null],
		default: null
	}
})

const emit = defineEmits(['conversationChanged'])

const articleChatStore = useArticleChatStore()
const conversations = ref([])
const isLoading = ref(false)
const activeConversationId = ref(null)
const activeConversationTitle = ref('AI Chat Assistant')
const isOpen = ref(false)

// Fetch conversations for this article
const fetchConversations = async () => {
	if (!props.articleId) {
		conversations.value = []
		return
	}

	isLoading.value = true
	try {
		const response = await api.get(`/articles/${props.articleId}/conversations`)
		conversations.value = Array.isArray(response) ? response : []

		// Set active conversation if we have conversations
		if (conversations.value.length > 0 && !activeConversationId.value) {
			setActiveConversation(conversations.value[0].id)
		}
	} catch (error) {
		console.error('Error fetching article conversations:', error)
		conversations.value = []
	} finally {
		isLoading.value = false
	}
}

// Set the active conversation
const setActiveConversation = (id) => {
	activeConversationId.value = id
	const conversation = conversations.value.find((c) => c.id === id)
	if (conversation) {
		activeConversationTitle.value = conversation.title || 'Untitled Conversation'
		articleChatStore.setConversationId(id)
		emit('conversationChanged', id)
	}
}

// Create a new conversation
const createNewConversation = async () => {
	if (!props.articleId) {
		console.warn('Cannot create conversation: No article ID provided')
		return
	}

	isLoading.value = true
	try {
		const newConversation = await api.post(`/articles/${props.articleId}/conversations`, {
			title: `Chat for article: ${new Date().toLocaleString()}`
		})

		conversations.value.unshift(newConversation)
		setActiveConversation(newConversation.id)
		isOpen.value = false
	} catch (error) {
		console.error('Error creating new conversation:', error)
	} finally {
		isLoading.value = false
	}
}

// Watch for article ID changes
watch(
	() => props.articleId,
	(newId) => {
		if (newId) {
			fetchConversations()
		}
	},
	{ immediate: true }
)

// Format date for display
const formatDate = (dateString) => {
	return new Date(dateString).toLocaleString()
}
</script>

<template>
	<div class="flex items-center justify-between mb-4" v-if="props.articleId">
		<DropdownMenuRoot v-model:open="isOpen">
			<DropdownMenuTrigger class="max-w-2/3 flex items-center gap-1 text-lg font-medium hover:bg-neutral-100 rounded-md px-2 py-1" :disabled="isLoading">
				<span class="truncate">{{ activeConversationTitle }}</span>
				<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
					<path
						fill-rule="evenodd"
						d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
						clip-rule="evenodd"
					/>
				</svg>
			</DropdownMenuTrigger>

			<DropdownMenuPortal>
				<DropdownMenuContent class="bg-white rounded-md shadow-lg border border-neutral-200 min-w-[240px] max-w-[500px] z-50">
					<div class="p-1">
						<button
							@click="createNewConversation"
							class="flex items-center w-full gap-2 px-3 py-2 text-left rounded-md hover:bg-neutral-100"
							:disabled="isLoading"
						>
							<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
								<path
									fill-rule="evenodd"
									d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
									clip-rule="evenodd"
								/>
							</svg>
							<span>New Conversation</span>
						</button>

						<div class="h-px bg-neutral-200 my-1"></div>

						<div v-if="isLoading" class="p-3 text-center text-neutral-500">Loading conversations...</div>

						<div v-else-if="conversations.length === 0" class="p-3 text-center text-neutral-500">No conversations yet</div>

						<div v-else class="max-h-[240px] overflow-y-auto">
							<DropdownMenuItem
								v-for="conversation in conversations"
								:key="conversation.id"
								@click="setActiveConversation(conversation.id)"
								class="flex items-center justify-between gap-2 px-3 py-2 text-left rounded-md hover:bg-neutral-100 cursor-pointer"
							>
								<div class="flex-1 min-w-0">
									<div class="font-medium truncate">{{ conversation.title || 'Untitled' }}</div>
									<div class="text-xs text-neutral-500 truncate">{{ formatDate(conversation.created_at) }}</div>
								</div>
								<svg
									v-if="conversation.id === activeConversationId"
									xmlns="http://www.w3.org/2000/svg"
									class="h-4 w-4 text-neutral-900"
									viewBox="0 0 20 20"
									fill="currentColor"
								>
									<path
										fill-rule="evenodd"
										d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
										clip-rule="evenodd"
									/>
								</svg>
							</DropdownMenuItem>
						</div>
					</div>
				</DropdownMenuContent>
			</DropdownMenuPortal>
		</DropdownMenuRoot>

		<button
			@click="createNewConversation"
			class="cursor-pointer bg-neutral-800 text-white px-3 py-1 rounded-md text-sm hover:bg-neutral-700"
			:disabled="isLoading"
		>
			New
		</button>
	</div>
	<div class="flex items-center justify-between mb-4" v-else>
		<h2 class="text-lg font-medium">AI Chat Assistant</h2>
	</div>
</template>
