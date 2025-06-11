<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useArticleStore } from '@/stores/articleStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import { useArticleChatStore } from '@/stores/articleChatStore'
import { usePromptStore } from '@/stores/promptStore'
import PromptDetailSheet from '@/components/prompts/PromptDetailSheet.vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import CopyIcon from '@/components/icons/CopyIcon.vue'
import SettingsIcon from '@/components/icons/SettingsIcon.vue'
import StarterKit from '@tiptap/starter-kit'
import TwoColumnLayout from '@/layouts/TwoColumnLayout.vue'
import Button from '@/components/ui/Button.vue'
import ChatMessage from '@/components/ChatMessage.vue'
import ChatInput from '@/components/ChatInput.vue'
import ArticleConversationDropdown from '@/components/conversations/ArticleConversationDropdown.vue'
import ChatLoadingIndicator from '@/components/ChatLoadingIndicator.vue'
import EditorMenu from '@/components/editor/EditorMenu.vue'

const route = useRoute()
const router = useRouter()
const articleStore = useArticleStore()
const jobStatusStore = useJobStatusStore()
const articleChatStore = useArticleChatStore()

const article = ref({
	id: null,
	title: '',
	meta_title: '',
	meta_description: '',
	schema: '',
	outline: '',
	content: '',
	organization_id: null,
	prompt_id: null
})

const originalArticle = ref({
	id: null,
	title: '',
	meta_title: '',
	meta_description: '',
	schema: '',
	outline: '',
	content: '',
	organization_id: null,
	prompt_id: null
})

const isSubmitting = ref(false)
const isLoading = ref(true)
const showSettings = ref(false)

// Get active jobs related to this article
const activeArticleJobs = computed(() => {
	return jobStatusStore.jobs.filter(
		(job) =>
			job.trackable_type === 'App\\Models\\Article' && job.trackable_id === article.value.id && (job.status === 'pending' || job.status === 'processing')
	)
})

const editor = useEditor({
	content: '',
	extensions: [StarterKit],
	onUpdate: ({ editor }) => {
		console.log('On updating article...')
		article.value.content = editor.getHTML()
	}
})

