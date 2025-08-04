<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useTransactionStore } from '@/stores/transactionStore';
import { useAccountStore } from '@/stores/accountStore';
import { useCategoryStore } from '@/stores/categoryStore';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';
import CategorySelector from '@/components/CategorySelector.vue';
import CsvUpload from '@/components/CsvUpload.vue';

const route = useRoute();
const transactionStore = useTransactionStore();
const accountStore = useAccountStore();
const categoryStore = useCategoryStore();

const showCsvUpload = ref(false);
const showBulkCategory = ref(false);
const bulkCategoryId = ref(null);

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(amount);
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString();
};

const isDeposit = (amount) => parseFloat(amount) > 0;

onMounted(async () => {
  await Promise.all([
    accountStore.fetchAccounts(),
    categoryStore.fetchCategories()
  ]);

  if (route.query.account_id) {
    transactionStore.setFilter('account_id', parseInt(route.query.account_id));
  }

  await transactionStore.fetchTransactions(1, true);
});

watch(() => transactionStore.filters, async () => {
  await transactionStore.fetchTransactions(1, true);
}, { deep: true });

const loadMore = async () => {
  const nextPage = transactionStore.transactions.meta.current_page + 1;
  if (nextPage <= transactionStore.transactions.meta.last_page) {
    await transactionStore.fetchTransactions(nextPage);
  }
};

const applyFilters = async () => {
  await transactionStore.fetchTransactions(1, true);
};

const clearFilters = async () => {
  transactionStore.clearFilters();
  await transactionStore.fetchTransactions(1, true);
};

const toggleSelection = (transaction) => {
  transactionStore.toggleTransactionSelection(transaction);
};

const selectAll = () => {
  if (transactionStore.selectedTransactions.length === transactionStore.transactions.data.length) {
    transactionStore.clearSelection();
  } else {
    transactionStore.selectAllTransactions();
  }
};

const bulkUpdateCategory = async () => {
  if (transactionStore.selectedTransactions.length === 0) return;

  try {
    await transactionStore.bulkUpdateCategory(
      transactionStore.selectedTransactionIds,
      bulkCategoryId.value
    );
    showBulkCategory.value = false;
    bulkCategoryId.value = null;
  } catch (error) {
    console.error('Error updating categories:', error);
  }
};

const canLoadMore = computed(() => {
  return transactionStore.transactions.meta.current_page < transactionStore.transactions.meta.last_page;
});

