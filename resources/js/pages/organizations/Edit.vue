<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useOrganizationStore } from '@/stores/organizationStore'
import { useKeywordStore } from '@/stores/keywordStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import KeywordDetailSheet from '@/components/keywords/KeywordDetailSheet.vue'
import KeywordCreateModal from '@/components/keywords/KeywordCreateModal.vue'
import GenerateKeywordsModal from '@/components/GenerateKeywordsModal.vue'
import KeywordNotification from '@/components/keywords/KeywordNotification.vue'
import KeywordListItem from '@/components/keywords/KeywordListItem.vue'
import RecommendedKeywordItem from '@/components/keywords/RecommendedKeywordItem.vue'
import OrganizationForm from '@/components/organizations/OrganizationForm.vue'

const route = useRoute()
const router = useRouter()
const organizationStore = useOrganizationStore()
const keywordStore = useKeywordStore()
const organization = ref({
	name: '',
	website: '',
	founded: '',
	employee_count: '',
	location: '',
	is_competitor: false
})
const originalOrganization = ref({
	name: '',
	website: '',
	founded: '',
	employee_count: '',
	location: '',
	is_competitor: false
})
const isSubmitting = ref(false)
const isLoading = ref(true)
const isKeywordCreateModalOpen = ref(false)
const isGenerateKeywordsModalOpen = ref(false)
const isKeywordDetailSheetOpen = ref(false)
const selectedKeyword = ref(null)
const selectedKeywordId = ref(null)
const deletedKeywordMessage = ref(null)

onMounted(async () => {
	try {
		const data = await organizationStore.fetchOrganization(route.params.id)
		organization.value = { ...data }
		originalOrganization.value = { ...data }
		await keywordStore.fetchKeywords(route.params.id)
	} catch (error) {
		console.error('Error fetching organization:', error)
	} finally {
		isLoading.value = false
	}
})

const hasChanges = computed(() => {
	return (
		organization.value.name !== originalOrganization.value.name ||
		organization.value.website !== originalOrganization.value.website ||
		organization.value.founded !== originalOrganization.value.founded ||
		organization.value.employee_count !== originalOrganization.value.employee_count ||
		organization.value.is_competitor !== originalOrganization.value.is_competitor ||
		organization.value.location !== originalOrganization.value.location
	)
})

const showKeywordDetails = (keyword) => {
	selectedKeyword.value = keyword
	selectedKeywordId.value = keyword.id
	isKeywordDetailSheetOpen.value = true
}

const addKeyword = (keyword) => {
	keyword.organization_id = route.params.id
	return keyword
}

const updateOrganization = async () => {
	isSubmitting.value = true
	try {
		await organizationStore.updateOrganization(route.params.id, organization.value)
		router.push({ name: 'organizations.index' })
	} catch (error) {
		console.error('Error updating organization:', error)
	} finally {
		isSubmitting.value = false
	}
}

const deleteOrganization = async () => {
	try {
		await organizationStore.deleteOrganization(route.params.id)
		router.push({ name: 'organizations.index' })
	} catch (error) {
		console.error('Error deleting organization:', error)
	}
}

const cancelEdit = () => {
	router.push({ name: 'organizations.index' })
}

const handleDeleteKeyword = (keywordId, keywordName) => {
	deletedKeywordMessage.value = `The keyword "${keywordName}" and its history has been deleted.`
	keywordStore.deleteKeyword(route.params.id, keywordId)
	setTimeout(() => {
		deletedKeywordMessage.value = null
	}, 10000)
}

const acceptRecommendedKeyword = async (keywordId) => {
	try {
		await keywordStore.acceptRecommendedKeyword(route.params.id, keywordId)
		deletedKeywordMessage.value = 'Keyword added to your organization.'
		setTimeout(() => {
			deletedKeywordMessage.value = null
		}, 10000)
	} catch (error) {
		console.error('Error accepting recommended keyword:', error)
	}
}

const denyRecommendedKeyword = async (keywordId, keywordName) => {
	try {
		await keywordStore.denyRecommendedKeyword(route.params.id, keywordId)
		deletedKeywordMessage.value = `The keyword "${keywordName}" recommendation has been removed.`
		setTimeout(() => {
			deletedKeywordMessage.value = null
		}, 10000)
	} catch (error) {
		console.error('Error denying recommended keyword:', error)
	}
}
</script>