const handleEditorCommand = (command, options = {}) => {
	if (command === 'undo') return editor.value.chain().focus().undo().run()
	if (command === 'redo') return editor.value.chain().focus().redo().run()

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

const fetchArticle = async () => {
	console.log('Fetching article...')
	const data = await articleStore.fetchArticle(route.params.id)
	article.value = { ...data }
	originalArticle.value = { ...data }
	editor.value.commands.setContent(article.value.content) // Set editor content
}

onMounted(async () => {
	try {
		if (route.params.id) {
			fetchArticle()
			// Set article ID in chat store and fetch chats
			articleChatStore.setArticleId(route.params.id)
			await articleChatStore.fetchChats()
		}
	} catch (error) {
		console.error('Error fetching article:', error)
	} finally {
		isLoading.value = false
	}
})

const hasChanges = computed(() => {
	return (
		article.value.title !== originalArticle.value.title ||
		article.value.meta_title !== originalArticle.value.meta_title ||
		article.value.meta_description !== originalArticle.value.meta_description ||
		article.value.schema !== originalArticle.value.schema ||
		article.value.outline !== originalArticle.value.outline ||
		article.value.content !== originalArticle.value.content ||
		article.value.organization_id !== originalArticle.value.organization_id ||
		article.value.prompt_id !== originalArticle.value.prompt_id
	)
})

const updateArticle = async () => {
	console.log('Updating article...')
	isSubmitting.value = true
	try {
		await articleStore.updateArticle(route.params.id, article.value)
		window.location.reload()
	} catch (error) {
		console.error('Error updating article:', error)
	} finally {
		isSubmitting.value = false
	}
}

const cancelEdit = () => {
	router.push({ name: 'articles.index' })
}

const isCopied = ref(false)
const isPromptDetailSheetOpen = ref(false)
const promptStore = usePromptStore()

// Preset prompts for empty chat
const presetPrompts = [
	'🧠 Summarize this article for me',
	'🔗 List sources mentioned in responses',
	'✨ Suggest improvements for this article',
	'🌐 Search the web for information related to this article'
]

// Handle conversation selection from dropdown
const handleConversationChanged = async (conversationId) => {
	if (conversationId) {
		articleChatStore.setConversationId(conversationId)
		await articleChatStore.fetchChats()
	}
}

// Open the prompt detail sheet
const showPromptDetails = () => {
	if (article.value.prompt_id) {
		isPromptDetailSheetOpen.value = true
	}
}

const copyContentToClipboard = async () => {
	try {
		await navigator.clipboard.writeText(article.value.content)
		isCopied.value = true
		setTimeout(() => {
			isCopied.value = false
		}, 2000)
	} catch (error) {
		console.error('Failed to copy content:', error)
	}
}
</script>

<template>
	<TwoColumnLayout>
		<template #left-column>
			<!-- Chat panel -->
			<div class="py-4 min-h-[calc(100vh-52px)] flex flex-col">
				<ArticleConversationDropdown :article-id="article.id" @conversation-changed="handleConversationChanged" />

				<div class="flex flex-col flex-grow">
					<!-- Chat messages -->
					<div class="flex-grow mb-4 space-y-4 overflow-y-auto p-2">
						<!-- Show preset prompts when there are no chat messages -->
						<div v-if="articleChatStore.chats.length === 0 && !articleChatStore.isLoading" class="flex flex-col gap-3 p-2">
							<p class="text-neutral-600 font-medium">Start a conversation with one of these prompts:</p>
							<button
								v-for="(prompt, index) in presetPrompts"
								:key="index"
								@click="articleChatStore.sendMessage(prompt)"
								class="cursor-pointer text-left p-3 bg-neutral-100 hover:bg-neutral-200 rounded-lg transition-colors"
							>
								{{ prompt }}
							</button>
						</div>
						<!-- Display chat messages when available -->
						<ChatMessage v-for="(chat, index) in articleChatStore.chats" :key="index" :chat="chat" />
						<ChatLoadingIndicator v-if="articleChatStore.isLoading" />
					</div>

					<!-- Chat input -->
					<ChatInput v-model="articleChatStore.newMessage" @send="articleChatStore.sendMessage" :disabled="articleChatStore.isLoading" />
				</div>
			</div>
		</template>

		<template #right-column>
			<!-- Active jobs message -->
			<div v-if="activeArticleJobs.length > 0" class="p-4 mb-4 bg-green-50 border border-green-200 text-green-800 rounded-lg flex items-center gap-2">
				<span class="animate-spin h-4 w-4 mr-2 border-t-2 border-b-2 border-green-700 rounded-full"></span>
				<span>
					{{ activeArticleJobs.length }}
					{{ activeArticleJobs.length === 1 ? 'job is running for this article' : 'jobs are running for this article' }}
				</span>
			</div>

			<div class="py-8">
				<!-- Top bar -->
				<div class="flex justify-between items-start gap-10 mb-8">
					<h1 class="text-2xl font-bold">{{ article.title || 'Edit Article' }}</h1>
					<div class="flex items-center justify-end gap-2">
						<Button @click="showSettings = !showSettings" variant="neutral">
							<SettingsIcon />
							{{ showSettings ? 'Hide Settings' : 'Settings' }}
						</Button>
						<Button @click="copyContentToClipboard" variant="neutral" :disabled="isCopied">
							<CopyIcon />
							{{ isCopied ? 'Copied!' : 'Copy HTML' }}
						</Button>
						<Button v-if="article.prompt_id" @click="showPromptDetails" variant="neutral">View Prompt</Button>
						<Button v-if="hasChanges" @click="updateArticle" :disabled="isSubmitting" :loading="isSubmitting">Save</Button>
					</div>
				</div>

				<!-- Loading state -->
				<div v-if="isLoading" class="flex justify-center py-8">
					<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
				</div>

				<!-- Error state -->
				<div v-else-if="articleStore.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
					{{ articleStore.error }}
				</div>

				<div v-else class="flex flex-col gap-6">
					<!-- Settings panel -->
					<div v-if="showSettings" class="bg-neutral-50 p-4 rounded-md border border-neutral-200 mb-2">
						<h2 class="text-lg font-medium mb-4">Article Settings</h2>
						<div class="flex flex-col gap-4">
							<!-- Title input -->
							<div>
								<label for="title" class="block text-sm font-medium text-neutral-700 mb-1">Title</label>
								<input
									id="title"
									v-model="article.title"
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
									v-model="article.meta_title"
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
									v-model="article.meta_description"
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
									v-model="article.schema"
									rows="5"
									class="bg-white w-full px-4 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-neutral-500 focus:border-neutral-500 font-mono text-sm"
									placeholder="JSON-LD structured data schema"
								></textarea>
							</div>
						</div>
					</div>

					<!-- Rich text editor -->
					<div class="flex flex-col gap-6">
						<div class="border border-neutral-300 rounded-md shadow-sm overflow-hidden">
							<!-- Editor menu -->
							<EditorMenu @command="handleEditorCommand" :active-commands="activeEditorCommands" />

							<!-- Editor content -->
							<div class="p-4 min-h-[400px]">
								<EditorContent :editor="editor" />
							</div>
						</div>
					</div>
				</div>
			</div>
		</template>
	</TwoColumnLayout>

	<!-- Prompt Detail Sheet -->
	<PromptDetailSheet v-if="article.prompt_id" :is-open="isPromptDetailSheetOpen" :prompt-id="article.prompt_id" @close="isPromptDetailSheetOpen = false" />
</template>
