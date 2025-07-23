<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCampaignStore } from '@/stores/campaignStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'

const route = useRoute()
const router = useRouter()
const campaignStore = useCampaignStore()

const teamId = computed(() => route.params.teamId)
const campaignId = computed(() => route.params.campaignId)

const campaign = ref({
	name: '',
	description: '',
	location: '',
	keywords: ''
})
const originalCampaign = ref({
	name: '',
	description: '',
	location: '',
	keywords: ''
})
const isSubmitting = ref(false)
const isLoading = ref(true)

const hasChanges = computed(() => {
	return (
		campaign.value.name !== originalCampaign.value.name ||
		campaign.value.description !== originalCampaign.value.description ||
		campaign.value.location !== originalCampaign.value.location ||
		campaign.value.keywords !== originalCampaign.value.keywords
	)
})

const updateCampaign = async () => {
	if (!hasChanges.value) return
	
	isSubmitting.value = true
	try {
		await campaignStore.updateCampaign(teamId.value, campaignId.value, campaign.value)
		originalCampaign.value = { ...campaign.value }
		router.push({ name: 'campaigns.index', params: { teamId: teamId.value } })
	} catch (error) {
		console.error('Error updating campaign:', error)
		alert('Failed to update campaign. Please try again.')
	} finally {
		isSubmitting.value = false
	}
}

const goBack = () => {
	router.push({ name: 'campaigns.index', params: { teamId: teamId.value } })
}

onMounted(async () => {
	try {
		await campaignStore.fetchCampaigns(teamId.value)
		const foundCampaign = campaignStore.campaigns.find(c => c.id == campaignId.value)
		if (foundCampaign) {
			campaign.value = {
				name: foundCampaign.name || '',
				description: foundCampaign.description || '',
				location: foundCampaign.location || '',
				keywords: foundCampaign.keywords || ''
			}
			originalCampaign.value = { ...campaign.value }
		} else {
			router.push({ name: 'campaigns.index', params: { teamId: teamId.value } })
		}
	} catch (error) {
		console.error('Error fetching campaign:', error)
	} finally {
		isLoading.value = false
	}
})
</script>

<template>
	<DefaultLayout>
		<div class="container mx-auto py-8">
			<div class="flex items-center mb-8">
				<button @click="goBack" class="mr-4 text-neutral-600 hover:text-neutral-800">
					← Back to Campaigns
				</button>
				<h1 class="text-2xl font-bold">Edit Campaign</h1>
			</div>

			<div v-if="isLoading" class="flex justify-center py-8">
				<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
			</div>

			<div v-else class="max-w-2xl">
				<div class="bg-white border border-neutral-200 rounded-lg p-6">
					<form @submit.prevent="updateCampaign" class="space-y-6">
						<div>
							<label class="block text-sm font-medium text-neutral-700 mb-1">Campaign Name</label>
							<input
								v-model="campaign.name"
								type="text"
								required
								class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
								placeholder="Enter campaign name"
							/>
						</div>

						<div>
							<label class="block text-sm font-medium text-neutral-700 mb-1">Location</label>
							<input
								v-model="campaign.location"
								type="text"
								class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
								placeholder="Enter location (optional)"
							/>
							<p class="text-xs text-neutral-500 mt-1">Location where your business primarily operates</p>
						</div>

						<div>
							<label class="block text-sm font-medium text-neutral-700 mb-1">Description</label>
							<textarea
								v-model="campaign.description"
								rows="4"
								class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
								placeholder="Enter campaign description (optional)"
							></textarea>
							<p class="text-xs text-neutral-500 mt-1">This description can help AI generate accurate prompts</p>
						</div>

						<div>
							<label class="block text-sm font-medium text-neutral-700 mb-1">Keywords</label>
							<textarea
								v-model="campaign.keywords"
								rows="3"
								class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
								placeholder="Enter keywords (optional)"
							></textarea>
							<p class="text-xs text-neutral-500 mt-1">Keywords that describe your business or campaign focus</p>
						</div>

						<div class="flex justify-end space-x-2 pt-4">
							<Button @click="goBack" variant="neutral">Cancel</Button>
							<Button v-if="hasChanges" type="submit" :disabled="isSubmitting" variant="dark">
								{{ isSubmitting ? 'Saving...' : 'Save Changes' }}
							</Button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
