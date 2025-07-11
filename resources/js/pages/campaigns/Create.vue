<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useCampaignStore } from '@/stores/campaignStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'

const router = useRouter()
const campaignStore = useCampaignStore()
const isSubmitting = ref(false)
const campaign = ref({
	name: '',
	description: ''
})

const createCampaign = async () => {
	if (!campaign.value.name) return

	isSubmitting.value = true
	try {
		const newCampaign = await campaignStore.createCampaign(campaign.value)
		campaignStore.currentCampaign = newCampaign
		campaignStore.saveCampaignToStorage(newCampaign)
		router.push('/')
	} catch (error) {
		console.error('Error creating campaign:', error)
	} finally {
		isSubmitting.value = false
	}
}
</script>

<template>
	<DefaultLayout>
		<div class="max-w-2xl mx-auto py-8">
			<div class="flex justify-between items-center mb-8">
				<h1 class="text-2xl font-bold">Create Campaign</h1>
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
				<Button @click="router.push('/')" variant="neutral"> Cancel </Button>
				<Button @click="createCampaign" :disabled="isSubmitting || !campaign.name.trim()" variant="dark">
					{{ isSubmitting ? 'Creating...' : 'Create Campaign' }}
				</Button>
			</div>
		</div>
	</DefaultLayout>
</template>
