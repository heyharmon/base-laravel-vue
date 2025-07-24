<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useTeamStore } from '@/stores/teamStore'
import { useOrganizationStore } from '@/stores/organizationStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import OrganizationSearch from '@/components/OrganizationSearch.vue'
import OrganizationLogo from '@/components/organizations/OrganizationLogo.vue'

const router = useRouter()
const teamStore = useTeamStore()
const organizationStore = useOrganizationStore()
const isSubmitting = ref(false)

// Organization data
const organization = ref({
	name: '',
	website: '',
	logo: '',
	is_competitor: false,
	hasDetails: false,
	location: '',
	description: ''
})

// Handle organization selection from search component
const handleSelectOrganization = (org) => {
	organization.value = { ...organization.value, ...org, is_competitor: false }
}

// Deselect organization and reset to empty state
const deselectOrganization = () => {
	organization.value = {
		name: '',
		website: '',
		logo: '',
		is_competitor: false,
		hasDetails: false,
		location: '',
		description: ''
	}
}

// Create team and organization
const createTeamAndOrganization = async () => {
	if (!organization.value.name) return

	isSubmitting.value = true
	try {
        let team = await teamStore.createTeam({ name: organization.value.name })
        await organizationStore.createAndOnboardOrganization(team.id, organization.value)

        await teamStore.switchTeam(team.id)
        router.push({ name: 'home', params: { id: team.id } })
	} catch (error) {
		console.error('Error creating team and organization:', error)
	} finally {
		isSubmitting.value = false
	}
}
</script>

<template>
	<DefaultLayout>
		<div class="max-w-2xl mx-auto py-8">
			<div class="flex justify-between items-center mb-8">
				<h1 class="text-2xl font-bold">Create New Team</h1>
			</div>

			<div class="space-y-6">
				<!-- Organization Search -->
				<OrganizationSearch
					v-if="!organization.name && !organization.website"
					label="Search for a website domain"
					placeholder="Enter your website domain"
					@select-organization="handleSelectOrganization"
				/>

				<!-- Organization Preview -->
				<div v-if="organization.name || organization.website" class="mt-4">
					<div class="flex justify-between items-center mb-2">
						<h3 class="text-sm font-medium text-neutral-700">Organization Preview</h3>
						<button
							@click="deselectOrganization"
							class="px-3 py-1.5 bg-neutral-200 hover:bg-neutral-300 text-neutral-700 rounded-md text-sm font-medium transition-colors flex items-center gap-1"
						>
							<span>Deselect</span>
						</button>
					</div>
					<div class="p-6 border border-neutral-200 rounded-md bg-neutral-50">
						<div class="flex items-center gap-4 mb-4">
							<OrganizationLogo :organization="organization" />
							<div>
								<h3 class="text-md font-medium">{{ organization.name }}</h3>
								<p class="text-sm text-neutral-500">{{ organization.website }}</p>
							</div>
						</div>

						<!-- Organization Name Input -->
						<div class="mt-4 border-t border-neutral-200 pt-4">
							<label for="name-input" class="block text-sm font-medium text-neutral-700 mb-1">Organization Name</label>
							<input
								id="name-input"
								v-model="organization.name"
								type="text"
								placeholder="Enter organization name"
								class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
							/>
							<p class="text-xs text-neutral-500 mt-1">Customize the name of your organization</p>
						</div>
					</div>
				</div>

				<!-- Campaign Details -->
				<div v-if="organization.name || organization.website" class="mt-4">
					<h3 class="text-sm font-medium text-neutral-700 mb-2">Campaign Details</h3>
					<div class="p-6 border border-neutral-200 rounded-md bg-neutral-50 space-y-4">
						<div>
							<label class="block text-sm font-medium text-neutral-700 mb-1">Location</label>
							<input
								v-model="organization.location"
								type="text"
								class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
								placeholder="Enter location (optional)"
							/>
							<p class="text-xs text-neutral-500 mt-1">Location where your business primarily operates</p>
						</div>

						<div>
							<label class="block text-sm font-medium text-neutral-700 mb-1">Description</label>
							<textarea
								v-model="organization.description"
								rows="4"
								class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
								placeholder="Enter campaign description (optional)"
							></textarea>
							<p class="text-xs text-neutral-500 mt-1">This description can help AI generate accurate prompts</p>
						</div>
					</div>
				</div>

				<div class="mt-6 flex justify-end space-x-2">
					<Button @click="createTeamAndOrganization" :disabled="isSubmitting || !organization.name" variant="dark">
						{{ isSubmitting ? 'Creating...' : 'Create Team' }}
					</Button>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
