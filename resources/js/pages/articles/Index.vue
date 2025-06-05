<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useArticleStore } from '@/stores/articleStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import moment from 'moment'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'

const router = useRouter()
const articleStore = useArticleStore()
const jobStatusStore = useJobStatusStore()

// Get active jobs related to competitors
const activeArticleJobs = computed(() => {
	return jobStatusStore.jobs.filter((job) => job.job_class.includes('GenerateArticleJob') && (job.status === 'pending' || job.status === 'processing'))
})

// Watch for article job completions
watch(
	() => jobStatusStore.jobs,
	(newJobs, oldJobs) => {
		// Check if any article job has just completed
		const completedArticleJob = newJobs.some(
			(job) =>
				job.job_class.includes('GenerateArticleJob') &&
				job.status === 'completed' &&
				oldJobs.find((oldJob) => oldJob.job_id === job.job_id)?.status !== 'completed'
		)

		if (completedArticleJob) {
			console.log('Article job completed, refreshing articles')
			articleStore.fetchArticles()
		}
	},
	{ deep: true }
)

onMounted(async () => {
	await articleStore.fetchArticles()
	// await jobStatusStore.pollTeamJobs()
})

const formatDate = (dateString) => {
	return moment(dateString).format('MMM D, YYYY')
}

const createNewArticle = async () => {
	try {
		const newArticle = await articleStore.createArticle({
			title: 'Untitled Article',
			content: '',
			outline: ''
		})
		router.push({ name: 'articles.edit', params: { id: newArticle.id } })
	} catch (error) {
		console.error('Error creating article:', error)
	}
}

const editArticle = (id) => {
	router.push({ name: 'articles.edit', params: { id } })
}

const deleteArticle = async (id) => {
	if (confirm('Are you sure you want to delete this article?')) {
		try {
			await articleStore.deleteArticle(id)
		} catch (error) {
			console.error('Error deleting article:', error)
		}
	}
}
</script>

<template>
	<DefaultLayout>
		<div class="container mx-auto py-8">
			<!-- Top bar -->
			<div class="flex justify-between items-center mb-8">
				<h1 class="text-2xl font-bold">Articles</h1>
				<Button @click="createNewArticle">
					<div class="flex items-center gap-2">
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
							<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
						</svg>
						New Article
					</div>
				</Button>
			</div>

			<!-- Active jobs message -->
			<div
				v-if="!articleStore.error && activeArticleJobs.length > 0"
				class="p-4 my-4 bg-green-50 border border-green-200 text-green-800 rounded-lg flex items-center gap-2"
			>
				<span class="animate-spin h-4 w-4 mr-2 border-t-2 border-b-2 border-green-700 rounded-full"></span>
				<span> Generating {{ activeArticleJobs.length }} {{ activeArticleJobs.length === 1 ? 'article' : 'articles' }}. </span>
			</div>

			<!-- Loading state -->
			<div v-if="articleStore.isLoading" class="flex justify-center py-8">
				<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
			</div>

			<!-- Error state -->
			<div v-else-if="articleStore.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
				{{ articleStore.error }}
			</div>

			<!-- No articles -->
			<div v-else-if="articleStore.articles.length === 0" class="text-center py-16 border border-neutral-200 rounded-xl">
				<svg
					xmlns="http://www.w3.org/2000/svg"
					fill="none"
					viewBox="0 0 24 24"
					stroke-width="1.5"
					stroke="currentColor"
					class="w-12 h-12 mx-auto text-neutral-400 mb-4"
				>
					<path
						stroke-linecap="round"
						stroke-linejoin="round"
						d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
					/>
				</svg>
				<div class="text-neutral-500 text-lg mb-2">No articles yet</div>
				<div class="text-neutral-400 text-sm mb-6">Create your first article to get started</div>
				<Button @click="createNewArticle">Create Article</Button>
			</div>

			<!-- Articles list -->
			<div v-else>
				<div class="bg-white border border-neutral-200 rounded-xl overflow-hidden">
					<div class="grid grid-cols-12 gap-4 p-4 border-b border-neutral-200 bg-neutral-50 text-sm font-medium text-neutral-500">
						<div class="col-span-6">Title</div>
						<div class="col-span-3">Last Updated</div>
						<div class="col-span-3 text-right">Actions</div>
					</div>
					<div
						v-for="article in articleStore.articles"
						:key="article.id"
						class="grid grid-cols-12 gap-4 p-4 border-b border-neutral-100 hover:bg-neutral-50 transition-colors"
					>
						<div class="col-span-6">
							<div @click="editArticle(article.id)" class="font-medium text-neutral-800 cursor-pointer hover:underline transition-colors">
								{{ article.title }}
							</div>
						</div>
						<div class="col-span-3 text-neutral-500 text-sm flex items-center">
							{{ formatDate(article.updated_at) }}
						</div>
						<div class="col-span-3 text-right flex justify-end gap-2">
							<button @click="editArticle(article.id)" class="text-neutral-500 hover:text-neutral-700 p-1" title="Edit">
								<svg
									xmlns="http://www.w3.org/2000/svg"
									fill="none"
									viewBox="0 0 24 24"
									stroke-width="1.5"
									stroke="currentColor"
									class="w-5 h-5"
								>
									<path
										stroke-linecap="round"
										stroke-linejoin="round"
										d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"
									/>
								</svg>
							</button>
							<button @click="deleteArticle(article.id)" class="text-neutral-500 hover:text-red-600 p-1" title="Delete">
								<svg
									xmlns="http://www.w3.org/2000/svg"
									fill="none"
									viewBox="0 0 24 24"
									stroke-width="1.5"
									stroke="currentColor"
									class="w-5 h-5"
								>
									<path
										stroke-linecap="round"
										stroke-linejoin="round"
										d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"
									/>
								</svg>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
