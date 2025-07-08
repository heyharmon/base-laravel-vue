<script setup>
import { ref, nextTick, watch, onMounted, onUnmounted } from 'vue'
import { marked } from 'marked'
import { useRoute } from 'vue-router'
import { useArticleStore } from '@/stores/articleStore'
import Button from '@/components/ui/Button.vue'
import ChatsDropdown from '@/components/chat/ChatsDropdown.vue'
import ArrowUpIcon from '@/components/icons/ArrowUpIcon.vue'
import { useEcho } from '@laravel/echo-vue'

const emit = defineEmits(['clearSelectedContent'])

const props = defineProps({
	context: {
		type: Object,
		default: null
	}
})

const route = useRoute()

const articleStore = useArticleStore()
const newMessage = ref('')
const messagesContainer = ref(null)
const textareaRef = ref(null)

// Preset prompts for empty chat
const presetPrompts = [
	// '🧠 Use deep research to write this article',
	'💬 Summarize this article for me',
	'🔗 List sources mentioned in prompt responses',
	'✨ Suggest improvements for this article',
	'🌐 Search web for info related to this article'
]

onMounted(async () => {
	await articleStore.fetchChats(route.params.id)
})

// Listen for new chat events on the article
useEcho(`article.${route.params.id}`, 'ArticleChatCreated', (e) => {
	if (e.role !== 'user') {
		console.log('Received new chat with ID:', e.id)
		articleStore.chats.push(e)
	}
})

// Listen for chat processing completion events
useEcho(`article.${route.params.id}`, 'ArticleChatAgentFinished', (e) => {
	console.log('Chat processing completed:', e)

	// Reset loading state
	articleStore.isLoadingChats = false

	// Handle errors if needed
	if (!e.success && e.error) {
		console.error('Chat processing failed:', e.error)
		// Optionally show user-friendly error message
	}

	// Scroll to bottom to show any new messages
	scrollToBottom()
})

// Watch for changes in the chats array
watch(
	() => articleStore.chats.length,
	(newLength, oldLength) => {
		if (newLength > oldLength) {
			scrollToBottom()
		}
	}
)

const sendMessage = async () => {
	if (!newMessage.value.trim() || articleStore.isLoadingChats) return

	const message = newMessage.value
	newMessage.value = ''

	// Reset textarea height after sending message
	nextTick(() => {
		resizeTextarea()
	})

	try {
		// Send message with current context
		await articleStore.sendMessage(message, props.context)
		scrollToBottom()

		// Clear selected content after sending
		clearSelectedContent()
	} catch (error) {
		console.error('Error sending message:', error)
		newMessage.value = message // Restore message on error
	}
}

const sendPresetPrompt = async (prompt) => {
	try {
		await articleStore.sendMessage(prompt, props.context)
		scrollToBottom()

		// Clear selected content after sending
		clearSelectedContent()
	} catch (error) {
		console.error('Error sending preset prompt:', error)
	}
}

// Handle conversation selection from dropdown
const handleConversationChanged = async (conversationId) => {
	if (conversationId) {
		articleStore.setConversationId(conversationId)
		await articleStore.fetchChats()
		scrollToBottom()
	}
}

// Clear selected content
const clearSelectedContent = () => {
	emit('clearSelectedContent')
}

const renderMarkdown = (content) => {
	return marked.parse(content || '')
}

// Function to scroll to the bottom of the messages container
const scrollToBottom = () => {
	nextTick(() => {
		if (messagesContainer.value) {
			messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
		}
	})
}

function resizeTextarea() {
	if (!textareaRef.value) return
	textareaRef.value.style.height = 'auto'
	textareaRef.value.style.height = textareaRef.value.scrollHeight + 'px'
}

// Handle keyboard events - submit on Enter but not on Shift+Enter
function handleKeydown(event) {
	if (event.key === 'Enter' && !event.shiftKey) {
		event.preventDefault()
		sendMessage()
	}
}

// Watch for changes in the message and resize the textarea
function handleInput() {
	resizeTextarea()
}

const getRoleLabel = (role) => {
	switch (role) {
		case 'user':
			return 'You'
		case 'assistant':
			return 'Assistant'
		case 'tool_call':
			return 'Tool'
		default:
			return role
	}
}
</script>

