<script setup>
import { onMounted, ref, watch } from 'vue'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import { useOrganizationStore } from '@/stores/organizationStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'

const jobStatusStore = useJobStatusStore()
const organizationStore = useOrganizationStore()

// Track completed jobs to detect when individual jobs complete
const completedJobIds = ref(new Set())

// Watch for changes in job statuses
watch(
	() => jobStatusStore.jobs,
	async (currentJobs, previousJobs) => {
		if (!previousJobs || !currentJobs) return

		// Check for newly completed jobs
		let shouldRefresh = false

		currentJobs.forEach((job) => {
			// If the job is completed or failed and we haven't processed it yet
			if (
				(job.status === 'completed' || job.status === 'failed') &&
				!completedJobIds.value.has(job.job_id) &&
				job.trackable_type === 'App\\Models\\Prompt'
			) {
				// Mark this job as processed
				completedJobIds.value.add(job.job_id)
				shouldRefresh = true
			}
		})

		// Refresh prompts if we found newly completed jobs
		if (shouldRefresh) {
			await organizationStore.fetchVisibilityMetrics()
		}
	},
	{ deep: true }
)

onMounted(async () => {
	await organizationStore.fetchVisibilityMetrics()
})
</script>

<template>
	<DefaultLayout>
		<div class="mt-6">
			<div class="flex items-center gap-2 mb-4">
				<h1 class="text-2xl font-bold">Rankings</h1>
				<div v-if="organizationStore.isLoadingVisibility" class="animate-spin rounded-full size-4 border-b-2 border-neutral-800"></div>
			</div>

			<div v-if="organizationStore.visibilityMetrics && organizationStore.visibilityMetrics.length > 0">
				<table class="min-w-full divide-y divide-neutral-200">
					<thead>
						<tr>
							<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider w-1/10">Org</th>
							<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider w-1/3">Visibility</th>
							<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider w-1/12"></th>
							<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider w-1/12">Mentions</th>
							<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider w-1/12">Total responses</th>
						</tr>
					</thead>
					<tbody class="bg-white divide-y divide-neutral-200">
						<tr v-for="org in organizationStore.visibilityMetrics.sort((a, b) => b.visibility - a.visibility)" :key="org.id">
							<td class="px-3 py-2 flex items-center gap-2 whitespace-nowrap font-medium">
								<img
									:src="`https://cdn.brandfetch.io/${org.website}/w/400/h/400?c=1idaplhOcH8x9kYGESa`"
									:alt="org.name + ' logo'"
									class="size-6 object-contain bg-white rounded-md border border-neutral-200"
								/>
								<span>{{ org.name || (org.is_competitor ? 'Unnamed Competitor' : 'Your Organization') }}</span>
								<span v-if="!org.is_competitor" class="bg-green-100 text-green-800 text-xs px-2 py-0.5 rounded-md">You</span>
							</td>
							<td class="pl-3 pr-4 py-2 whitespace-nowrap text-sm">
								<div class="w-full bg-neutral-200 rounded-full h-2 mr-2">
									<div
										class="h-2 rounded-full"
										:class="org.is_competitor ? 'bg-red-500' : 'bg-green-500'"
										:style="{ width: `${org.visibility}%` }"
									></div>
								</div>
							</td>
							<td class="py-2 whitespace-nowrap text-sm">{{ org.visibility }}%</td>
							<td class="px-3 py-2 whitespace-nowrap text-sm">{{ org.total_mentions }}</td>
							<td class="px-3 py-2 whitespace-nowrap text-sm">{{ org.total_responses }}</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div v-else class="text-center py-4 text-neutral-500 text-sm">No organization data available</div>
		</div>
	</DefaultLayout>
</template>
