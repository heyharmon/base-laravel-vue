<script setup>
import { onMounted } from 'vue';
import { useCategorizationStore } from '@/stores/categorizationStore';
import DefaultLayout from '@/layouts/DefaultLayout.vue';

const categorizationStore = useCategorizationStore();

onMounted(async () => {
  await categorizationStore.fetchJobHistory();
});

const formatDate = (date) => {
  return new Date(date).toLocaleString();
};

const getStatusColor = (status) => {
  switch (status) {
    case 'completed':
      return 'bg-green-100 text-green-800';
    case 'processing':
      return 'bg-blue-100 text-blue-800';
    case 'failed':
      return 'bg-red-100 text-red-800';
    case 'pending':
      return 'bg-yellow-100 text-yellow-800';
    default:
      return 'bg-neutral-100 text-neutral-800';
  }
};

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
  <DefaultLayout>
    <div class="container mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold">AI Categorization History</h1>
        <router-link
          to="/banking/transactions"
          class="text-blue-600 hover:text-blue-800"
        >
          ← Back to Transactions
        </router-link>
      </div>

      <div v-if="categorizationStore.isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
      </div>

      <div v-else-if="categorizationStore.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        {{ categorizationStore.error }}
      </div>

      <div v-else>
        <div v-if="categorizationStore.jobHistory.length === 0" class="text-center py-12">
          <p class="text-neutral-500">No categorization jobs yet</p>
        </div>

        <div v-else class="bg-white border border-neutral-200 rounded-lg overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-neutral-100 border-b border-neutral-200">
                <tr>
                  <th class="px-4 py-3 text-left font-medium">Type</th>
                  <th class="px-4 py-3 text-left font-medium">Status</th>
                  <th class="px-4 py-3 text-center font-medium">Progress</th>
                  <th class="px-4 py-3 text-center font-medium">Success Rate</th>
                  <th class="px-4 py-3 text-left font-medium">Started</th>
                  <th class="px-4 py-3 text-left font-medium">Completed</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-200">
                <tr
                  v-for="job in categorizationStore.jobHistory"
                  :key="job.id"
                  class="hover:bg-neutral-50"
                >
                  <td class="px-4 py-3 text-sm">
                    {{ formatJobType(job.type) }}
                  </td>
                  <td class="px-4 py-3">
                    <span :class="getStatusColor(job.status)" class="px-2 py-1 rounded text-xs font-medium">
                      {{ job.status }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-sm text-center">
                    <div class="flex items-center justify-center">
                      <div class="w-16 bg-neutral-200 rounded-full h-2 mr-2">
                        <div
                          class="bg-blue-600 h-2 rounded-full"
                          :style="{ width: job.progress_percentage + '%' }"
                        ></div>
                      </div>
                      <span class="text-xs">{{ job.processed_transactions }}/{{ job.total_transactions }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-sm text-center">
                    <span v-if="job.processed_transactions > 0">
                      {{ Math.round((job.successful_transactions / job.processed_transactions) * 100) }}%
                    </span>
                    <span v-else>-</span>
                  </td>
                  <td class="px-4 py-3 text-sm">
                    {{ job.started_at ? formatDate(job.started_at) : '-' }}
                  </td>
                  <td class="px-4 py-3 text-sm">
                    {{ job.completed_at ? formatDate(job.completed_at) : '-' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