<template>
	<div class="flex-1 overflow-hidden flex flex-col h-full">
		<!-- Messages top bar -->
		<div class="py-2 px-6">
			<ChatsDropdown :article-id="articleStore.article?.id" @conversation-changed="handleConversationChanged" />
		</div>

		<!-- Messages area (scrollable) -->
		<div ref="messagesContainer" class="flex-1 overflow-y-auto scrollbar-thin px-6 pt-4 pb-8 space-y-6 custom-scrollbar">
			<!-- Empty state with preset prompts -->
			<div v-if="articleStore.chats.length === 0 && !articleStore.isLoadingChats" class="flex flex-col gap-3">
				<p class="text-neutral-600 font-medium">Start a conversation with one of these prompts:</p>
				<button
					v-for="(prompt, index) in presetPrompts"
					:key="index"
					@click="sendPresetPrompt(prompt)"
					class="cursor-pointer text-left p-3 bg-neutral-100 hover:bg-neutral-200 rounded-lg transition-colors"
				>
					{{ prompt }}
				</button>
			</div>

			<!-- Chat messages -->
			<div v-for="chat in articleStore.chats" :key="chat.id" :class="['flex', chat.role === 'user' ? 'justify-end' : 'justify-start']">
				<!-- Tool Call -->
				<div v-if="chat.role === 'tool_call'" class="whitespace-nowrap overflow-hidden text-ellipsis text-sm rounded-lg p-2 border border-neutral-300">
					{{ chat.content }}
				</div>
				<!-- Assistant -->
				<div v-else-if="chat.role === 'assistant'" class="max-w-[90%]">
					<div v-if="chat.role !== 'user'" class="text-xs font-semibold mb-2 text-neutral-500">
						{{ getRoleLabel(chat.role) }}
					</div>

					<!-- Chat content -->
					<div class="markdown-content" v-html="renderMarkdown(chat.content)"></div>

					<!-- Citations section if annotations exist -->
					<div v-if="chat.annotations && chat.annotations.length > 0" class="mt-3 pt-2 border-t border-neutral-200">
						<p class="text-xs font-semibold mb-1">Sources:</p>
						<ul class="text-xs space-y-1">
							<li v-for="(annotation, index) in chat.annotations" :key="index">
								<a :href="annotation.url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">
									{{ annotation.title || annotation.url }}
								</a>
							</li>
						</ul>
					</div>
				</div>
				<!-- User -->
				<div v-else class="max-w-[90%] rounded-lg p-3 bg-neutral-200/60">
					<div v-if="chat.role !== 'user'" class="text-xs font-semibold mb-2 text-neutral-500">
						{{ getRoleLabel(chat.role) }}
					</div>

					<!-- Chat content -->
					<div class="markdown-content" v-html="renderMarkdown(chat.content)"></div>

					<!-- Citations section if annotations exist -->
					<div v-if="chat.annotations && chat.annotations.length > 0" class="mt-3 pt-2 border-t border-neutral-200">
						<p class="text-xs font-semibold mb-1">Sources:</p>
						<ul class="text-xs space-y-1">
							<li v-for="(annotation, index) in chat.annotations" :key="index">
								<a :href="annotation.url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">
									{{ annotation.title || annotation.url }}
								</a>
							</li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Loading indicator -->
			<div v-if="articleStore.isLoadingChats" class="flex justify-start">
				<div class="bg-neutral-300 dark:bg-neutral-700 rounded-lg p-3 flex items-center space-x-2">
					<div class="w-2 h-2 rounded-full bg-neutral-500 animate-pulse"></div>
					<div class="w-2 h-2 rounded-full bg-neutral-500 animate-pulse" style="animation-delay: 0.2s"></div>
					<div class="w-2 h-2 rounded-full bg-neutral-500 animate-pulse" style="animation-delay: 0.4s"></div>
				</div>
			</div>
		</div>

		<!-- Input area (fixed at bottom) -->
		<div class="px-4 pb-4 bg-transparent -mt-4">
			<div class="relative max-w-full mx-auto">
				<form @submit.prevent="sendMessage" class="border border-neutral-300 bg-white rounded-2xl overflow-hidden">
					<textarea
						v-model="newMessage"
						placeholder="Type your message here..."
						rows="1"
						autofocus
						ref="textareaRef"
						@input="handleInput"
						@keydown="handleKeydown"
						class="w-full pt-3 px-4 resize-none focus:outline-none disabled:opacity-50"
						style="min-height: 44px; max-height: 200px"
					/>

					<div class="flex items-center justify-between px-2 pb-2">
						<div class="flex items-center gap-1">
							<a
								as="a"
								variant="link"
								size="sm"
								href="https://sites.google.com/bloomcu.com/paraloom-instruction-templates"
								target="_blank"
								class="underline-offset-4 underline text-neutral-500 text-sm font-medium hover:text-neutral-700 pl-2"
							>
								Instruction templates
							</a>
						</div>

						<div class="flex items-center gap-1">
							<!-- Send button -->
							<button
								@click="sendMessage"
								type="submit"
								class="p-2 bg-black text-white rounded-full cursor-pointer hover:bg-black/80 disabled:cursor-not-allowed disabled:opacity-50"
								:disabled="!newMessage.trim() || articleStore.isLoadingChats"
							>
								<ArrowUpIcon />
							</button>
						</div>
					</div>

					<!-- Context Indicators -->
					<div v-if="context.selected_content" class="px-4 py-2 flex flex-col gap-2 bg-neutral-100 border-t border-neutral-200">
						<div v-if="context.selected_content" class="flex gap-2">
							<div class="text-sm text-neutral-600">
								<span class="font-semibold">Selected:</span> "{{
									context.selected_content.length > 100 ? context.selected_content.substring(0, 100) + '...' : context.selected_content
								}}"
							</div>
							<button
								@click="clearSelectedContent"
								class="size-5 p-1 flex items-center justify-center bg-white border border-neutral-200 rounded-md text-neutral-400 hover:text-neutral-600 text-sm cursor-pointer"
								title="Clear selected content"
							>
								✕
							</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</template>

