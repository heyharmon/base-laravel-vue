<script setup>
import { ref, nextTick, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import Modal from '@/components/ui/Modal.vue'
import api from '@/services/api'
import { useKeywordStore } from '@/stores/keywordStore'
import { useOrganizationStore } from '@/stores/organizationStore'

const props = defineProps({
	isOpen: {
		type: Boolean,
		required: true
	}
})

const emit = defineEmits(['close'])

const route = useRoute()
const isLoadingKeywords = ref(false)
const generatedKeywords = ref([])
const error = ref(null)
const keywordStore = useKeywordStore()
const organizationStore = useOrganizationStore()
const organization = ref(null)

watch(
	() => props.isOpen,
	async (isOpen) => {
		if (isOpen) {
			await fetchOrganization()
			await generateKeywords()
		}
	},
	{ immediate: true }
)

const fetchOrganization = async () => {
	if (!route.params.id) {
		error.value = 'No organization ID found in route.'
		return
	}

	try {
		const data = await organizationStore.fetchOrganization(route.params.id)
		organization.value = data

		if (!data.website) {
			error.value =
				'This organization does not have a website configured. Please add a website URL to the organization first.'
		}
	} catch (err) {
		console.error('Error fetching organization:', err)
		error.value = 'Failed to fetch organization. Please try again.'
	}
}

const closeModal = () => {
	generatedKeywords.value = []
	error.value = null
	organization.value = null
	emit('close')
}

const removeKeyword = (index) => {
	generatedKeywords.value.splice(index, 1)
}

const generateKeywords = async () => {
	if (!organization.value?.website) {
		error.value = 'No website domain available for this organization.'
		return
	}

	isLoadingKeywords.value = true
	error.value = null
	generatedKeywords.value = []

	try {
		const response = await api.post('/generate-keywords', { domain: organization.value.website })
		generatedKeywords.value = response || []
	} catch (err) {
		console.error('Error generating keywords:', err)
		error.value = 'Failed to generate keywords. Please try again.'
	} finally {
		isLoadingKeywords.value = false
	}
}

const createKeywords = async () => {
	if (!generatedKeywords.value.length || !route.params.id) return

	try {
		const promises = generatedKeywords.value.map((keyword) =>
			keywordStore.createKeyword(route.params.id, { name: keyword })
		)

		await Promise.all(promises)
		closeModal()
	} catch (err) {
		console.error('Error creating keywords:', err)
		error.value = 'Failed to create keywords. Please try again.'
	}
}
</script>

<template>
	<Modal :is-open="isOpen" title="Generate Keywords" width="wider" @close="closeModal">
		<div class="space-y-4">
			<!-- Organization Info -->
			<div v-if="organization" class="mb-4 p-4 bg-neutral-50 rounded-lg">
				<h3 class="font-medium text-neutral-800">{{ organization.name }}</h3>
				<p v-if="organization.website" class="text-sm text-neutral-600 mt-1">
					Domain: {{ organization.website }}
				</p>
				<p v-else class="text-sm text-red-600 mt-1">No website configured for this organization</p>
			</div>

			<div v-if="error" class="text-red-500 text-sm">
				{{ error }}
			</div>

			<div class="mt-4 max-h-[calc(100vh-30rem)]">
				<!-- Keywords Content -->
				<div class="max-h-[calc(100vh-30rem)] overflow-y-auto">
					<div v-if="isLoadingKeywords" class="flex flex-col items-center justify-center py-8">
						<div
							class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-800 mb-2"
						></div>
						<p class="text-neutral-600 text-sm">Generating keywords...</p>
					</div>
					<div v-else-if="generatedKeywords.length > 0">
						<h3 class="font-medium mb-2">Suggestions:</h3>
						<ul class="space-y-1">
							<li
								v-for="(keyword, index) in generatedKeywords"
								:key="index"
								class="flex items-center justify-between bg-neutral-100 px-2 py-1.5 rounded mb-1"
							>
								<span class="text-sm">{{ keyword }}</span>
								<button
									@click="removeKeyword(index)"
									class="text-neutral-500 hover:text-red-500 ml-2 p-1 cursor-pointer rounded-lg hover:bg-red-100"
									type="button"
								>
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
										class="h-4 w-4"
									>
										<path d="M18 6 6 18" />
										<path d="m6 6 12 12" />
									</svg>
								</button>
							</li>
						</ul>
					</div>
				</div>
			</div>
		</div>

		<template #footer>
			<button
				v-if="generatedKeywords.length > 0"
				@click="createKeywords"
				class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
				:disabled="isLoadingKeywords"
			>
				Create Keywords
			</button>

			<button
				v-if="!isLoadingKeywords"
				@click="generateKeywords"
				class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-200 hover:bg-neutral-100 text-neutral-800 rounded-md cursor-pointer"
				:disabled="isLoadingKeywords || !organization?.website"
			>
				Regenerate
			</button>

			<button
				@click="closeModal"
				class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-200 hover:bg-neutral-100 text-neutral-800 rounded-md cursor-pointer"
			>
				Cancel
			</button>
		</template>
	</Modal>
</template>
