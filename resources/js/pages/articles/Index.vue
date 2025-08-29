<script setup>
import { onMounted, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useCampaignStore } from '@/stores/campaignStore'
import { useArticleStore } from '@/stores/articleStore'
import { useUsageStore } from '@/stores/usageStore'
import { useNotificationStore } from '@/stores/notificationStore'
import moment from 'moment'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import EditIcon from '../../components/icons/EditIcon.vue'
import TrashIcon from '../../components/icons/TrashIcon.vue'
import PlusIcon from '../../components/icons/PlusIcon.vue'
import DocumentIcon from '../../components/icons/DocumentIcon.vue'
import CampaignSwitcher from '@/components/campaigns/CampaignSwitcher.vue'
import UsageProgress from '@/components/UsageProgress.vue'

const router = useRouter()
const route = useRoute()
const articleStore = useArticleStore()
const campaignStore = useCampaignStore()
const usageStore = useUsageStore()
const notificationStore = useNotificationStore()
const teamId = computed(() => route.params.teamId)
const campaignId = computed(() => route.params.campaignId)

onMounted(async () => {
        await campaignStore.fetchCampaigns(teamId.value)
        if (campaignId.value) {
                await campaignStore.switchCampaign(teamId.value, campaignId.value)
        }
        await articleStore.fetchArticles(teamId.value, campaignId.value)
        await usageStore.fetchUsage(teamId.value)
})

watch(campaignId, async (newId) => {
	if (newId) {
		await campaignStore.switchCampaign(teamId.value, newId)
		await articleStore.fetchArticles(teamId.value, newId)
	}
})

const createArticle = async () => {
        try {
                const newArticle = await articleStore.createArticle(teamId.value, campaignId.value, {
                        title: 'Untitled article'
                })
                await usageStore.fetchUsage(teamId.value)
                router.push({
                        name: 'articles.edit',
                        params: {
                                teamId: teamId.value,
                                campaignId: campaignId.value,
                                articleId: newArticle.id
                        }
                })
        } catch (error) {
                notificationStore.addNotification({ message: error?.message || 'Unable to create article', type: 'error' })
        }
}

const editArticle = (id) => {
	router.push({
		name: 'articles.edit',
		params: {
			teamId: teamId.value,
			campaignId: campaignId.value,
			articleId: id
		}
	})
}

const deleteArticle = async (id) => {
        if (confirm('Are you sure you want to delete this article?')) {
                try {
                        await articleStore.deleteArticle(teamId.value, campaignId.value, id)
                        await usageStore.fetchUsage(teamId.value)
                } catch (error) {
                        notificationStore.addNotification({ message: error?.message || 'Error deleting article', type: 'error' })
                }
        }
}
</script>

<template>
	<DefaultLayout>
		<!-- Top bar -->
                <div class="flex justify-between items-center py-6">
                        <div class="flex items-center gap-4">
                                <h1 class="text-2xl font-bold">Articles</h1>
                        </div>
                        <div class="flex items-center space-x-2">
                                <Button @click="createArticle" variant="link">
                                        <div class="flex items-center gap-1">
                                                <PlusIcon class="size-4" />
                                                Create article
                                        </div>
                                </Button>
                                <CampaignSwitcher />
                        </div>
                </div>

                <UsageProgress
                        v-if="usageStore.usage"
                        :used="usageStore.usage.articles_used"
                        :limit="usageStore.usage.articles_limit"
                        label="Articles"
                />

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
	</DefaultLayout>
</template>
