<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCampaignStore } from '@/stores/campaignStore'
import { PopoverRoot, PopoverTrigger, PopoverContent, PopoverPortal, PopoverClose } from 'reka-ui'
import ChevronDownIcon from '@/components/icons/ChevronDownIcon.vue'

const route = useRoute()
const router = useRouter()
const campaignStore = useCampaignStore()

const teamId = computed(() => route.params.teamId || route.params.id)
const campaignId = computed(() => route.params.campaignId)
const searchQuery = ref('')

// Filtered campaigns based on search query
const filteredCampaigns = computed(() => {
	if (!campaignStore.campaigns?.length) return []
	if (!searchQuery.value.trim()) return campaignStore.campaigns

	const query = searchQuery.value.toLowerCase().trim()
	return campaignStore.campaigns.filter((campaign) => campaign.name.toLowerCase().includes(query))
})

const switchCampaign = async (campaignId) => {
	await campaignStore.switchCampaign(teamId.value, campaignId)
	router.push({
		name: route.name,
		params: { ...route.params, campaignId }
	})
}

const createCampaign = () => {
	router.push({ name: 'campaigns.create', params: { teamId: teamId.value } })
}

watch(
	teamId,
	async (newTeamId) => {
		if (newTeamId) {
			await campaignStore.fetchCampaigns(newTeamId)
		}
	},
	{ immediate: true }
)

// Keep current campaign in sync with the route
watch(
	campaignId,
	(newId) => {
		if (newId && campaignStore.campaigns.length > 0) {
			const found = campaignStore.campaigns.find((c) => c.id == newId)
			if (found) {
				campaignStore.currentCampaign = found
			}
		}
	},
	{ immediate: true }
)
</script>

<template>
	<PopoverRoot v-if="campaignStore.campaigns.length > 0">
		<PopoverTrigger as-child>
			<div
				class="flex items-center justify-between min-w-[200px] space-x-2 cursor-pointer px-3 py-1.5 rounded-md bg-white shadow-xs border border-neutral-400/70 hover:bg-neutral-100 transition-all"
			>
				<div class="flex flex-col pr-0.5">
					<span v-if="campaignStore.currentCampaign" class="text-xs text-neutral-500">Campaign</span>
					<span class="text-sm font-medium text-neutral-700 -mt-0.5">{{ campaignStore.currentCampaign?.name || 'Select Campaign' }}</span>
				</div>
				<ChevronDownIcon class="text-neutral-600" />
			</div>
		</PopoverTrigger>
		<PopoverPortal>
			<PopoverContent
				class="w-56 p-0 bg-white rounded shadow-lg overflow-hidden border border-neutral-300 z-50"
				side="bottom"
				align="start"
				:side-offset="5"
			>
				<div class="p-2">
					<p class="text-xs font-medium text-neutral-600 mb-2">Campaigns</p>
					<div class="mb-2">
						<input
							v-model="searchQuery"
							type="text"
							placeholder="Search campaigns..."
							class="w-full px-2 py-1 text-sm text-neutral-900 bg-white border border-neutral-300 rounded focus:outline-none focus:ring-1 focus:ring-neutral-400"
						/>
					</div>
					<div v-if="campaignStore.campaigns" class="space-y-1">
						<div v-if="filteredCampaigns.length > 0" class="space-y-1.5 max-h-[calc(100vh-250px)] overflow-y-auto">
							<PopoverClose as-child v-for="campaign in filteredCampaigns" :key="campaign.id">
								<div
									@click="switchCampaign(campaign.id)"
									class="flex items-center px-2 py-1 rounded cursor-pointer hover:bg-neutral-100"
									:class="{ 'bg-neutral-100': campaignStore.currentCampaign?.id === campaign.id }"
								>
									<span class="text-sm text-neutral-900">{{ campaign.name }}</span>
									<span v-if="campaignStore.currentCampaign?.id === campaign.id" class="ml-auto text-xs text-neutral-500">Current</span>
								</div>
							</PopoverClose>
						</div>
						<div
							v-if="campaignStore.campaigns && campaignStore.campaigns.length > 0 && filteredCampaigns.length === 0"
							class="text-sm text-neutral-500 py-1"
						>
							No campaigns match your search
						</div>
					</div>
					<div v-else class="text-sm text-neutral-500 py-1">Loading campaigns...</div>
				</div>
				<div class="border-t border-neutral-300 mt-1">
					<PopoverClose as-child>
						<router-link
							:to="{ name: 'campaigns.index', params: { teamId: route.params.id } }"
							class="cursor-pointer block px-3 py-2 text-sm text-neutral-900 hover:bg-neutral-100"
						>
							Edit campaigns
						</router-link>
					</PopoverClose>
					<PopoverClose as-child>
						<router-link
							:to="{ name: 'campaigns.index', params: { teamId: route.params.id } }"
							class="cursor-pointer block px-3 py-2 text-sm text-neutral-900 hover:bg-neutral-100"
						>
							Create campaign
						</router-link>
					</PopoverClose>
				</div>
			</PopoverContent>
		</PopoverPortal>
	</PopoverRoot>
</template>
