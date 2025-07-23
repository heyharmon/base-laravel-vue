<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import Modal from '@/components/ui/Modal.vue'
import TermSuggestions from '@/components/terms/TermSuggestions.vue'
import { useOrganizationStore } from '@/stores/organizationStore'

const props = defineProps({
	isOpen: {
		type: Boolean,
		required: true
	}
})

const emit = defineEmits(['close'])

const route = useRoute()
const organization = ref(null)
const error = ref(null)
const organizationStore = useOrganizationStore()

watch(
	() => props.isOpen,
	async (isOpen) => {
		if (isOpen) {
			await fetchOrganization()
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
			error.value = 'This organization does not have a website configured. Please add a website URL to the organization first.'
		}
	} catch (err) {
		console.error('Error fetching organization:', err)
		error.value = 'Failed to fetch organization. Please try again.'
	}
}

const closeModal = () => {
	error.value = null
	organization.value = null
	emit('close')
}

const handleCreateTerms = () => {
	closeModal()
}
</script>

<template>
	<Modal :is-open="isOpen" title="Generate Terms" width="wider" @close="closeModal">
		<div class="space-y-4">
			<!-- Organization Info -->
			<div v-if="organization" class="mb-4 p-4 bg-neutral-50 rounded-lg">
				<h3 class="font-medium text-neutral-800">{{ organization.name }}</h3>
				<p v-if="organization.website" class="text-sm text-neutral-600 mt-1">Domain: {{ organization.website }}</p>
				<p v-else class="text-sm text-red-600 mt-1">No website configured for this organization</p>
			</div>

			<div v-if="error" class="text-red-500 text-sm">
				{{ error }}
			</div>

                        <TermSuggestions
                                v-if="organization?.website"
                                :domain="organization.website"
                                :organization-id="route.params.id"
                                :team-id="organization.team_id"
                                @create-terms="handleCreateTerms"
                        />
		</div>

		<!-- <template #footer>
			<button
				@click="closeModal"
				class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-200 hover:bg-neutral-100 text-neutral-800 rounded-md cursor-pointer"
			>
				Cancel
			</button>
		</template> -->
	</Modal>
</template>
