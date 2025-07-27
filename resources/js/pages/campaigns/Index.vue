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
const showCreateModal = ref(false)
const newCampaign = ref({
	name: '',
	description: '',
	location: ''
})
const isSubmitting = ref(false)

const createCampaign = async () => {
	if (!newCampaign.value.name) return
	isSubmitting.value = true
	try {
		const campaign = await campaignStore.createCampaign(teamId.value, {
			is_default: false,
			name: newCampaign.value.name,
			description: newCampaign.value.description,
			location: newCampaign.value.location
		})
		showCreateModal.value = false
		newCampaign.value = { name: '', description: '', location: '' }
		router.push({ name: 'home', params: { teamId: teamId.value, campaignId: campaign.id } })
	} catch (error) {
		console.error('Error creating campaign:', error)
	} finally {
		isSubmitting.value = false
	}
}

const deleteCampaign = async (campaignId) => {
	if (!confirm('Are you sure you want to delete this campaign? All associated data will be permanently deleted.')) return
	try {
		await campaignStore.deleteCampaign(teamId.value, campaignId)
	} catch (error) {
		console.error('Error deleting campaign:', error)
		alert(error.message || 'Failed to delete campaign')
	}
}

onMounted(() => {
	campaignStore.fetchCampaigns(teamId.value)
})
</script>

<template>
	<DefaultLayout>
		<div class="container mx-auto py-8">
			<div class="flex justify-between items-center mb-8">
				<h1 class="text-2xl font-bold">Campaigns</h1>
				<Button @click="showCreateModal = true">Create Campaign</Button>
			</div>

			<div v-if="campaignStore.isLoading" class="flex justify-center py-8">
				<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
			</div>

			<div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
				<router-link
					v-for="campaign in campaignStore.campaigns"
					:key="campaign.id"
					:to="{ name: 'home', params: { teamId: teamId, campaignId: campaign.id } }"
					class="group block bg-white border border-neutral-200 rounded-lg p-6 hover:shadow-lg transition-shadow cursor-pointer"
				>
					<div class="flex justify-between items-start mb-4">
						<h3 class="text-lg font-semibold">{{ campaign.name }}</h3>
						<span v-if="campaign.is_default" class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">Default</span>
					</div>
					<p v-if="campaign.description" class="text-neutral-600 text-sm mb-4">{{ campaign.description }}</p>
					<div class="flex justify-between items-center">
						<router-link
							:to="{ name: 'home', params: { teamId: teamId, campaignId: campaign.id } }"
							class="text-blue-600 hover:text-blue-800 text-sm font-medium opacity-0 group-hover:opacity-100 transition-opacity"
							@click.stop
							>View Rankings →</router-link
						>
						<div class="flex space-x-2">
							<router-link
								:to="{ name: 'campaigns.edit', params: { teamId: teamId, campaignId: campaign.id } }"
								class="text-neutral-600 hover:text-neutral-800 text-sm"
								@click.stop
								>Edit</router-link
							>
							<button
								v-if="!campaign.is_default"
								@click.stop="deleteCampaign(campaign.id)"
								class="text-red-600 hover:text-red-800 text-sm cursor-pointer"
							>
								Delete
							</button>
						</div>
					</div>
				</router-link>
			</div>
		</div>

		<div v-if="showCreateModal" class="fixed inset-0 bg-neutral-300/50 flex items-center justify-center z-50">
			<div class="bg-white rounded-lg p-6 w-full max-w-md">
				<h2 class="text-xl font-bold mb-4">Create new campaign</h2>
				<div class="mb-4">
					<label class="block text-sm font-medium text-neutral-700 mb-1">Campaign Name</label>
					<input
						v-model="newCampaign.name"
						type="text"
						class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
						placeholder="Enter campaign name"
					/>
				</div>
				<div class="mb-4">
					<label class="block text-sm font-medium text-neutral-700 mb-1">Location (optional)</label>
					<input
						v-model="newCampaign.location"
						type="text"
						class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
						placeholder="Enter location"
					/>
					<p class="text-xs text-neutral-500 mt-1">Location where your business primarily operates</p>
				</div>
				<div class="mb-4">
					<label class="block text-sm font-medium text-neutral-700 mb-1">Description (optional)</label>
					<textarea
						v-model="newCampaign.description"
						rows="3"
						class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
						placeholder="Enter campaign description"
					></textarea>
					<p class="text-xs text-neutral-500">This description can help AI generate accurate prompts</p>
				</div>
				<div class="flex justify-end space-x-2">
					<Button @click="showCreateModal = false" variant="neutral">Cancel</Button>
					<Button @click="createCampaign" :disabled="isSubmitting || !newCampaign.name" variant="dark">{{
						isSubmitting ? 'Creating...' : 'Create Campaign'
					}}</Button>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
