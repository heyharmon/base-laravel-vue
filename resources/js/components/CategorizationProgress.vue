<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { useCategorizationStore } from '@/stores/categorizationStore';

const categorizationStore = useCategorizationStore();

const showProgress = computed(() => categorizationStore.hasActiveJobs);

onMounted(() => {
  categorizationStore.fetchActiveJobs();
});

onUnmounted(() => {
  categorizationStore.cleanup();
});

const formatJobType = (type) => {
  switch (type) {
    case 'single':
      return 'Single Transaction';
    case 'batch':
      return 'Batch';
    case 'all':
      return 'All Uncategorized';
    default:
      return type;
  }
};
</script>

<template>
  <div v-if="showProgress" class="fixed bottom-4 right-4 bg-white border border-neutral-200 rounded-lg shadow-lg p-4 max-w-sm z-50">
    <div class="flex items-center justify-between mb-2">
      <h4 class="font-medium text-sm">AI Categorization</h4>
      <div class="text-xs text-neutral-500">
        {{ categorizationStore.totalProcessedTransactions }} / {{ categorizationStore.totalActiveTransactions }}
      </div>
    </div>

    <div class="w-full bg-neutral-200 rounded-full h-2 mb-3">
      <div
        class="bg-blue-600 h-2 rounded-full transition-all duration-300"
        :style="{ width: categorizationStore.overallProgress + '%' }"
      ></div>
    </div>

    <div class="space-y-2 max-h-40 overflow-y-auto">
      <div
        v-for="job in categorizationStore.activeJobs"
        :key="job.id"
        class="text-xs"
      >
        <div class="flex justify-between items-center mb-1">
          <span class="text-neutral-600">{{ formatJobType(job.type) }}</span>
          <span class="text-neutral-500">{{ job.processed_transactions }}/{{ job.total_transactions }}</span>
        </div>
        <div class="w-full bg-neutral-200 rounded-full h-1">
          <div
            class="bg-green-500 h-1 rounded-full transition-all duration-300"
            :style="{ width: job.progress_percentage + '%' }"
          ></div>
        </div>
      </div>
    </div>

    <div class="text-xs text-neutral-500 mt-2">
      Categorizing transactions with AI...
    </div>
  </div>
</template>
