<script setup>
import { ref, onMounted, computed, onUnmounted, watch, nextTick, defineAsyncComponent } from 'vue'
import { useRoute } from 'vue-router'
import { useArticleStore } from '@/stores/articleStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import { usePromptStore } from '@/stores/promptStore'
import PromptDetailSheet from '@/components/prompts/PromptDetailSheet.vue'
import ArticleDeepResearchResponseModal from '@/components/articles/ArticleDeepResearchResponseModal.vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import TwoColumnLayout from '@/layouts/TwoColumnLayout.vue'
import Button from '@/components/ui/Button.vue'
import ChatMessage from '@/components/chat/ChatMessage.vue'
import ChatInput from '@/components/chat/ChatInput.vue'
import ChatsDropdown from '@/components/chat/ChatsDropdown.vue'
import ChatLoadingIndicator from '@/components/chat/ChatLoadingIndicator.vue'
import EditorMenu from '@/components/editor/EditorMenu.vue'
import { useEcho } from '@laravel/echo-vue'

const route = useRoute()
const articleStore = useArticleStore()
const jobStatusStore = useJobStatusStore()

const isLoading = ref(true)
const showSettings = ref(false)
const showVersions = ref(false)
const messagesContainer = ref(null)

// Dynamically import the ArticleVersionsPanel component
const ArticleVersionsPanel = defineAsyncComponent(() => import('@/components/articles/ArticleVersionsPanel.vue'))

// Get active jobs related to this article
const activeArticleJobs = computed(() => {
	if (!articleStore.article) return []
	return jobStatusStore.jobs.filter(
		(job) =>
			job.trackable_type === 'App\\Models\\Article' &&
			job.trackable_id === articleStore.article.id &&
			(job.status === 'pending' || job.status === 'processing')
	)
})

const editor = useEditor({
	content: '',
	extensions: [StarterKit],
	onUpdate: ({ editor }) => {
		articleStore.article.content = editor.getHTML()
	}
})

const handleEditorCommand = (command, options = {}) => {
	const commandMap = {
		bold: () => editor.value.chain().focus().toggleBold().run(),
		italic: () => editor.value.chain().focus().toggleItalic().run(),
		heading: ({ level }) => editor.value.chain().focus().toggleHeading({ level }).run(),
		bulletList: () => editor.value.chain().focus().toggleBulletList().run(),
		orderedList: () => editor.value.chain().focus().toggleOrderedList().run(),
		blockquote: () => editor.value.chain().focus().toggleBlockquote().run()
	}

	if (commandMap[command]) {
		commandMap[command](options)
	}
}

const activeEditorCommands = computed(() => ({
	bold: editor.value?.isActive('bold'),
	italic: editor.value?.isActive('italic'),
	heading: {
		level: [1, 2, 3, 4].find((level) => editor.value?.isActive('heading', { level }))
	},
	bulletList: editor.value?.isActive('bulletList'),
	orderedList: editor.value?.isActive('orderedList'),
	blockquote: editor.value?.isActive('blockquote')
}))

// Echo channel subscription handlers
const { leaveChannel, listen } = useEcho(`article.${route.params.id}`, 'ArticleUpdated', (e) => {
	console.log('Received update for article ID:', e.id)

	// Update the article content
	if (e.id === articleStore.article.id) {
		// articleStore.article.content = e.content
		// articleStore.article.versions = e.versions
		articleStore.article = e

		// Update the editor content if it's different
		editor.value.commands.setContent(e.content)
	}
})

// Listen for deep research updates
listen('ArticleDeepResearchUpdated', (e) => {
	console.log('Deep research completed for article ID:', e.article_id)

	// Refresh the article data when deep research is completed
	if (e.article_id === articleStore.article.id) {
		console.log('Refreshing article data after deep research completion')
		articleStore.fetchArticle(e.article_id)
		isArticleDeepResearchResponseModalOpen.value = true
	}
})

// Function to scroll to the bottom of the messages container
const scrollToBottom = () => {
	nextTick(() => {
		if (messagesContainer.value) {
			messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
		}
	})
}

// Watch for changes in the chats array to scroll to bottom when new messages are added
watch(
	() => articleStore.chats.length,
	(newLength, oldLength) => {
		if (newLength > oldLength) {
			scrollToBottom()
		}
	}
)

// Watch for changes in the article content from the store and update the editor
watch(
	() => articleStore.article?.content,
	(newContent) => {
		if (editor.value && newContent && editor.value.getHTML() !== newContent) {
			console.log('Article content updated in store, updating editor')
			editor.value.commands.setContent(newContent)
		}
	}
)

onMounted(async () => {
	try {
		const articleId = route.params.id
		if (!articleId) return

		// Load article
		await articleStore.fetchArticle(articleId)

		// Initialize editor content
		if (editor.value && articleStore.article.content) {
			editor.value.commands.setContent(articleStore.article.content)
		}

		isLoading.value = false

		// Fetch chats for this article
		await articleStore.fetchChats(route.params.id)

		// Scroll to bottom of messages
		scrollToBottom()
	} catch (error) {
		console.error('Error loading article:', error)
		isLoading.value = false
	}
})

