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

// US States list
const states = [
	{ code: 'AL', name: 'Alabama' },
	{ code: 'AK', name: 'Alaska' },
	{ code: 'AZ', name: 'Arizona' },
	{ code: 'AR', name: 'Arkansas' },
	{ code: 'CA', name: 'California' },
	{ code: 'CO', name: 'Colorado' },
	{ code: 'CT', name: 'Connecticut' },
	{ code: 'DE', name: 'Delaware' },
	{ code: 'FL', name: 'Florida' },
	{ code: 'GA', name: 'Georgia' },
	{ code: 'HI', name: 'Hawaii' },
	{ code: 'ID', name: 'Idaho' },
	{ code: 'IL', name: 'Illinois' },
	{ code: 'IN', name: 'Indiana' },
	{ code: 'IA', name: 'Iowa' },
	{ code: 'KS', name: 'Kansas' },
	{ code: 'KY', name: 'Kentucky' },
	{ code: 'LA', name: 'Louisiana' },
	{ code: 'ME', name: 'Maine' },
	{ code: 'MD', name: 'Maryland' },
	{ code: 'MA', name: 'Massachusetts' },
	{ code: 'MI', name: 'Michigan' },
	{ code: 'MN', name: 'Minnesota' },
	{ code: 'MS', name: 'Mississippi' },
	{ code: 'MO', name: 'Missouri' },
	{ code: 'MT', name: 'Montana' },
	{ code: 'NE', name: 'Nebraska' },
	{ code: 'NV', name: 'Nevada' },
	{ code: 'NH', name: 'New Hampshire' },
	{ code: 'NJ', name: 'New Jersey' },
	{ code: 'NM', name: 'New Mexico' },
	{ code: 'NY', name: 'New York' },
	{ code: 'NC', name: 'North Carolina' },
	{ code: 'ND', name: 'North Dakota' },
	{ code: 'OH', name: 'Ohio' },
	{ code: 'OK', name: 'Oklahoma' },
	{ code: 'OR', name: 'Oregon' },
	{ code: 'PA', name: 'Pennsylvania' },
	{ code: 'RI', name: 'Rhode Island' },
	{ code: 'SC', name: 'South Carolina' },
	{ code: 'SD', name: 'South Dakota' },
	{ code: 'TN', name: 'Tennessee' },
	{ code: 'TX', name: 'Texas' },
	{ code: 'UT', name: 'Utah' },
	{ code: 'VT', name: 'Vermont' },
	{ code: 'VA', name: 'Virginia' },
	{ code: 'WA', name: 'Washington' },
	{ code: 'WV', name: 'West Virginia' },
	{ code: 'WI', name: 'Wisconsin' },
	{ code: 'WY', name: 'Wyoming' },
	{ code: 'DC', name: 'District of Columbia' }
]

// Organization data
const organization = ref({
	name: '',
	website: '',
	logo: '',
	state: '',
	is_competitor: false,
	hasDetails: false
})

// Handle organization selection from search component
const handleSelectOrganization = (org) => {
	organization.value = { ...organization.value, ...org, is_competitor: false }
}

// Create team and organization
const createTeamAndOrganization = async () => {
	if (!organization.value.name) return

	isSubmitting.value = true
	try {
		let team = await teamStore.createTeam({ name: organization.value.name })
		await organizationStore.createAndOnboardOrganization(organization.value)

		await teamStore.switchTeam(team.id)
		router.push({ name: 'dashboard' })
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
					label="Search for a website domain"
					placeholder="Enter your website domain"
					@select-organization="handleSelectOrganization"
				/>

				<!-- Organization Preview -->
				<div v-if="organization.name || organization.website" class="mt-4">
					<h3 class="text-sm font-medium text-neutral-700 mb-2">Organization Preview</h3>
					<div class="p-6 border border-neutral-200 rounded-md bg-neutral-50">
						<div class="flex items-center gap-4 mb-4">
							<OrganizationLogo :organization="organization" />
							<div>
								<h3 class="text-md font-medium">{{ organization.name }}</h3>
								<p class="text-sm text-neutral-500">{{ organization.website }}</p>
							</div>
						</div>
						
						<!-- State Selection -->
						<div class="mt-4 border-t border-neutral-200 pt-4">
							<label for="state-select" class="block text-sm font-medium text-neutral-700 mb-1">State</label>
							<select 
								id="state-select"
								v-model="organization.state"
								class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
							>
								<option value="">Select a state (optional)</option>
								<option v-for="state in states" :key="state.code" :value="state.name">{{ state.name }}</option>
							</select>
							<p class="text-xs text-neutral-500 mt-1">Select the state where this organization primarily does business</p>
						</div>
					</div>
				</div>

				<div class="mt-6 flex justify-end space-x-2">
					<!-- <Button @click="router.push({ name: 'teams.index' })" variant="neutral"> Cancel </Button> -->
					<Button @click="createTeamAndOrganization" :disabled="isSubmitting || !organization.name" variant="dark">
						{{ isSubmitting ? 'Creating...' : 'Create Team' }}
					</Button>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