<template>
	<DefaultLayout>
		<div class="container mx-auto py-8">
			<!-- Top bar -->
			<div class="flex justify-between items-center mb-8">
				<div class="flex items-center gap-3">
					<div class="flex items-center gap-2">
						<img
							:src="`https://cdn.brandfetch.io/${organization.website}/w/400/h/400?c=1idaplhOcH8x9kYGESa`"
							:alt="organization.name + ' logo'"
							class="h-10 w-10 object-contain bg-white rounded-md border border-neutral-200"
						/>
						<h1 class="text-2xl font-bold">{{ organization.name }}</h1>
					</div>
					<span v-if="organization.is_competitor" class="bg-neutral-200 text-neutral-800 text-xs px-2 py-1 rounded"> Competitor </span>
				</div>
				<div class="flex gap-4">
					<button
						v-if="organization.is_competitor"
						@click="deleteOrganization"
						class="text-red-600 hover:text-red-800 text-sm font-medium cursor-pointer"
					>
						Delete
					</button>
					<Button @click="cancelEdit" variant="neutral"> Back </Button>
				</div>
			</div>

			<!-- Loading state -->
			<div v-if="isLoading" class="flex justify-center py-8">
				<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
			</div>

			<!-- Error state -->
			<div v-else-if="organizationStore.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
				{{ organizationStore.error }}
			</div>

			<div v-else class="flex flex-col md:flex-row gap-12">
				<!-- Left column - Keywords section -->
				<div class="w-full md:w-2/3">
					<!-- Keywords header -->
					<div class="flex justify-between items-center mb-4">
						<h2 class="text-xl font-semibold">Keywords</h2>

						<div class="flex gap-2">
							<button
								@click="isGenerateKeywordsModalOpen = true"
								class="flex items-center gap-2 px-3 py-1.5 bg-neutral-800 text-white rounded-md text-xs font-medium hover:bg-neutral-700 transition-colors cursor-pointer"
							>
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
									<path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
								</svg>
								<span>Generate keywords</span>
							</button>
							<button
								@click="isKeywordCreateModalOpen = true"
								class="px-3 py-1.5 bg-white text-neutral-800 border border-neutral-400 rounded-md text-xs font-medium hover:bg-neutral-100 transition-colors cursor-pointer"
							>
								Add a keyword
							</button>
						</div>
					</div>

					<!-- Loading state for keywords -->
					<div v-if="keywordStore.isLoading" class="flex justify-center py-8">
						<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
					</div>

					<!-- Keywords section -->
					<div v-else>
						<!-- No keywords message -->
						<div v-if="keywordStore.keywords.length === 0" class="text-center py-12 border border-neutral-200 rounded-xl mb-8">
							<div class="text-neutral-400 text-sm">No keywords yet</div>
						</div>

						<!-- Existing keywords list -->
						<div v-else class="space-y-3 mb-8">
							<KeywordNotification :message="deletedKeywordMessage" />

							<KeywordListItem
								v-for="keyword in keywordStore.keywords"
								:key="keyword.id"
								:keyword="keyword"
								:is-selected="selectedKeywordId === keyword.id"
								@select="showKeywordDetails"
								@delete="(kw) => handleDeleteKeyword(kw.id, kw.name)"
							/>
						</div>

						<!-- Recommended Keywords Section -->
						<div v-if="keywordStore.recommendedKeywords.length > 0">
							<h3 class="text-lg font-semibold mb-4">Recommended keywords</h3>
							<div v-if="keywordStore.isLoadingRecommended" class="flex justify-center py-8">
								<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
							</div>
							<div v-else class="space-y-3">
								<RecommendedKeywordItem
									v-for="keyword in keywordStore.recommendedKeywords"
									:key="keyword.id"
									:keyword="keyword"
									@accept="(kw) => acceptRecommendedKeyword(kw.id)"
									@deny="(kw) => denyRecommendedKeyword(kw.id, kw.name)"
								/>
							</div>
						</div>
					</div>
				</div>

				<!-- Right column - Organization details -->
				<OrganizationForm :organization="organization" :has-changes="hasChanges" :is-submitting="isSubmitting" @update="updateOrganization" />
			</div>
		</div>
	</DefaultLayout>

	<!-- Keyword Modal -->
	<KeywordCreateModal :is-open="isKeywordCreateModalOpen" @close="isKeywordCreateModalOpen = false" @create="addKeyword" />

	<!-- Generate Keywords Modal -->
	<GenerateKeywordsModal :is-open="isGenerateKeywordsModalOpen" @close="isGenerateKeywordsModalOpen = false" />

	<!-- Keyword Detail Sheet -->
	<KeywordDetailSheet
		:is-open="isKeywordDetailSheetOpen"
		:keyword="selectedKeyword"
		:keyword-id="selectedKeyword?.id"
		@close="
			() => {
				isKeywordDetailSheetOpen = false
				selectedKeywordId = null
			}
		"
	/>
</template>