<style>
/* Markdown content styling */
.markdown-content h1 {
	font-size: 1.5rem;
	font-weight: 700;
	margin-top: 1rem;
	margin-bottom: 0.5rem;
}

.markdown-content h2 {
	font-size: 1.25rem;
	font-weight: 600;
	margin-top: 0.75rem;
	margin-bottom: 0.5rem;
}

.markdown-content h3 {
	font-size: 1.125rem;
	font-weight: 600;
	margin-top: 0.75rem;
	margin-bottom: 0.5rem;
}

.markdown-content p:not(:last-of-type) {
	margin-bottom: 0.75rem;
}

.markdown-content ul,
.markdown-content ol {
	margin-left: 1.5rem;
	margin-bottom: 0.75rem;
}

.markdown-content ul {
	list-style-type: disc;
}

.markdown-content ol {
	list-style-type: decimal;
}

.markdown-content li {
	margin-bottom: 0.25rem;
}

.markdown-content a {
	color: #3b82f6;
	text-decoration: underline;
}

.markdown-content blockquote {
	border-left: 4px solid #d1d5db;
	padding-left: 1rem;
	margin-left: 0;
	margin-right: 0;
	font-style: italic;
	color: #4b5563;
}

.markdown-content pre {
	background-color: #f3f4f6;
	padding: 0.75rem;
	border-radius: 0.375rem;
	overflow-x: auto;
	margin-bottom: 0.75rem;
}

.markdown-content code {
	background-color: #f3f4f6;
	padding: 0.25rem 0.375rem;
	border-radius: 0.25rem;
	font-family: ui-monospace, monospace;
	font-size: 0.875em;
}

.markdown-content pre code {
	background-color: transparent;
	padding: 0;
}

.markdown-content hr {
	margin-top: 1rem;
	margin-bottom: 1rem;
	border-top: 1px solid #e5e7eb;
}

.markdown-content table {
	border-collapse: collapse;
	width: 100%;
	margin-bottom: 0.75rem;
}

.markdown-content th,
.markdown-content td {
	border: 1px solid #d1d5db;
	padding: 0.5rem;
}

.markdown-content th {
	background-color: #f3f4f6;
	font-weight: 600;
}

.markdown-content img {
	max-width: 100%;
	height: auto;
	border-radius: 0.375rem;
}

.markdown-content strong {
	font-weight: 600;
}

.markdown-content em {
	font-style: italic;
}
</style>
