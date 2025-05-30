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
	is_competitor: false,
	founded: null,
	employee_count: null,
	location: '',
	description: '',
	logo: '',
	industry: '',
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
					<div class="flex items-center gap-4 p-6 border border-neutral-200 rounded-md bg-neutral-50">
						<OrganizationLogo :organization="organization" />
						<div>
							<h3 class="text-md font-medium">{{ organization.name }}</h3>
							<p class="text-sm text-neutral-500">{{ organization.website }}</p>
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
