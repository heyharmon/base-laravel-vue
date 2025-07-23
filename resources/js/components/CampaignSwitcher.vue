<script setup>
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCampaignStore } from '@/stores/campaignStore'

const route = useRoute()
const router = useRouter()
const campaignStore = useCampaignStore()

const teamId = computed(() => route.params.teamId || route.params.id)

const switchCampaign = async (campaignId) => {
        await campaignStore.switchCampaign(teamId.value, campaignId)
        router.push({
                name: route.name,
                params: { ...route.params, campaignId }
        })
}

watch(teamId, async (newTeamId) => {
        if (newTeamId) {
                await campaignStore.fetchCampaigns(newTeamId)
        }
}, { immediate: true })
</script>

<template>
    <div class="relative">
        <select
            v-if="campaignStore.campaigns.length > 0"
            :value="campaignStore.currentCampaign?.id"
            @change="switchCampaign($event.target.value)"
            class="px-3 py-1.5 bg-white border border-neutral-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
            <option
                v-for="campaign in campaignStore.campaigns"
                :key="campaign.id"
                :value="campaign.id"
            >
                {{ campaign.name }}
            </option>
        </select>
    </div>
</template>