onUnmounted(() => {
	// Leave the Echo channel when component is unmounted
	leaveChannel()
})

const isCopied = ref(false)
const isPromptDetailSheetOpen = ref(false)
const isArticleDeepResearchResponseModalOpen = ref(false)
const promptStore = usePromptStore()

// Preset prompts for empty chat
const presetPrompts = [
	'🧠 Use deep research to write this article',
	'💬 Summarize this article for me',
	'🔗 List sources mentioned in prompt responses',
	'✨ Suggest improvements for this article',
	'🌐 Search the web for information related to this article'
]

// Handle conversation selection from dropdown
const handleConversationChanged = async (conversationId) => {
	if (conversationId) {
		articleStore.setConversationId(conversationId)
		await articleStore.fetchChats()
	}
}

// Open the prompt detail sheet
const showPromptDetails = () => {
	if (articleStore.article?.prompt_id) {
		isPromptDetailSheetOpen.value = true
	}
}

const copyContentToClipboard = async () => {
	try {
		if (articleStore.article?.content) {
			await navigator.clipboard.writeText(articleStore.article.content)
			isCopied.value = true
			setTimeout(() => {
				isCopied.value = false
			}, 2000)
		}
	} catch (error) {
		console.error('Failed to copy content:', error)
	}
}
</script>

