<script setup>
import { onMounted } from 'vue'
import { useOrganizationStore } from '@/stores/organizationStore'
import { useRouter } from 'vue-router'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'

const organizationStore = useOrganizationStore()
const router = useRouter()

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
</script>

<template>
	<DefaultLayout>
		<div class="container mx-auto py-8">
			<div class="flex justify-between items-center mb-8">
				<h1 class="text-2xl font-bold">Keywords</h1>
				<Button @click="router.push({ name: 'organizations.create' })">
					{{ organizationStore.ownedOrganizations.length === 0 ? 'Add your organization' : 'Add competitor' }}
				</Button>
			</div>

			<!-- Loading state -->
			<div v-if="organizationStore.isLoading" class="flex justify-center py-8">
				<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
			</div>

			<!-- Error state -->
			<div
				v-else-if="organizationStore.error"
				class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"
			>
				{{ organizationStore.error }}
			</div>

			<div v-else>
				<!-- Your Organization -->
				<div class="mb-8">
					<h2 class="text-xl font-semibold mb-4">Your organization</h2>
					<div v-if="organizationStore.ownedOrganizations.length === 0" class="text-neutral-500">
						You don't have an organization yet.
					</div>
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
								<div v-if="org.website">Website: {{ org.website }}</div>
								<div v-if="org.founded">Founded: {{ org.founded }}</div>
								<div v-if="org.employee_count">Employees: {{ org.employee_count }}</div>
							</div>
							<div class="mt-4 flex space-x-2">
								<router-link
									:to="{ name: 'organizations.edit', params: { id: org.id } }"
									class="text-blue-600 hover:text-blue-800 text-sm font-medium"
								>
									Edit
								</router-link>
							</div>
						</router-link>
					</div>
				</div>

				<!-- Competitor Organizations -->
				<div>
					<h2 class="text-xl font-semibold mb-4">Competitors</h2>
					<div v-if="organizationStore.competitorOrganizations.length === 0" class="text-neutral-500">
						You haven't added any competitors yet.
					</div>
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
								<div v-if="org.website">Website: {{ org.website }}</div>
								<div v-if="org.founded">Founded: {{ org.founded }}</div>
								<div v-if="org.employee_count">Employees: {{ org.employee_count }}</div>
							</div>
							<div class="mt-4 flex space-x-2">
								<router-link
									:to="{ name: 'organizations.edit', params: { id: org.id } }"
									class="text-blue-600 hover:text-blue-800 text-sm font-medium mr-2"
								>
									Edit
								</router-link>
								<button
									@click.stop="deleteOrganization(org.id)"
									class="text-red-600 hover:text-red-800 text-sm font-medium cursor-pointer"
								>
									Delete
								</button>
							</div>
						</router-link>
					</div>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
