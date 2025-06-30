<script setup>
import { ref, computed, onMounted } from 'vue'
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
	location: '',
	industry_id: null,
	is_competitor: false,
	hasDetails: false
})

// Industry dropdown state
const showAddIndustry = ref(false)
const newIndustryName = ref('')
const isCreatingIndustry = ref(false)

onMounted(async () => {
	await organizationStore.fetchIndustries()
})

const selectedIndustry = computed({
	get() {
		return organization.value.industry_id || ''
	},
	set(value) {
		organization.value.industry_id = value
	}
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
		location: '',
		industry_id: null,
		is_competitor: false,
		hasDetails: false
	}
}

const addNewIndustry = async () => {
	if (!newIndustryName.value.trim()) return
	
	isCreatingIndustry.value = true
	try {
		const industry = await organizationStore.createIndustry({ name: newIndustryName.value.trim() })
		selectedIndustry.value = industry.id
		newIndustryName.value = ''
		showAddIndustry.value = false
	} catch (error) {
		console.error('Error creating industry:', error)
	} finally {
		isCreatingIndustry.value = false
	}
}

const cancelAddIndustry = () => {
	newIndustryName.value = ''
	showAddIndustry.value = false
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

						<!-- Location Input -->
						<div class="mt-4 border-t border-neutral-200 pt-4">
							<label for="location-input" class="block text-sm font-medium text-neutral-700 mb-1">Location</label>
							<input
								id="location-input"
								v-model="organization.location"
								type="text"
								placeholder="Enter location (optional)"
								class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
							/>
							<p class="text-xs text-neutral-500 mt-1">Enter the location where this organization primarily does business</p>
						</div>

						<!-- Industry Input -->
						<div class="mt-4 border-t border-neutral-200 pt-4">
							<label class="block text-sm font-medium text-neutral-700 mb-1">Industry</label>
							<div v-if="!showAddIndustry" class="space-y-2">
								<select
									v-model="selectedIndustry"
									class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
								>
									<option value="">Select an industry (optional)</option>
									<option v-for="industry in organizationStore.industries" :key="industry.id" :value="industry.id">
										{{ industry.name }}
									</option>
								</select>
								<button
									@click="showAddIndustry = true"
									type="button"
									class="text-sm text-blue-600 hover:text-blue-800 font-medium"
								>
									+ Add new industry
								</button>
							</div>
							<div v-else class="space-y-2">
								<input
									v-model="newIndustryName"
									type="text"
									placeholder="Enter new industry name"
									class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
									@keyup.enter="addNewIndustry"
									@keyup.escape="cancelAddIndustry"
								/>
								<div class="flex gap-2">
									<button
										@click="addNewIndustry"
										type="button"
										:disabled="isCreatingIndustry || !newIndustryName.trim()"
										class="text-sm bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
									>
										{{ isCreatingIndustry ? 'Adding...' : 'Add' }}
									</button>
									<button
										@click="cancelAddIndustry"
										type="button"
										class="text-sm text-neutral-600 hover:text-neutral-800 px-3 py-1"
									>
										Cancel
									</button>
								</div>
							</div>
							<p class="text-xs text-neutral-500 mt-1">Specify the industry this organization operates in</p>
						</div>

						<!-- Description Input -->
						<div class="mt-4 border-t border-neutral-200 pt-4">
							<label for="description-input" class="block text-sm font-medium text-neutral-700 mb-1">Description</label>
							<textarea
								id="description-input"
								v-model="organization.description"
								rows="3"
								placeholder="Enter organization description (optional)"
								class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
							></textarea>
							<p class="text-xs text-neutral-500 mt-1">Provide a brief description of this organization</p>
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
