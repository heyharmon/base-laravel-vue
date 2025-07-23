<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useOrganizationStore } from '@/stores/organizationStore'
import { useCampaignStore } from '@/stores/campaignStore'
import OrganizationLogo from '@/components/organizations/OrganizationLogo.vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import OrganizationSearch from '@/components/OrganizationSearch.vue'

const router = useRouter()
const route = useRoute()
const organizationStore = useOrganizationStore()
const campaignStore = useCampaignStore()
const teamId = route.params.teamId
const isSubmitting = ref(false)
const isLoadingDetails = ref(false)
const organization = ref({
	name: '',
	website: '',
	is_competitor: true,
	founded: null,
	employee_count: null,
	location: '',
	description: '',
	logo: '',
	hasDetails: false
})

// Handle organization selection from search component
const handleSelectOrganization = (org) => {
	organization.value = { ...organization.value, ...org, is_competitor: true }
}

// Deselect organization and reset to empty state
const deselectOrganization = () => {
	organization.value = {
		name: '',
		website: '',
		is_competitor: true,
		founded: null,
		employee_count: null,
		location: '',
		description: '',
		logo: '',
		hasDetails: false
	}
}

// Create competitor organization
const createOrganization = async () => {
	if (!organization.value.name) return

	isSubmitting.value = true
	try {
		// Create the organization
                const newOrg = await organizationStore.createOrganization(teamId, campaignStore.currentCampaign?.id, organization.value)

        router.push({ name: 'organizations.index', params: { teamId } })
	} catch (error) {
		console.error('Error creating organization:', error)
	} finally {
		isSubmitting.value = false
	}
}
</script>

<template>
	<DefaultLayout>
		<div class="max-w-2xl mx-auto py-8">
                        <div class="flex justify-between items-center mb-8">
                                <div class="flex items-center gap-4">
                                        <h1 class="text-2xl font-bold">Add Competitor</h1>
                                        <CampaignSwitcher />
                                </div>
                        </div>

			<div class="space-y-4">
				<OrganizationSearch
					v-if="!organization.name && !organization.website"
					label="Search for a website domain"
					placeholder="Enter competitor's website domain"
					@select-organization="handleSelectOrganization"
				/>

				<!-- Organization Preview -->
				<div v-if="organization.name || organization.website" class="mt-4">
					<div class="flex justify-between items-center mb-2">
						<h3 class="text-sm font-medium text-neutral-700">Competitor</h3>
						<button
							@click="deselectOrganization"
							class="px-3 py-1.5 bg-neutral-200 hover:bg-neutral-300 text-neutral-700 rounded-md text-sm font-medium transition-colors flex items-center gap-1"
						>
							<span>Deselect</span>
						</button>
					</div>
					<div class="flex items-center gap-4 p-6 border border-neutral-200 rounded-md bg-neutral-50">
						<OrganizationLogo :organization="organization" />
						<div>
							<h3 class="text-md font-medium">
								{{ organization.name }}
							</h3>
							<p class="text-sm text-neutral-500">
								{{ organization.website }}
							</p>
						</div>
					</div>
				</div>
			</div>

			<div class="mt-6 flex justify-end space-x-2">
				<Button @click="router.push({ name: 'organizations.index' })" variant="neutral"> Cancel </Button>
				<Button @click="createOrganization" :disabled="isSubmitting || !organization.name || !organization.website" variant="dark">
					{{ isSubmitting ? 'Creating...' : 'Add competitor' }}
				</Button>
			</div>
		</div>
	</DefaultLayout>
</template>
