<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useArticleStore } from '@/stores/articleStore'
import api from '@/services/api'
import Button from '@/components/ui/Button.vue'
import ChevronDownIcon from '@/components/icons/ChevronDownIcon.vue'
import moment from 'moment'

const props = defineProps({
	articleId: {
		type: [Number, String, null],
		default: null
	}
})

const emit = defineEmits(['conversationChanged'])

const articleStore = useArticleStore()
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
		articleStore.setConversationId(id)
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
	<div v-if="props.articleId" ref="dropdownRef" class="flex items-center justify-between">
		<!-- Dropdown Trigger -->
		<div class="relative w-4/5">
			<button
				class="w-full flex items-center gap-0.5 text-md px-2 py-1 -ml-2 rounded-md hover:bg-neutral-100 cursor-pointer"
				:disabled="isLoading"
				@click="isOpen = !isOpen"
				type="button"
			>
				<ChevronDownIcon class="size-5" />
				<span class="truncate">{{ activeConversationTitle }}</span>
			</button>

			<!-- Dropdown Menu -->
			<div v-if="isOpen" class="absolute z-50 bg-white border border-neutral-200 rounded-md shadow-lg min-w-[240px] max-w-[460px] mt-1">
				<div class="p-1">
					<!-- Loading / Empty / List -->
					<div v-if="isLoading" class="p-3 text-center text-neutral-500">Loading conversations...</div>
					<div v-else-if="conversations.length === 0" class="p-3 text-center text-neutral-500">No conversations yet</div>
					<div v-else class="overflow-y-auto">
						<button
							v-for="conversation in conversations"
							:key="conversation.id"
							@click="setActiveConversation(conversation.id)"
							:class="{ 'bg-neutral-100': conversation.id === activeConversationId }"
							class="flex items-center justify-between w-full gap-2 px-3 py-2 text-left rounded-md cursor-pointer hover:bg-neutral-100"
							type="button"
						>
							<div class="flex-1 min-w-0">
								<div class="font-medium truncate">{{ conversation.title || 'Untitled' }}</div>
								<div class="text-xs text-neutral-500 truncate">{{ moment(conversation.created_at).format('M/D/YYYY, h:mm:ss A') }}</div>
							</div>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- External New Button -->
		<Button @click="createNewConversation" :disabled="isLoading" type="button" variant="outline" size="sm"> New </Button>
	</div>

	<!-- Fallback when no articleId -->
	<div v-else class="flex items-center justify-between mb-4">
		<h2 class="text-lg font-medium">AI Chat Assistant</h2>
	</div>
</template>
