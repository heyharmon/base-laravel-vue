<script setup>
import { onMounted, ref } from 'vue'
import { useOrganizationStore } from '@/stores/organizationStore'
import { useRouter } from 'vue-router'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'

const organizationStore = useOrganizationStore()
const router = useRouter()
const isGeneratingCompetitors = ref(false)
const competitorsMessage = ref(null)

onMounted(async () => {
	await organizationStore.fetchOrganizations()
})

const deleteOrganization = async (organizationId) => {
	if (!confirm('Are you sure you want to delete this organization? This action cannot be undone.')) {
		return
	}

	try {
		await organizationStore.deleteOrganization(organizationId)
	} catch (error) {
		console.error('Error deleting organization:', error)
	}
}

const acceptRecommendedCompetitor = async (organizationId) => {
	try {
		await organizationStore.updateOrganization(organizationId, { is_recommended: false })
	} catch (error) {
		console.error('Error accepting recommended competitor:', error)
	}
}

const denyRecommendedCompetitor = async (organizationId) => {
	try {
		await organizationStore.deleteOrganization(organizationId)
	} catch (error) {
		console.error('Error denying recommended competitor:', error)
	}
}

const generateCompetitors = async () => {
	isGeneratingCompetitors.value = true
	competitorsMessage.value = 'Competitor generation jobs are now running. Refresh the page when queued runs are complete.'

	await organizationStore.generateCompetitors()

	setTimeout(() => (isGeneratingCompetitors.value = false), 1000)
	setTimeout(() => (competitorsMessage.value = null), 10000)
}
</script>

