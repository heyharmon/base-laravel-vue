<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCampaignStore } from '@/stores/campaignStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'

const route = useRoute()
const router = useRouter()
const campaignStore = useCampaignStore()
const isSubmitting = ref(false)
const isLoading = ref(true)
const campaign = ref({
	name: '',
	description: ''
})
const originalCampaign = ref({
	name: '',
	description: ''
})

onMounted(async () => {
	try {
		// Find the campaign in the store
		const campaignId = parseInt(route.params.id)
		let foundCampaign = campaignStore.campaigns.find((c) => c.id === campaignId)

		// If not found in store, fetch campaigns first
		if (!foundCampaign) {
			await campaignStore.fetchCampaigns()
			foundCampaign = campaignStore.campaigns.find((c) => c.id === campaignId)
		}

		if (foundCampaign) {
			campaign.value = { ...foundCampaign }
			originalCampaign.value = { ...foundCampaign }
		} else {
			// Campaign not found, redirect to home
			router.push('/')
		}
	} catch (error) {
		console.error('Error fetching campaign:', error)
		router.push('/')
	} finally {
		isLoading.value = false
	}
})

const hasChanges = computed(() => {
	return campaign.value.name !== originalCampaign.value.name || campaign.value.description !== originalCampaign.value.description
})

const updateCampaign = async () => {
	if (!campaign.value.name) return

	isSubmitting.value = true
	try {
		await campaignStore.updateCampaign(route.params.id, campaign.value)
		router.push('/')
	} catch (error) {
		console.error('Error updating campaign:', error)
	} finally {
		isSubmitting.value = false
	}
}

const deleteCampaign = async () => {
	if (campaign.value.is_default) {
		alert('Cannot delete the default campaign')
		return
	}

	if (confirm('Are you sure you want to delete this campaign? This action cannot be undone.')) {
		try {
			await campaignStore.deleteCampaign(route.params.id)
			router.push('/')
		} catch (error) {
			console.error('Error deleting campaign:', error)
		}
	}
}

const cancelEdit = () => {
	router.push('/')
}
</script>

<template>
	<DefaultLayout>
		<div class="max-w-2xl mx-auto py-8">
			<!-- Loading state -->
			<div v-if="isLoading" class="flex justify-center py-8">
				<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
			</div>

			<div v-else>
				<div class="flex justify-between items-center mb-8">
					<div class="flex items-center gap-3">
						<h1 class="text-2xl font-bold">Edit Campaign</h1>
						<span v-if="campaign.is_default" class="bg-neutral-200 text-neutral-800 text-xs px-2 py-1 rounded"> Default </span>
					</div>
					<div class="flex gap-4">
						<button v-if="!campaign.is_default" @click="deleteCampaign" class="text-red-600 hover:text-red-800 text-sm font-medium cursor-pointer">
							Delete
						</button>
						<Button @click="cancelEdit" variant="neutral"> Back </Button>
					</div>
				</div>

				<div class="space-y-6">
					<div>
						<label for="name" class="block text-sm font-medium text-neutral-700 mb-2"> Campaign Name </label>
						<input
							id="name"
							v-model="campaign.name"
							type="text"
							placeholder="Enter campaign name"
							class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-neutral-500 focus:border-transparent"
							required
						/>
					</div>

					<div>
						<label for="description" class="block text-sm font-medium text-neutral-700 mb-2"> Description </label>
						<textarea
							id="description"
							v-model="campaign.description"
							rows="4"
							placeholder="Enter campaign description (optional)"
							class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-neutral-500 focus:border-transparent"
						></textarea>
					</div>
				</div>

				<div class="mt-8 flex justify-end space-x-2">
					<Button @click="cancelEdit" variant="neutral"> Cancel </Button>
					<Button @click="updateCampaign" :disabled="isSubmitting || !campaign.name.trim() || !hasChanges" variant="dark">
						{{ isSubmitting ? 'Updating...' : 'Update Campaign' }}
					</Button>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
