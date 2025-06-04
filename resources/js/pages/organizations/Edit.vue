<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useOrganizationStore } from '@/stores/organizationStore'
import { useTermStore } from '@/stores/termStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import TermDetailSheet from '@/components/terms/TermDetailSheet.vue'
import TermCreateModal from '@/components/terms/TermCreateModal.vue'
import GenerateTermsModal from '@/components/GenerateTermsModal.vue'
import TermNotification from '@/components/terms/TermNotification.vue'
import TermListItem from '@/components/terms/TermListItem.vue'
import RecommendedTermItem from '@/components/terms/RecommendedTermItem.vue'
import OrganizationForm from '@/components/organizations/OrganizationForm.vue'

const route = useRoute()
const router = useRouter()
const organizationStore = useOrganizationStore()
const termStore = useTermStore()
const organization = ref({
	name: '',
	website: '',
	founded: '',
	employee_count: '',
	location: '',
	industry: '',
	description: '',
	is_competitor: false
})
const originalOrganization = ref({
	name: '',
	website: '',
	founded: '',
	employee_count: '',
	location: '',
	industry: '',
	description: '',
	is_competitor: false
})
const isSubmitting = ref(false)
const isLoading = ref(true)
const isTermCreateModalOpen = ref(false)
const isGenerateTermsModalOpen = ref(false)
const isTermDetailSheetOpen = ref(false)
const selectedTerm = ref(null)
const selectedTermId = ref(null)
const deletedTermMessage = ref(null)

onMounted(async () => {
	try {
		const data = await organizationStore.fetchOrganization(route.params.id)
		organization.value = { ...data }
		originalOrganization.value = { ...data }
		await termStore.fetchTerms(route.params.id)
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
		organization.value.location !== originalOrganization.value.location ||
		organization.value.industry !== originalOrganization.value.industry ||
		organization.value.description !== originalOrganization.value.description
	)
})

const showTermDetails = (term) => {
	selectedTerm.value = term
	selectedTermId.value = term.id
	isTermDetailSheetOpen.value = true
}

const addTerm = (term) => {
	term.organization_id = route.params.id
	return term
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

const handleDeleteTerm = (termId, termName) => {
	deletedTermMessage.value = `The term "${termName}" and its history has been deleted.`
	termStore.deleteTerm(route.params.id, termId)
	setTimeout(() => {
		deletedTermMessage.value = null
	}, 10000)
}

const acceptRecommendedTerm = async (termId) => {
	try {
		await termStore.acceptRecommendedTerm(route.params.id, termId)
		deletedTermMessage.value = 'Term added to your organization.'
		setTimeout(() => {
			deletedTermMessage.value = null
		}, 10000)
	} catch (error) {
		console.error('Error accepting recommended term:', error)
	}
}

const denyRecommendedTerm = async (termId, termName) => {
	try {
		await termStore.denyRecommendedTerm(route.params.id, termId)
		deletedTermMessage.value = `The term "${termName}" recommendation has been removed.`
		setTimeout(() => {
			deletedTermMessage.value = null
		}, 10000)
	} catch (error) {
		console.error('Error denying recommended term:', error)
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
				<!-- Left column - Terms section -->
				<div class="w-full md:w-2/3">
					<!-- Terms header -->
					<div class="flex justify-between items-center mb-4">
						<h2 class="text-xl font-semibold">Terms</h2>

						<div class="flex gap-2">
							<button
								@click="isGenerateTermsModalOpen = true"
								class="flex items-center gap-2 px-3 py-1.5 bg-neutral-800 text-white rounded-md text-xs font-medium hover:bg-neutral-700 transition-colors cursor-pointer"
							>
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
									<path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
								</svg>
								<span>Generate terms</span>
							</button>
							<button
								@click="isTermCreateModalOpen = true"
								class="px-3 py-1.5 bg-white text-neutral-800 border border-neutral-400 rounded-md text-xs font-medium hover:bg-neutral-100 transition-colors cursor-pointer"
							>
								Add a term
							</button>
						</div>
					</div>

					<!-- Loading state for terms -->
					<div v-if="termStore.isLoading" class="flex justify-center py-8">
						<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
					</div>

					<!-- Terms section -->
					<div v-else>
						<!-- No terms message -->
						<div v-if="termStore.terms.length === 0" class="text-center py-12 border border-neutral-200 rounded-xl mb-8">
							<div class="text-neutral-400 text-sm">No terms yet</div>
						</div>

						<!-- Existing terms list -->
						<div v-else class="space-y-3 mb-8">
							<TermNotification :message="deletedTermMessage" />

							<TermListItem
								v-for="term in termStore.terms"
								:key="term.id"
								:term="term"
								:is-selected="selectedTermId === term.id"
								@select="showTermDetails"
								@delete="(kw) => handleDeleteTerm(kw.id, kw.name)"
							/>
						</div>

						<!-- Recommended Terms Section -->
						<div v-if="termStore.recommendedTerms.length > 0">
							<h3 class="text-lg font-semibold mb-4">Recommended terms</h3>
							<div v-if="termStore.isLoadingRecommended" class="flex justify-center py-8">
								<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
							</div>
							<div v-else class="space-y-3">
								<RecommendedTermItem
									v-for="term in termStore.recommendedTerms"
									:key="term.id"
									:term="term"
									@accept="(kw) => acceptRecommendedTerm(kw.id)"
									@deny="(kw) => denyRecommendedTerm(kw.id, kw.name)"
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

	<!-- Term Modal -->
	<TermCreateModal :is-open="isTermCreateModalOpen" @close="isTermCreateModalOpen = false" @create="addTerm" />

	<!-- Generate Terms Modal -->
	<GenerateTermsModal :is-open="isGenerateTermsModalOpen" @close="isGenerateTermsModalOpen = false" />

	<!-- Term Detail Sheet -->
	<TermDetailSheet
		:is-open="isTermDetailSheetOpen"
		:term="selectedTerm"
		:term-id="selectedTerm?.id"
		@close="
			() => {
				isTermDetailSheetOpen = false
				selectedTermId = null
			}
		"
	/>
</template>
