<script setup>
import { ref, watch, nextTick } from 'vue'
import { useCampaignStore } from '@/stores/campaignStore'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'

const props = defineProps({
	isOpen: {
		type: Boolean,
		required: true
	},
	teamId: {
		type: [Number, String],
		required: true
	}
})

const emit = defineEmits(['close', 'created'])

const campaignStore = useCampaignStore()
const isSubmitting = ref(false)
const campaignNameInput = ref(null)
const newCampaign = ref({
	name: '',
	description: '',
	location: ''
})

const resetForm = () => {
	newCampaign.value = {
		name: '',
		description: '',
		location: ''
	}
	isSubmitting.value = false
}

// Reset form when modal opens
watch(
	() => props.isOpen,
	async (isOpen) => {
		if (isOpen) {
			resetForm()
			await nextTick()
			if (campaignNameInput.value) {
				campaignNameInput.value.focus()
			}
		}
	},
	{ immediate: true }
)

const closeModal = () => {
	resetForm()
	emit('close')
}

const createCampaign = async () => {
	if (!newCampaign.value.name) return

	isSubmitting.value = true
	try {
		const campaign = await campaignStore.createCampaign(props.teamId, {
			is_default: false,
			name: newCampaign.value.name,
			description: newCampaign.value.description,
			location: newCampaign.value.location
		})

		emit('created', campaign)
		emit('close')
	} catch (error) {
		console.error('Error creating campaign:', error)
	} finally {
		isSubmitting.value = false
	}
}
</script>

<template>
	<Modal :is-open="isOpen" title="Create new campaign" @close="closeModal">
		<div class="space-y-4">
			<div>
				<label class="block text-sm font-medium text-neutral-700 mb-1">Campaign Name</label>
				<input
					ref="campaignNameInput"
					v-model="newCampaign.name"
					type="text"
					class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
					placeholder="Enter campaign name"
					@keyup.enter="createCampaign"
				/>
			</div>

			<div>
				<label class="block text-sm font-medium text-neutral-700 mb-1">Location (optional)</label>
				<input
					v-model="newCampaign.location"
					type="text"
					class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
					placeholder="Enter location"
				/>
				<p class="text-xs text-neutral-500 mt-1">Location where your business primarily operates</p>
			</div>

			<div>
				<label class="block text-sm font-medium text-neutral-700 mb-1">Description (optional)</label>
				<textarea
					v-model="newCampaign.description"
					rows="3"
					class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
					placeholder="Enter campaign description"
				></textarea>
				<p class="text-xs text-neutral-500">This description can help AI generate accurate prompts</p>
			</div>
		</div>
		<template #footer>
			<Button @click="createCampaign" :disabled="isSubmitting || !newCampaign.name" variant="dark">
				{{ isSubmitting ? 'Creating...' : 'Create Campaign' }}
			</Button>
			<Button @click="closeModal" variant="neutral"> Cancel </Button>
		</template>
	</Modal>
</template>
