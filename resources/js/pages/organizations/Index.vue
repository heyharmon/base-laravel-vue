<script setup>
import { onMounted, ref, computed, watch } from 'vue'
import { useOrganizationStore } from '@/stores/organizationStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import { useRouter } from 'vue-router'
import moment from 'moment'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'

const organizationStore = useOrganizationStore()
const jobStatusStore = useJobStatusStore()
const router = useRouter()
const isGeneratingCompetitors = ref(false)

const activeCompetitorJobs = computed(() => {
	return jobStatusStore.jobs.filter(
		(job) => job.job_class.includes('FindCompetitorsInPastResponsesJob') && (job.status === 'pending' || job.status === 'processing')
	)
})

// Check if organization was created within the last 24 hours
const isNewOrganization = (createdAt) => {
	if (!createdAt) return false
	return moment().diff(moment(createdAt), 'hours') <= 24
}

watch(
	activeCompetitorJobs,
	(newJobs, oldJobs) => {
		if (newJobs.length > 0) {
			// Jobs are running
			isGeneratingCompetitors.value = true
		} else if (oldJobs.length > 0 && newJobs.length === 0) {
			// Jobs were running but now they're done
			isGeneratingCompetitors.value = false
			organizationStore.fetchOrganizations()
		}
	},
	{ deep: true }
)

onMounted(async () => {
	await organizationStore.fetchOrganizations()
	await jobStatusStore.pollTeamJobs()
})
</script>

<template>
	<DefaultLayout>
		<div class="container mx-auto py-6">
			<div class="flex justify-between items-center mb-3">
				<h1 class="text-2xl font-bold">Keywords</h1>
				<div class="flex space-x-2">
					<Button
						v-if="organizationStore.ownedOrganizations.length > 0"
						@click="organizationStore.generateCompetitors()"
						:disabled="isGeneratingCompetitors"
						variant="outline"
					>
						{{ isGeneratingCompetitors ? 'Generating...' : 'Generate competitors' }}
					</Button>
					<Button @click="router.push({ name: 'organizations.create' })">
						{{ organizationStore.ownedOrganizations.length === 0 ? 'Add your organization' : 'Add competitor' }}
					</Button>
				</div>
			</div>

			<!-- Competitors generation message -->
			<div
				v-if="!organizationStore.error && isGeneratingCompetitors"
				class="p-4 mb-3 bg-green-50 border border-green-200 text-green-800 rounded-lg flex items-center gap-2"
			>
				<span class="animate-spin h-4 w-4 mr-2 border-t-2 border-b-2 border-green-700 rounded-full"></span>
				<span
					>Competitor generation jobs are now running. Checking {{ activeCompetitorJobs.length }} prompt
					{{ activeCompetitorJobs.length === 1 ? 'response' : 'responses' }}.</span
				>
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
							</div>
							<div class="text-sm text-neutral-600">
								<div v-if="org.website">{{ org.website }}</div>
								<div v-if="org.founded">Founded: {{ org.founded }}</div>
								<div v-if="org.employee_count">Employees: {{ org.employee_count }}</div>
								<div class="mt-1 flex items-center">
									<span class="font-medium text-neutral-700">{{ org.keywords_count }}</span>
									<span class="ml-1 text-neutral-500">{{ org.keywords_count === 1 ? 'keyword' : 'keywords' }}</span>
								</div>
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
								<div>
									<div v-if="isNewOrganization(org.created_at)" class="mb-1">
										<span class="inline-block px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 rounded-full">
											Added {{ moment(org.created_at).fromNow() }}
										</span>
									</div>
									<h3 class="text-lg font-medium">{{ org.name || 'Unnamed Competitor' }}</h3>
								</div>
								<img
									v-if="org.website"
									:src="`https://cdn.brandfetch.io/${org.website}/w/400/h/400?c=1idaplhOcH8x9kYGESa`"
									:alt="org.name + ' logo'"
									class="h-10 w-10 object-contain bg-white rounded-md border border-neutral-200"
								/>
							</div>
							<div class="text-sm text-neutral-600">
								<div v-if="org.website">{{ org.website }}</div>
								<div v-if="org.founded">Founded: {{ org.founded }}</div>
								<div v-if="org.employee_count">Employees: {{ org.employee_count }}</div>
								<div class="mt-1 flex items-center">
									<span class="font-medium text-neutral-700">{{ org.keywords_count }}</span>
									<span class="ml-1 text-neutral-500">{{ org.keywords_count === 1 ? 'keyword' : 'keywords' }}</span>
								</div>
							</div>
							<div class="mt-4 flex space-x-2">
								<button class="text-blue-600 hover:text-blue-800 text-sm font-medium cursor-pointer">Edit</button>
							</div>
						</router-link>
					</div>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