const allSelected = computed(() => {
  return transactionStore.transactions.data.length > 0 &&
         transactionStore.selectedTransactions.length === transactionStore.transactions.data.length;
});
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold">Transactions</h1>
        <div class="flex space-x-2">
          <Button
            v-if="transactionStore.hasSelectedTransactions"
            @click="showBulkCategory = true"
            variant="outline"
          >
            Set Category ({{ transactionStore.selectedTransactions.length }})
          </Button>
          <Button @click="showCsvUpload = true">Upload CSV</Button>
        </div>
      </div>

      <div class="bg-white border border-neutral-200 rounded-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Account</label>
            <select
              v-model="transactionStore.filters.account_id"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Accounts</option>
              <option
                v-for="account in accountStore.accounts"
                :key="account.id"
                :value="account.id"
              >
                {{ account.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Category</label>
            <select
              v-model="transactionStore.filters.category_id"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Categories</option>
              <option
                v-for="category in categoryStore.categories"
                :key="category.id"
                :value="category.id"
              >
                {{ category.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Type</label>
            <select
              v-model="transactionStore.filters.type"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Types</option>
              <option value="deposit">Deposits</option>
              <option value="spend">Spends</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">From Date</label>
            <input
              v-model="transactionStore.filters.date_from"
              type="date"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">To Date</label>
            <input
              v-model="transactionStore.filters.date_to"
              type="date"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Search</label>
            <input
              v-model="transactionStore.filters.search"
              type="text"
              placeholder="Search description..."
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>

        <div class="flex justify-end space-x-2 mt-4">
          <Button @click="clearFilters" variant="outline">Clear Filters</Button>
          <Button @click="applyFilters">Apply Filters</Button>
        </div>
      </div>

      <div v-if="transactionStore.isLoading && transactionStore.transactions.data.length === 0" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
      </div>

      <div v-else-if="transactionStore.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        {{ transactionStore.error }}
      </div>

      <div v-else>
        <div v-if="transactionStore.transactions.data.length === 0" class="text-center py-12">
          <p class="text-neutral-500 mb-4">No transactions found</p>
          <Button @click="showCsvUpload = true">Upload Your First CSV</Button>
        </div>

        <div v-else class="bg-white border border-neutral-200 rounded-lg overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-neutral-100 border-b border-neutral-200">
                <tr>
                  <th class="px-4 py-3 text-left">
                    <input
                      type="checkbox"
                      :checked="allSelected"
                      @change="selectAll"
                      class="rounded border-neutral-300"
                    />
                  </th>
                  <th class="px-4 py-3 text-left font-medium">Date</th>
                  <th class="px-4 py-3 text-left font-medium">Account</th>
                  <th class="px-4 py-3 text-left font-medium">Description</th>
                  <th class="px-4 py-3 text-right font-medium">Amount</th>
                  <th class="px-4 py-3 text-left font-medium">Category</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-200">
                <tr
                  v-for="transaction in transactionStore.transactions.data"
                  :key="transaction.id"
                  class="hover:bg-neutral-50"
                >
                  <td class="px-4 py-3">
                    <input
                      type="checkbox"
                      :checked="transactionStore.selectedTransactions.some(t => t.id === transaction.id)"
                      @change="toggleSelection(transaction)"
                      class="rounded border-neutral-300"
                    />
                  </td>
                  <td class="px-4 py-3 text-sm">
                    {{ formatDate(transaction.date) }}
                  </td>
                  <td class="px-4 py-3 text-sm">
                    {{ transaction.account?.name }}
                  </td>
                  <td class="px-4 py-3 text-sm">
                    <div class="max-w-xs truncate" :title="transaction.description">
                      {{ transaction.description }}
                    </div>
                  </td>
                  <td class="px-4 py-3 text-sm text-right">
                    <span :class="isDeposit(transaction.amount) ? 'text-green-600' : 'text-red-600'">
                      {{ formatCurrency(transaction.amount) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-sm">
                    <span v-if="transaction.category" class="bg-neutral-100 text-neutral-800 px-2 py-1 rounded text-xs">
                      {{ transaction.category.name }}
                    </span>
                    <span v-else class="text-neutral-400 text-xs">Uncategorized</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="canLoadMore" class="px-4 py-3 border-t border-neutral-200 text-center">
            <Button
              @click="loadMore"
              :disabled="transactionStore.isLoading"
              variant="outline"
            >
              {{ transactionStore.isLoading ? 'Loading...' : 'Load More' }}
            </Button>
          </div>

          <div class="px-4 py-3 border-t border-neutral-200 bg-neutral-50 text-sm text-neutral-600">
            Showing {{ transactionStore.transactions.data.length }} of {{ transactionStore.transactions.meta.total }} transactions
          </div>
        </div>
      </div>

      <CsvUpload
        :show="showCsvUpload"
        @close="showCsvUpload = false"
        @uploaded="showCsvUpload = false"
      />

      <div v-if="showBulkCategory" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
          <h2 class="text-xl font-bold mb-4">Set Category</h2>
          <p class="text-neutral-600 mb-4">
            Set category for {{ transactionStore.selectedTransactions.length }} selected transactions
          </p>

          <div class="mb-4">
            <CategorySelector v-model="bulkCategoryId" />
          </div>

          <div class="flex justify-end space-x-2">
            <Button @click="showBulkCategory = false" variant="outline">Cancel</Button>
            <Button @click="bulkUpdateCategory" :disabled="!bulkCategoryId">Update Categories</Button>
          </div>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