<template>
	<DefaultLayout>
		<div class="container mx-auto py-8">
			<div class="flex justify-between items-center mb-3">
				<h1 class="text-2xl font-bold">Keywords</h1>
				<div class="flex space-x-2">
					<Button
						v-if="organizationStore.ownedOrganizations.length > 0"
						@click="generateCompetitors"
						:disabled="isGeneratingCompetitors"
						variant="outline"
					>
						<span v-if="isGeneratingCompetitors" class="flex items-center">
							<span class="animate-spin h-4 w-4 mr-2 border-t-2 border-b-2 border-neutral-900 rounded-full"></span>
							Generating...
						</span>
						<span v-else>Generate competitors</span>
					</Button>
					<Button @click="router.push({ name: 'organizations.create' })">
						{{ organizationStore.ownedOrganizations.length === 0 ? 'Add your organization' : 'Add competitor' }}
					</Button>
				</div>
			</div>

			<!-- Competitors generation message -->
			<div
				v-if="!organizationStore.error && competitorsMessage"
				class="p-4 mb-3 bg-green-50 border border-green-200 text-green-800 rounded-lg flex items-center gap-2"
			>
				<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" viewBox="0 0 20 20" fill="currentColor">
					<path
						fill-rule="evenodd"
						d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
						clip-rule="evenodd"
					/>
				</svg>
				<span>{{ competitorsMessage }}</span>
			</div>

			<!-- Loading state -->
			<div v-if="organizationStore.isLoading" class="flex justify-center py-8">
				<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
			</div>

			<!-- Error state -->
			<div v-else-if="organizationStore.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
				{{ organizationStore.error }}
			</div>

			<div v-else>
				<!-- Your Organization -->
				<div class="mt-8 mb-8">
					<h2 class="text-xl font-semibold mb-4">Your organization</h2>
					<div v-if="organizationStore.ownedOrganizations.length === 0" class="text-neutral-500">You don't have an organization yet.</div>
					<div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
						<router-link
							v-for="org in organizationStore.ownedOrganizations"
							:key="org.id"
							:to="{ name: 'organizations.edit', params: { id: org.id } }"
							class="bg-white border border-neutral-200 p-4 rounded-lg shadow cursor-pointer hover:bg-neutral-50 transition-all"
						>
							<div class="flex justify-between items-start">
								<h3 class="text-lg font-medium">{{ org.name || 'Unnamed Organization' }}</h3>
								<img
									v-if="org.website"
									:src="`https://cdn.brandfetch.io/${org.website}/w/400/h/400?c=1idaplhOcH8x9kYGESa`"
									:alt="org.name + ' logo'"
									class="h-10 w-10 object-contain bg-white rounded-md border border-neutral-200"
								/>
								<!-- <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Your organization</span> -->
							</div>
							<div class="mt-2 text-sm text-neutral-600">
								<div v-if="org.website">{{ org.website }}</div>
								<div v-if="org.founded">Founded: {{ org.founded }}</div>
								<div v-if="org.employee_count">Employees: {{ org.employee_count }}</div>
							</div>
							<div class="mt-4 flex space-x-2">
								<button class="text-blue-600 hover:text-blue-800 text-sm font-medium cursor-pointer">Edit</button>
							</div>
						</router-link>
					</div>
				</div>

				<!-- Competitor Organizations -->
				<div class="mb-8">
					<h2 class="text-xl font-semibold mb-4">Competitors</h2>
					<div v-if="organizationStore.competitorOrganizations.length === 0" class="text-neutral-500">You haven't added any competitors yet.</div>
					<div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
						<router-link
							v-for="org in organizationStore.competitorOrganizations"
							:key="org.id"
							:to="{ name: 'organizations.edit', params: { id: org.id } }"
							class="bg-white border border-neutral-200 p-4 rounded-lg shadow cursor-pointer hover:bg-neutral-50 transition-all"
						>
							<div class="flex justify-between items-start">
								<h3 class="text-lg font-medium">{{ org.name || 'Unnamed Competitor' }}</h3>
								<img
									v-if="org.website"
									:src="`https://cdn.brandfetch.io/${org.website}/w/400/h/400?c=1idaplhOcH8x9kYGESa`"
									:alt="org.name + ' logo'"
									class="h-10 w-10 object-contain bg-white rounded-md border border-neutral-200"
								/>
								<!-- <span class="bg-neutral-200 text-neutral-800 text-xs px-2 py-1 rounded">Competitor</span> -->
							</div>
							<div class="mt-2 text-sm text-neutral-600">
								<div v-if="org.website">{{ org.website }}</div>
								<div v-if="org.founded">Founded: {{ org.founded }}</div>
								<div v-if="org.employee_count">Employees: {{ org.employee_count }}</div>
							</div>
							<div class="mt-4 flex space-x-2">
								<button class="text-blue-600 hover:text-blue-800 text-sm font-medium cursor-pointer">Edit</button>
							</div>
						</router-link>
					</div>
				</div>

				<!-- Recommended Competitors -->
				<div v-if="organizationStore.recommendedCompetitors.length > 0">
					<h2 class="text-xl font-semibold mb-4">Recommended Competitors</h2>
					<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
						<div
							v-for="org in organizationStore.recommendedCompetitors"
							:key="org.id"
							class="bg-white border border-neutral-200 p-4 rounded-lg shadow"
						>
							<div class="flex justify-between items-start">
								<h3 class="text-lg font-medium">{{ org.name || 'Unnamed Competitor' }}</h3>
								<img
									v-if="org.website"
									:src="`https://cdn.brandfetch.io/${org.website}/w/400/h/400?c=1idaplhOcH8x9kYGESa`"
									:alt="org.name + ' logo'"
									class="h-10 w-10 object-contain bg-white rounded-md border border-neutral-200"
								/>
							</div>
							<div class="mt-2 text-sm text-neutral-600">
								<div v-if="org.website">{{ org.website }}</div>
								<div v-if="org.founded">Founded: {{ org.founded }}</div>
								<div v-if="org.employee_count">Employees: {{ org.employee_count }}</div>
							</div>
							<div class="mt-4 flex space-x-2">
								<button
									@click="acceptRecommendedCompetitor(org.id)"
									class="bg-green-100 hover:bg-green-200 text-green-800 text-sm px-3 py-1 rounded transition-colors cursor-pointer"
								>
									Accept
								</button>
								<button
									@click="denyRecommendedCompetitor(org.id)"
									class="bg-red-100 hover:bg-red-200 text-red-800 text-sm px-3 py-1 rounded transition-colors cursor-pointer"
								>
									Deny
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
