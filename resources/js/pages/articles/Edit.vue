<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useArticleStore } from '@/stores/articleStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import { useArticleChatStore } from '@/stores/articleChatStore'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import ChatMessage from '@/components/ChatMessage.vue'
import ChatInput from '@/components/ChatInput.vue'
import ChatLoadingIndicator from '@/components/ChatLoadingIndicator.vue'

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
	prompt_id: null,
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
	prompt_id: null,
})

const isSubmitting = ref(false)
const isLoading = ref(true)
const showSettings = ref(false)
const showChat = ref(false)

// Get active jobs related to this article
const activeArticleJobs = computed(() => {
	return jobStatusStore.jobs.filter(
		(job) =>
			job.trackable_type === 'App\\Models\\Article' &&
			job.trackable_id === article.value.id &&
			(job.status === 'pending' || job.status === 'processing')
	)
})

// Watch activeArticleJobs and when it changes to false, fetch the article
// watch(
// 	activeArticleJobs,
// 	(newJobs, oldJobs) => {
// 		if (oldJobs.length > newJobs.length || newJobs.length === 0) {
// 			// At least one job completed, or all jobs are done
// 			fetchArticle()
// 		}
// 	},
// 	{ deep: true }
// )

const editor = useEditor({
	content: '',
	extensions: [StarterKit],
	onUpdate: ({ editor }) => {
		console.log('On updating article...')
		article.value.content = editor.getHTML()
	}
})

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
	<DefaultLayout>
		<!-- Active jobs message -->
		<div
			v-if="activeArticleJobs.length > 0"
			class="p-4 mt-4 bg-green-50 border border-green-200 text-green-800 rounded-lg flex items-center gap-2"
		>
			<span class="animate-spin h-4 w-4 mr-2 border-t-2 border-b-2 border-green-700 rounded-full"></span>
			<span> {{ activeArticleJobs.length }} {{ activeArticleJobs.length === 1 ? 'job is running for this article' : 'jobs are running for this article' }} </span>
		</div>

		<div class="container mx-auto py-8">
			<!-- Top bar -->
			<div class="flex justify-between items-start mb-8">
				<div class="flex items-center gap-3">
					<h1 class="text-2xl font-bold">{{ article.title || 'Edit Article' }}</h1>
				</div>
				<div class="flex items-center justify-end gap-2">
					<Button @click="showSettings = !showSettings" variant="neutral">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
						{{ showSettings ? 'Hide Settings' : 'Show Settings' }}
					</Button>
					<Button @click="copyContentToClipboard" variant="neutral" :disabled="isCopied">
						{{ isCopied ? 'Copied!' : 'Copy Content' }}
					</Button>
					<Button @click="showChat = !showChat" variant="neutral">
						{{ showChat ? 'Hide AI Chat' : 'Show AI Chat' }}
					</Button>
					<Button v-if="hasChanges" @click="updateArticle" :disabled="isSubmitting" :loading="isSubmitting"> Save </Button>
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
				<!-- Chat panel -->
				<div v-if="showChat" class="bg-neutral-50 p-4 rounded-md border border-neutral-200 mb-2">
					<h2 class="text-lg font-medium mb-4">AI Chat Assistant</h2>
					<div class="flex flex-col">
						<!-- Chat messages -->
						<div class="flex-grow mb-4 space-y-4 overflow-y-auto max-h-[400px] p-2">
							<ChatMessage v-for="(chat, index) in articleChatStore.chats" :key="index" :chat="chat" />
							<ChatLoadingIndicator v-if="articleChatStore.isLoading" />
						</div>

						<!-- Chat input -->
						<div class="mt-4">
							<ChatInput :is-loading="articleChatStore.isLoading" @send="articleChatStore.sendMessage($event)" />
						</div>
					</div>
				</div>

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

				<!-- Outline input -->
				<!-- <div>
          <label for="outline" class="block text-sm font-medium text-neutral-700 mb-1">Outline</label>
          <textarea
            id="outline"
            v-model="article.outline"
            rows="4"
            class="w-full px-4 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-neutral-500 focus:border-neutral-500"
            placeholder="Article outline"
          ></textarea>
        </div> -->

				<!-- Rich text editor -->
				<div>
					<div class="flex justify-between items-center mb-2">
						<label class="block text-sm font-medium text-neutral-700">Content</label>
						<Button
							@click="copyContentToClipboard"
							variant="outline"
							class="flex items-center gap-1 text-xs px-2 py-1 rounded bg-neutral-100 hover:bg-neutral-200 transition-colors"
						>
							<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
								<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
							</svg>
							{{ isCopied ? 'Copied!' : 'Copy article HTML' }}
						</Button>
					</div>
					<div class="border border-neutral-300 rounded-md shadow-sm overflow-hidden">
						<!-- Editor menu -->
						<div class="flex items-center gap-2 p-2 border-b border-neutral-300 bg-neutral-50">
							<button
								@click="editor.chain().focus().toggleBold().run()"
								:class="{ 'bg-neutral-200': editor.isActive('bold') }"
								class="p-1 rounded hover:bg-neutral-200"
							>
								<!-- prettier-ignore -->
								<svg fill=none height=16 stroke=currentColor stroke-linecap=round stroke-linejoin=round stroke-width=2 viewBox="0 0 24 24" width=16 xmlns=http://www.w3.org/2000/svg><path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path><path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path></svg>
							</button>
							<button
								@click="editor.chain().focus().toggleItalic().run()"
								:class="{ 'bg-neutral-200': editor.isActive('italic') }"
								class="p-1 rounded hover:bg-neutral-200"
							>
								<!-- prettier-ignore -->
								<svg fill=none height=16 stroke=currentColor stroke-linecap=round stroke-linejoin=round stroke-width=2 viewBox="0 0 24 24" width=16 xmlns=http://www.w3.org/2000/svg><line x1=19 x2=10 y1=4 y2=4></line><line x1=14 x2=5 y1=20 y2=20></line><line x1=15 x2=9 y1=4 y2=20></line></svg>
							</button>
							<button
								@click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
								:class="{ 'bg-neutral-200': editor.isActive('heading', { level: 1 }) }"
								class="p-1 rounded hover:bg-neutral-200"
							>
								H1
							</button>
							<button
								@click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
								:class="{ 'bg-neutral-200': editor.isActive('heading', { level: 2 }) }"
								class="p-1 rounded hover:bg-neutral-200"
							>
								H2
							</button>
							<button
								@click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
								:class="{ 'bg-neutral-200': editor.isActive('heading', { level: 3 }) }"
								class="p-1 rounded hover:bg-neutral-200"
							>
								H3
							</button>
							<button
								@click="editor.chain().focus().toggleHeading({ level: 4 }).run()"
								:class="{ 'bg-neutral-200': editor.isActive('heading', { level: 4 }) }"
								class="p-1 rounded hover:bg-neutral-200"
							>
								H4
							</button>
							<button
								@click="editor.chain().focus().toggleBulletList().run()"
								:class="{ 'bg-neutral-200': editor.isActive('bulletList') }"
								class="p-1 rounded hover:bg-neutral-200"
							>
								<!-- prettier-ignore -->
								<svg fill=none height=16 stroke=currentColor stroke-linecap=round stroke-linejoin=round stroke-width=2 viewBox="0 0 24 24" width=16 xmlns=http://www.w3.org/2000/svg><line x1=8 x2=21 y1=6 y2=6></line><line x1=8 x2=21 y1=12 y2=12></line><line x1=8 x2=21 y1=18 y2=18></line><line x1=3 x2=3.01 y1=6 y2=6></line><line x1=3 x2=3.01 y1=12 y2=12></line><line x1=3 x2=3.01 y1=18 y2=18></line></svg>
							</button>
							<button
								@click="editor.chain().focus().toggleOrderedList().run()"
								:class="{ 'bg-neutral-200': editor.isActive('orderedList') }"
								class="p-1 rounded hover:bg-neutral-200"
							>
								<!-- prettier-ignore -->
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="6" x2="21" y2="6"></line><line x1="10" y1="12" x2="21" y2="12"></line><line x1="10" y1="18" x2="21" y2="18"></line><path d="M4 6h1v4"></path><path d="M4 10h2"></path><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path></svg>
							</button>
							<button
								@click="editor.chain().focus().toggleBlockquote().run()"
								:class="{ 'bg-neutral-200': editor.isActive('blockquote') }"
								class="p-1 rounded hover:bg-neutral-200"
							>
								<!-- prettier-ignore -->
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="m6.585 17.308l2.396-4.174q-.173.097-.404.135t-.461.039q-1.4 0-2.354-.972q-.954-.971-.954-2.336q0-1.4.954-2.354t2.354-.954q1.364 0 2.336.954t.971 2.347q0 .486-.118.906t-.336.793l-3.238 5.616zm8.769 0l2.396-4.173q-.173.096-.404.134t-.461.039q-1.4 0-2.354-.972q-.954-.971-.954-2.336q0-1.42.954-2.363t2.354-.945q1.364 0 2.336.954t.971 2.347q0 .486-.118.906t-.335.793L16.5 17.308zm-7.238-5.116q.913 0 1.552-.639q.64-.64.64-1.553t-.64-1.553t-1.552-.64q-.914 0-1.553.64q-.64.64-.64 1.553t.64 1.553t1.553.64m8.769 0q.913 0 1.553-.64T19.077 10t-.64-1.553t-1.552-.64t-1.553.64t-.64 1.553t.64 1.553t1.553.64M8.115 10"/></svg>
							</button>
							<button @click="editor.chain().focus().undo().run()" class="p-1 rounded hover:bg-neutral-200">
								<!-- prettier-ignore -->
								<svg fill=none height=16 stroke=currentColor stroke-linecap=round stroke-linejoin=round stroke-width=2 viewBox="0 0 24 24" width=16 xmlns=http://www.w3.org/2000/svg><path d="M3 7v6h6"></path><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"></path></svg>
							</button>
							<button @click="editor.chain().focus().redo().run()" class="p-1 rounded hover:bg-neutral-200">
								<!-- prettier-ignore -->
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 7v6h-6"></path><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"></path></svg>
							</button>
						</div>

						<!-- Editor content -->
						<div class="p-4 min-h-[400px]">
							<EditorContent :editor="editor" />
						</div>
					</div>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
