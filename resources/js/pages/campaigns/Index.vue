<script setup>
import { ref, computed, onMounted, defineAsyncComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCampaignStore } from '@/stores/campaignStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'

const route = useRoute()
const router = useRouter()
const campaignStore = useCampaignStore()

const teamId = computed(() => route.params.teamId)
const showCreateModal = ref(false)

// Dynamically load the CampaignCreateModal component
const CampaignCreateModal = defineAsyncComponent(() => import('@/components/campaigns/CampaignCreateModal.vue'))

const handleCampaignCreated = (campaign) => {
	router.push({ name: 'home', params: { teamId: teamId.value, campaignId: campaign.id } })
}

const deleteCampaign = async (event, campaignId) => {
	if (!confirm('Are you sure you want to delete this campaign? All associated data will be permanently deleted.')) return

	await campaignStore.deleteCampaign(teamId.value, campaignId)
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
								@click.stop.prevent="deleteCampaign($event, campaign.id)"
								class="text-red-600 hover:text-red-800 text-sm cursor-pointer"
							>
								Delete
							</button>
						</div>
					</div>
				</router-link>
			</div>
		</div>

		<!-- Create Campaign Modal - dynamically loaded -->
		<CampaignCreateModal
			v-if="showCreateModal"
			:is-open="showCreateModal"
			:team-id="teamId"
			@close="showCreateModal = false"
			@created="handleCampaignCreated"
		/>
	</DefaultLayout>
</template>