<template>
	<TwoColumnLayout>
		<template #left-column>
			<!-- Chat column -->
			<div class="flex-1 overflow-hidden flex flex-col h-full">
				<!-- Messages top bar -->
				<div class="py-2 px-6">
					<ChatsDropdown :article-id="articleStore.article?.id" @conversation-changed="handleConversationChanged" />
				</div>

				<!-- Messages area (scrollable) -->
				<div ref="messagesContainer" class="flex-1 overflow-y-auto scrollbar-thin px-6 pt-4 pb-8 space-y-6 custom-scrollbar">
					<div v-if="articleStore.chats.length === 0 && !articleStore.isLoadingChats" class="flex flex-col gap-3">
						<p class="text-neutral-600 font-medium">Start a conversation with one of these prompts:</p>
						<button
							v-for="(prompt, index) in presetPrompts"
							:key="index"
							@click="articleStore.sendMessage(prompt)"
							class="cursor-pointer text-left p-3 bg-neutral-100 hover:bg-neutral-200 rounded-lg transition-colors"
						>
							{{ prompt }}
						</button>
					</div>
					<ChatMessage v-for="(chat, index) in articleStore.chats" :key="index" :chat="chat" />
					<ChatLoadingIndicator v-if="articleStore.isLoadingChats" />
				</div>

				<!-- Input area (fixed at bottom) -->
				<div class="px-4 pb-4 bg-transparent -mt-4">
					<ChatInput v-model="articleStore.newMessage" @send="articleStore.sendMessage" :disabled="articleStore.isLoadingChats" />
				</div>
			</div>
		</template>

		<template #right-column>
			<!-- Deep research statuses -->
			<div
				v-if="articleStore.article?.perplexity_status === 'CREATED' || articleStore.article?.perplexity_status === 'IN_PROGRESS'"
				class="p-4 my-4 bg-green-50 border border-green-200 text-green-800 rounded-lg mr-6"
			>
				<div class="flex items-center gap-2 mb-2">
					<span class="animate-spin h-4 w-4 mr-2 border-t-2 border-b-2 border-green-700 rounded-full"></span>
					Deep research is in progress...
				</div>
				<div class="w-full bg-green-200 rounded-full h-2.5 mt-2">
					<div
						class="bg-green-600 h-2.5 rounded-full transition-all duration-500 ease-in-out"
						:style="{ width: `${Math.min(((articleStore.article?.perplexity_checks || 0) / 60) * 100, 100)}%` }"
					></div>
				</div>
				<div class="text-xs text-green-800 mt-1 text-right">
					{{ Math.min(Math.round(((articleStore.article?.perplexity_checks || 0) / 60) * 100), 100) }}% completed
				</div>
			</div>
			<div
				v-if="articleStore.article?.perplexity_status === 'COMPLETED' && articleStore.article?.perplexity_request_id"
				class="p-4 my-4 bg-green-50 border border-green-200 text-green-800 rounded-lg mr-6"
			>
				<div class="flex items-center justify-between">
					<div class="flex items-center gap-2">
						<span class="h-4 w-4 mr-2 flex items-center justify-center">
							<svg
								xmlns="http://www.w3.org/2000/svg"
								width="16"
								height="16"
								viewBox="0 0 24 24"
								fill="none"
								stroke="currentColor"
								stroke-width="2"
								stroke-linecap="round"
								stroke-linejoin="round"
							>
								<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
								<polyline points="22 4 12 14.01 9 11.01"></polyline>
							</svg>
						</span>
						Deep research completed
					</div>
					<Button @click="isArticleDeepResearchResponseModalOpen = true" variant="success" size="sm"> View Deep Research </Button>
				</div>
			</div>

			<div
				v-if="articleStore.article?.perplexity_status === 'FAILED'"
				class="p-4 my-4 bg-red-50 border border-red-200 text-red-800 rounded-lg flex items-center gap-2 mr-6"
			>
				Deep research failed
			</div>

			<div class="pt-4">
				<!-- Top bar -->
				<div class="flex justify-between items-start gap-10 mb-4 pr-6">
					<h1 class="text-xl font-bold">{{ articleStore.article?.title || 'Edit Article' }}</h1>

					<div class="flex items-center justify-end gap-2">
						<!-- Auto-save indicator -->
						<div class="flex items-center mr-2 text-sm text-neutral-600">
							<span v-if="articleStore.isSaving" class="flex items-center">
								<span class="animate-spin h-4 w-4 mr-2 border-t-2 border-b-2 border-neutral-600 rounded-full"></span>
							</span>
							<span v-else class="text-neutral-600"></span>
						</div>

						<div class="flex gap-2">
							<Button @click="showSettings = !showSettings" variant="outline" size="sm">
								{{ showSettings ? 'Hide Settings' : 'Settings' }}
							</Button>

							<Button @click="showVersions = !showVersions" variant="outline" size="sm">
								{{ showVersions ? 'Hide Versions' : 'Versions' }}
							</Button>

							<Button @click="copyContentToClipboard" variant="outline" size="sm" :disabled="isCopied">
								{{ isCopied ? 'Copied!' : 'Copy HTML' }}
							</Button>

							<Button v-if="articleStore.article && articleStore.article.prompt_id" @click="showPromptDetails" variant="outline" size="sm"
								>Prompt</Button
							>
						</div>
					</div>
				</div>

				<!-- Loading state -->
				<div v-if="isLoading" class="flex justify-center py-8">
					<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
				</div>

				<div v-else class="flex flex-col gap-6">
					<!-- Versions panel - dynamically loaded -->
					<ArticleVersionsPanel v-if="showVersions" />

					<!-- Settings panel -->
					<div v-if="showSettings" class="bg-neutral-50 p-4 rounded-md border border-neutral-200 mb-2">
						<h2 class="text-lg font-medium mb-4">Article Settings</h2>

						<div class="flex flex-col gap-4">
							<!-- Title input -->
							<div>
								<label for="title" class="block text-sm font-medium text-neutral-700 mb-1">Title</label>
								<input
									id="title"
									v-model="articleStore.article.title"
									type="text"
									class="bg-white w-full px-4 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-neutral-500 focus:border-neutral-500"
									placeholder="Article title"
								/>
							</div>

							<!-- Meta Title input -->
							<div>
								<label for="meta_title" class="block text-sm font-medium text-neutral-700 mb-1">Meta Title</label>
								<input
									id="meta_title"
									v-model="articleStore.article.meta_title"
									type="text"
									class="bg-white w-full px-4 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-neutral-500 focus:border-neutral-500"
									placeholder="Meta title for SEO"
								/>
							</div>

							<!-- Meta Description input -->
							<div>
								<label for="meta_description" class="block text-sm font-medium text-neutral-700 mb-1">Meta Description</label>
								<textarea
									id="meta_description"
									v-model="articleStore.article.meta_description"
									rows="3"
									class="bg-white w-full px-4 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-neutral-500 focus:border-neutral-500"
									placeholder="Meta description for SEO"
								></textarea>
							</div>

							<!-- Schema input -->
							<div>
								<label for="schema" class="block text-sm font-medium text-neutral-700 mb-1">Schema</label>
								<textarea
									id="schema"
									v-model="articleStore.article.schema"
									rows="5"
									class="bg-white w-full px-4 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-neutral-500 focus:border-neutral-500 font-mono text-sm"
									placeholder="JSON-LD structured data schema"
								></textarea>
							</div>
						</div>
					</div>

					<!-- Rich text editor -->
					<div class="flex flex-col overflow-hidden">
						<!-- Editor menu -->
						<EditorMenu @command="handleEditorCommand" :active-commands="activeEditorCommands" />

						<!-- Editor content -->
						<div class="pl-2 pr-6 min-h-[400px] max-h-[calc(100vh-200px)] overflow-y-auto custom-scrollbar">
							<EditorContent :editor="editor" />
						</div>
					</div>
				</div>
			</div>
		</template>
	</TwoColumnLayout>

	<!-- Prompt Detail Sheet -->
	<PromptDetailSheet
		v-if="articleStore.article?.prompt_id"
		:is-open="isPromptDetailSheetOpen"
		:prompt-id="articleStore.article.prompt_id"
		@close="isPromptDetailSheetOpen = false"
	/>

	<!-- Perplexity Response Modal -->
	<ArticleDeepResearchResponseModal
		v-if="articleStore.article?.perplexity_request_id"
		:is-open="isArticleDeepResearchResponseModalOpen"
		:article-id="articleStore.article?.id"
		@close="isArticleDeepResearchResponseModalOpen = false"
	/>
</template>
