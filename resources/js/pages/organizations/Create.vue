<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useOrganizationStore } from '@/stores/organizationStore'
import { useKeywordStore } from '@/stores/keywordStore'
import api from '@/services/api'
import KeywordSuggestions from '@/components/keywords/KeywordSuggestions.vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import Spinner from '@/components/ui/Spinner.vue'
import OrganizationSearch from '@/components/OrganizationSearch.vue'

const router = useRouter()
const organizationStore = useOrganizationStore()
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
	industry: '',
	hasDetails: false
})
const keywords = ref([])

// Handle organization selection from search component
const handleSelectOrganization = (org) => {
	organization.value = { ...organization.value, ...org, is_competitor: true }
}

// Create competitor organization
const createOrganization = async () => {
	if (!organization.value.name) return

	isSubmitting.value = true
	try {
		// Create the organization
		const newOrg = await organizationStore.createOrganization(organization.value)
		
		// Create keywords if any were generated
		if (keywords.value.length > 0 && newOrg && newOrg.id) {
			const keywordStore = useKeywordStore()
			const promises = keywords.value.map((keyword) => 
				keywordStore.createKeyword(newOrg.id, { name: keyword })
			)
			await Promise.all(promises)
		}
		
		router.push({ name: 'organizations.index' })
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
				<h1 class="text-2xl font-bold">Add Competitor</h1>
			</div>

			<div class="space-y-4">
				<OrganizationSearch label="Search" placeholder="Enter competitor's website domain" @select-organization="handleSelectOrganization" />

				<!-- Organization Preview -->
				<div v-if="organization.name || organization.website" class="mt-4">
					<h3 class="text-sm font-medium text-neutral-700 mb-2">Competitor</h3>
					<div class="flex items-center gap-4 p-6 border border-neutral-200 rounded-md bg-neutral-50">
						<img
							v-if="organization.logo"
							:src="organization.logo"
							:alt="organization.name + ' logo'"
							class="h-16 w-16 object-contain bg-white rounded-md border border-neutral-200"
						/>
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

				<!-- Keyword Suggestions -->
				<div v-if="organization.website" class="mt-6">
					<!-- <h3 class="text-sm font-medium text-neutral-700 mb-2">Keyword suggestions for {{ organization.name }}</h3> -->
					<KeywordSuggestions :domain="organization.website" @update:keywords="keywords = $event" />
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
