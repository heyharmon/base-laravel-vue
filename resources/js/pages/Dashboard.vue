<script setup>
import { onMounted, ref, computed, watch } from 'vue';
import { usePromptStore } from '@/stores/promptStore';
import { useJobStatusStore } from '@/stores/jobStatusStore';
import { useOrganizationStore } from '@/stores/organizationStore';
import PromptDetailSheet from '@/components/prompts/PromptDetailSheet.vue';
import PromptCreateModal from '@/components/prompts/PromptCreateModal.vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';

const promptStore = usePromptStore();
const jobStatusStore = useJobStatusStore();
const organizationStore = useOrganizationStore();

const isPromptCreateModalOpen = ref(false);
const isPromptDetailSheetOpen = ref(false);

const selectedPrompt = ref(null);
const selectedPromptId = ref(null);
const activeTab = ref('prompts'); // Default tab for mobile view
const sortOption = ref('default'); // Default sort option
const dateRange = ref({
  startDate: null,
  endDate: null
});

// Track if we have active jobs
const hasActiveJobs = computed(() => {
  return jobStatusStore.jobs && jobStatusStore.jobs.some(job =>
    job.status === 'pending' || job.status === 'processing'
  );
});

// Track completed jobs to detect when individual jobs complete
const completedJobIds = ref(new Set());

// Watch for changes in job statuses
watch(() => jobStatusStore.jobs, async (currentJobs, previousJobs) => {
  if (!previousJobs || !currentJobs) return;

  // Check for newly completed jobs
  let shouldRefresh = false;

  currentJobs.forEach(job => {
    // If the job is completed or failed and we haven't processed it yet
    if ((job.status === 'completed' || job.status === 'failed') &&
        !completedJobIds.value.has(job.job_id) &&
        job.trackable_type === 'App\\Models\\Prompt') {

      // Mark this job as processed
      completedJobIds.value.add(job.job_id);
      shouldRefresh = true;
    }
  });

  // Refresh prompts if we found newly completed jobs
  if (shouldRefresh) {
	await organizationStore.fetchVisibilityMetrics()
    await promptStore.fetchPrompts();
  }
}, { deep: true });

// Also keep the original watcher for when all jobs complete
watch(hasActiveJobs, async (currentHasActiveJobs, previousHasActiveJobs) => {
  // If we previously had active jobs but now we don't, refresh prompts
  if (previousHasActiveJobs && !currentHasActiveJobs) {
    await promptStore.fetchPrompts();
  }
}, { immediate: false });

onMounted(async () => {
  await promptStore.fetchPrompts();
  await jobStatusStore.fetchTeamJobs();
  await organizationStore.fetchOrganizations();
  await organizationStore.fetchVisibilityMetrics();
});

const openRunMenuId = ref(null);
const isRunAllMenuOpen = ref(false);

const toggleRunMenu = (id) => {
  if (openRunMenuId.value === id) {
    openRunMenuId.value = null;
  } else {
    openRunMenuId.value = id;
  }
};

const closeRunMenu = () => {
  openRunMenuId.value = null;
};

const closeRunAllMenu = () => {
  isRunAllMenuOpen.value = false;
};

const runPrompt = async (id, count = 1) => {
  await promptStore.runPrompt(id, count);
  await jobStatusStore.fetchTeamJobs();

  jobStatusStore.startAutoRefresh(1000);
};

const runAllPrompts = async (count = 1) => {
  try {
    await promptStore.runAllPrompts(count);
    await jobStatusStore.fetchTeamJobs();
    jobStatusStore.startAutoRefresh(1000);
  } catch (error) {
    console.error('Error running all prompts:', error);
  }
};

const sortedPrompts = computed(() => {
  if (!promptStore.prompts || promptStore.prompts.length === 0) return [];

  if (sortOption.value === 'default') {
    return [...promptStore.prompts];
  } else if (sortOption.value === 'mentions-asc') {
    return [...promptStore.prompts].sort((a, b) => {
      const aPercentage = a.mentions_percentage !== undefined ? a.mentions_percentage : 0;
      const bPercentage = b.mentions_percentage !== undefined ? b.mentions_percentage : 0;
      return aPercentage - bPercentage;
    });
  } else if (sortOption.value === 'mentions-desc') {
    return [...promptStore.prompts].sort((a, b) => {
      const aPercentage = a.mentions_percentage !== undefined ? a.mentions_percentage : 0;
      const bPercentage = b.mentions_percentage !== undefined ? b.mentions_percentage : 0;
      return bPercentage - aPercentage;
    });
  }

  return [...promptStore.prompts];
});

const showKeywordDetails = async (keyword) => {
  selectedKeyword.value = keyword;
  selectedKeywordId.value = keyword.id;
  isKeywordDetailSheetOpen.value = true;
};

const showPromptDetails = async (prompt) => {
  selectedPrompt.value = prompt;
  selectedPromptId.value = prompt.id;
  isPromptDetailSheetOpen.value = true;
};
</script>

