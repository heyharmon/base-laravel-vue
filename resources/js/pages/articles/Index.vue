<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useArticleStore } from '@/stores/articleStore'
import moment from 'moment'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import EditIcon from '../../components/icons/EditIcon.vue'
import TrashIcon from '../../components/icons/TrashIcon.vue'
import PlusIcon from '../../components/icons/PlusIcon.vue'
import DocumentIcon from '../../components/icons/DocumentIcon.vue'

const router = useRouter()
const articleStore = useArticleStore()

onMounted(async () => {
	await articleStore.fetchArticles()
})

const createArticle = async () => {
	const newArticle = await articleStore.createArticle({
		title: 'Untitled article'
	})
	router.push({ name: 'articles.edit', params: { id: newArticle.id } })
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
				<Button @click="createArticle">
					<div class="flex items-center gap-2">
						<PlusIcon />
						Create article
					</div>
				</Button>
			</div>

			<!-- Loading state -->
			<div v-if="articleStore.isLoading" class="flex justify-center py-8">
				<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
			</div>

			<!-- No articles -->
			<div v-else-if="articleStore.articles.length === 0" class="text-center py-16 border border-neutral-200 rounded-xl">
				<DocumentIcon class="mx-auto text-neutral-400 mb-4" />
				<div class="text-neutral-500 text-lg mb-2">No articles yet</div>
				<div class="text-neutral-400 text-sm mb-6">Create your first article to get started</div>
				<Button @click="createArticle">Create article</Button>
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
							{{ moment(article.updated_at).format('MMM D, YYYY') }}
						</div>
						<div class="col-span-3 text-right flex justify-end gap-2">
							<button @click="editArticle(article.id)" class="cursor-pointer text-neutral-500 hover:text-neutral-700 p-1" title="Edit">
								<EditIcon />
							</button>
							<button @click="deleteArticle(article.id)" class="cursor-pointer text-neutral-500 hover:text-red-600 p-1" title="Delete">
								<TrashIcon />
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
