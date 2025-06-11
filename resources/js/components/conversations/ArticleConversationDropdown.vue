<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useArticleChatStore } from '@/stores/articleChatStore'
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

const fetchConversations = async () => {
	if (!props.articleId) {
		conversations.value = []
		return
	}
	isLoading.value = true
	try {
		const response = await api.get(`/articles/${props.articleId}/conversations`)
		conversations.value = Array.isArray(response) ? response : []
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

const setActiveConversation = (id) => {
	activeConversationId.value = id
	const conversation = conversations.value.find((c) => c.id === id)
	if (conversation) {
		activeConversationTitle.value = conversation.title || 'Untitled Conversation'
		articleChatStore.setConversationId(id)
		emit('conversationChanged', id)
	}
	isOpen.value = false
}

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
	} catch (error) {
		console.error('Error creating new conversation:', error)
	} finally {
		isLoading.value = false
	}
}

watch(
	() => props.articleId,
	(newId) => {
		if (newId) fetchConversations()
	},
	{ immediate: true }
)

const formatDate = (dateString) => {
	return new Date(dateString).toLocaleString()
}

const dropdownRef = ref(null)
const onClickOutside = (event) => {
	if (isOpen.value && dropdownRef.value && !dropdownRef.value.contains(event.target)) {
		isOpen.value = false
	}
}
onMounted(() => document.addEventListener('click', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside))
</script>

<template>
	<div v-if="props.articleId" ref="dropdownRef" class="flex items-center justify-between mb-4">
		<!-- Dropdown Trigger -->
		<div class="relative w-4/5">
			<button
				class="w-full flex items-center gap-1 text-md font-medium px-2 py-1 border border-neutral-200 rounded-md hover:bg-neutral-100"
				:disabled="isLoading"
				@click="isOpen = !isOpen"
				type="button"
			>
				<svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 20 20" fill="currentColor">
					<path
						fill-rule="evenodd"
						d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
						clip-rule="evenodd"
					/>
				</svg>
				<span class="truncate">{{ activeConversationTitle }}</span>
			</button>

			<!-- Dropdown Menu -->
			<div v-if="isOpen" class="absolute z-50 bg-white border border-neutral-200 rounded-md shadow-lg min-w-[240px] max-w-[500px] mt-1">
				<div class="p-1">
					<!-- Loading / Empty / List -->
					<div v-if="isLoading" class="p-3 text-center text-neutral-500">Loading conversations...</div>
					<div v-else-if="conversations.length === 0" class="p-3 text-center text-neutral-500">No conversations yet</div>
					<div v-else class="overflow-y-auto">
						<button
							v-for="conversation in conversations"
							:key="conversation.id"
							@click="setActiveConversation(conversation.id)"
							class="flex items-center justify-between w-full gap-2 px-3 py-2 text-left rounded-md hover:bg-neutral-100"
							type="button"
						>
							<div class="flex-1 min-w-0">
								<div class="font-medium truncate">{{ conversation.title || 'Untitled' }}</div>
								<div class="text-xs text-neutral-500 truncate">{{ formatDate(conversation.created_at) }}</div>
							</div>
							<svg
								v-if="conversation.id === activeConversationId"
								xmlns="http://www.w3.org/2000/svg"
								class="size-4 text-neutral-900"
								viewBox="0 0 20 20"
								fill="currentColor"
							>
								<path
									fill-rule="evenodd"
									d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
									clip-rule="evenodd"
								/>
							</svg>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- External New Button -->
		<button
			@click="createNewConversation"
			class="cursor-pointer bg-neutral-800 text-white px-3 py-1 rounded-md text-sm hover:bg-neutral-700"
			:disabled="isLoading"
			type="button"
		>
			New
		</button>
	</div>

	<!-- Fallback when no articleId -->
	<div v-else class="flex items-center justify-between mb-4">
		<h2 class="text-lg font-medium">AI Chat Assistant</h2>
	</div>
</template>