<template>
  <DefaultLayout>
    <div class="flex flex-col space-y-4 mt-6">
		<!-- Top Section: Visibility Cards -->
		<div class="flex flex-wrap gap-4">

			<!-- Company Visibility Card -->
			<div v-if="organizationStore.visibilityMetrics && organizationStore.visibilityMetrics.length > 0" class="flex-1 flex">
				<div
					v-for="org in organizationStore.visibilityMetrics.filter(o => !o.is_competitor)"
					:key="org.id"
					class="bg-white p-4 rounded-lg shadow border border-neutral-200 w-full flex flex-col"
				>
					<div class="flex justify-between items-start">
						<h3 class="text-lg font-medium">{{ org.name || 'Your Organization' }}</h3>
						<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Your Organization</span>
					</div>

					<div class="mt-4">
						<div class="flex justify-between mb-1">
							<span class="text-sm font-medium">Visibility</span>
							<span class="text-sm font-medium">{{ org.visibility }}%</span>
						</div>
						<div class="w-full bg-neutral-200 rounded-full h-2">
							<div
								class="h-2 rounded-full bg-green-500"
								:style="{width: `${org.visibility}%`}"
							></div>
						</div>
						<div class="mt-2 text-sm text-neutral-600">
							<p>{{ org.total_mentions }} mentions in {{ org.total_responses }} responses</p>
						</div>
					</div>
				</div>
			</div>

			<!-- Competitors Table -->
			<div class="flex-1 flex">
				<div class="bg-white p-4 rounded-lg shadow border border-neutral-200 w-full flex flex-col">
					<h3 class="text-lg font-medium mb-4">Competitors</h3>

					<div v-if="organizationStore.isLoadingVisibility" class="flex justify-center py-4">
						<div class="animate-spin rounded-full h-6 w-6 border-b-2 border-neutral-800"></div>
					</div>

					<div v-else-if="organizationStore.visibilityMetrics && organizationStore.visibilityMetrics.filter(o => o.is_competitor).length > 0">
						<table class="min-w-full divide-y divide-neutral-200">
							<thead>
								<tr>
									<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Name</th>
									<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Visibility</th>
									<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Mentions</th>
								</tr>
							</thead>
							<tbody class="bg-white divide-y divide-neutral-200">
								<tr v-for="org in organizationStore.visibilityMetrics.filter(o => o.is_competitor).sort((a, b) => b.visibility - a.visibility)" :key="org.id">
									<td class="px-3 py-2 whitespace-nowrap text-sm">{{ org.name || 'Unnamed Competitor' }}</td>
									<td class="px-3 py-2 whitespace-nowrap text-sm">
										<div class="flex items-center">
											<div class="w-16 bg-neutral-200 rounded-full h-2 mr-2">
												<div class="h-2 rounded-full bg-red-500" :style="{width: `${org.visibility}%`}"></div>
											</div>
											<span>{{ org.visibility }}%</span>
										</div>
									</td>
									<td class="px-3 py-2 whitespace-nowrap text-sm">{{ org.total_mentions }}</td>
								</tr>
							</tbody>
						</table>
					</div>

					<div v-else class="text-center py-4 text-neutral-500 text-sm">
						No competitor data available
					</div>
				</div>
			</div>
		</div>

		<!-- Main Content -->
		<div class="flex flex-col md:flex-row h-[calc(100vh-16rem)] overflow-hidden">

			<!-- Prompts column -->
			<div class="w-full py-4 overflow-y-auto">
				<div class="mb-4">
				<div class="flex justify-between items-center">
					<h2 class="text-xl md:text-2xl font-semibold">Prompts</h2>
					<div class="flex space-x-2">
					<!-- Sort prompts -->
					<div class="relative inline-block">
						<select
						v-model="sortOption"
						class="px-3 py-1.5 bg-white text-neutral-800 border border-neutral-400 rounded-md text-xs font-medium appearance-none pr-8 cursor-pointer"
						>
						<option value="default">Default order</option>
						<option value="mentions-desc">Mentions (high to low)</option>
						<option value="mentions-asc">Mentions (low to high)</option>
						</select>
						<div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-neutral-700">
						<svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
							<path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
						</svg>
						</div>
					</div>
					<div class="relative">
						<button
						@click.stop="isRunAllMenuOpen = !isRunAllMenuOpen"
						class="px-3 py-1.5 bg-white text-neutral-800 border border-neutral-400 rounded-md text-xs font-medium hover:bg-neutral-100 transition-colors cursor-pointer flex items-center justify-center"
						:disabled="promptStore.isLoading || promptStore.loadingPromptIds.length > 0 || promptStore.isRunningAll"
						>
						<div v-if="promptStore.isRunningAll" class="animate-spin h-3 w-3 border-b-2 border-neutral-800 rounded-full mr-1"></div>
						<span>Run all prompts</span>
						</button>
						<div
						v-if="isRunAllMenuOpen"
						class="absolute right-0 mt-1 w-32 bg-white border border-neutral-300 rounded-md shadow-lg z-10"
						@click.stop
						>
						<button
							@click.stop="runAllPrompts(1); closeRunAllMenu()"
							class="w-full px-3 py-1.5 text-left text-xs hover:bg-neutral-100 transition-colors"
						>
							Run all prompts 1x
						</button>
						<button
							@click.stop="runAllPrompts(3); closeRunAllMenu()"
							class="w-full px-3 py-1.5 text-left text-xs hover:bg-neutral-100 transition-colors"
						>
							Run all prompts 3x
						</button>
						<button
							@click.stop="runAllPrompts(5); closeRunAllMenu()"
							class="w-full px-3 py-1.5 text-left text-xs hover:bg-neutral-100 transition-colors"
						>
							Run all prompts 5x
						</button>
						</div>
					</div>
					<button
						@click="isPromptCreateModalOpen = true"
						class="px-3 py-1.5 bg-neutral-800 text-white rounded-md text-xs font-medium hover:bg-neutral-700 transition-colors cursor-pointer"
					>
						Add prompt
					</button>
					</div>
				</div>
				</div>

				<div v-if="promptStore.isLoading" class="flex justify-center py-8">
				<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
				</div>

				<div v-else class="space-y-4">
				<div
					v-for="prompt in sortedPrompts"
					:key="prompt.id"
					class="flex items-start justify-between p-4 border border-neutral-300 hover:border-neutral-400 hover:bg-neutral-50 rounded-lg cursor-pointer"
					:class="{ 'border-2 border-neutral-400 bg-neutral-50': selectedPromptId === prompt.id }"
					@click="showPromptDetails(prompt)"
				>
					<div>
						<p class="text-neutral-800 text-lg">{{ prompt.content }}</p>
						<div v-if="prompt.keywords_count >= 0" class="flex items-center gap-2 text-sm text-neutral-500 mt-1">
							<p v-if="prompt.mentions_percentage !== undefined">Mentioned {{ prompt.mentions_percentage }}% of the time out of {{ prompt.responses_count }} {{ prompt.responses_count === 1 ? 'response' : 'responses' }}</p>
							<p>•</p>
							<p class="">{{ prompt.keywords_count }} keyword {{ prompt.keywords_count === 1 ? 'occurrence' : 'occurrences' }}</p>
						</div>
						<div v-else class="text-sm text-neutral-500 mt-1">New prompt</div>

						<!-- Show job status indicator if there's an active job for this prompt -->
						<div v-if="jobStatusStore.jobs?.some(job => job.trackable_id === prompt.id && (job.status === 'pending' || job.status === 'processing'))" class="mt-2 flex items-center text-sm text-blue-600">
						<div class="animate-spin h-3 w-3 border-b-2 border-blue-600 rounded-full mr-2"></div>
						<span>Processing...</span>
						</div>
					</div>
					<div class="flex justify-end space-x-2">
					<div class="relative">
						<button
						@click.stop="toggleRunMenu(prompt.id)"
						class="px-3 bg-white text-neutral-800 border border-neutral-400 rounded-md text-xs font-medium hover:bg-neutral-100 transition-colors cursor-pointer flex items-center justify-center min-w-[40px]"
						:disabled="promptStore.loadingPromptIds.includes(prompt.id)"
						>
						<div v-if="promptStore.loadingPromptIds.includes(prompt.id)" class="animate-spin h-3 w-3 border-b-2 border-neutral-800 rounded-full"></div>
						<span v-else>Run</span>
						</button>
						<div
						v-if="openRunMenuId === prompt.id"
						class="absolute right-0 mt-1 w-20 bg-white border border-neutral-300 rounded-md shadow-lg z-10"
						@click.stop
						>
						<button
							@click.stop="runPrompt(prompt.id, 1); closeRunMenu()"
							class="w-full px-3 py-1.5 text-left text-xs hover:bg-neutral-100 transition-colors"
						>
							Run 1x
						</button>
						<button
							@click.stop="runPrompt(prompt.id, 3); closeRunMenu()"
							class="w-full px-3 py-1.5 text-left text-xs hover:bg-neutral-100 transition-colors"
						>
							Run 3x
						</button>
						<button
							@click.stop="runPrompt(prompt.id, 5); closeRunMenu()"
							class="w-full px-3 py-1.5 text-left text-xs hover:bg-neutral-100 transition-colors"
						>
							Run 5x
						</button>
						</div>
					</div>
					<button
						@click.stop="promptStore.deletePrompt(prompt.id)"
						class="-mr-2 p-1.5 text-neutral-400 hover:text-neutral-600 transition-colors cursor-pointer"
						aria-label="Delete prompt"
					>
						<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
						<path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
						</svg>
					</button>
					</div>
				</div>
				</div>
			</div>
		</div>
    </div>
  </DefaultLayout>

  <!-- Prompt Modal -->
  <PromptCreateModal
    :is-open="isPromptCreateModalOpen"
    @close="isPromptCreateModalOpen = false"
  />

  <!-- Prompt Detail Sheet -->
  <PromptDetailSheet
    :is-open="isPromptDetailSheetOpen"
    :prompt="selectedPrompt"
    :prompt-id="selectedPrompt?.id"
    @close="() => {
      isPromptDetailSheetOpen = false;
      selectedPromptId = null;
    }"
  />
</template>
