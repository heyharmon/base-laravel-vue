<script setup>
import { ref } from 'vue';
import { useCategorizationStore } from '@/stores/categorizationStore';
import { useTransactionStore } from '@/stores/transactionStore';
import Button from '@/components/ui/Button.vue';

const categorizationStore = useCategorizationStore();
const transactionStore = useTransactionStore();

const isProcessing = ref(false);

const categorizeSelected = async () => {
  if (transactionStore.selectedTransactions.length === 0) return;

  isProcessing.value = true;
  try {
    await categorizationStore.categorizeBatch(transactionStore.selectedTransactionIds);
    transactionStore.clearSelection();
  } catch (error) {
    console.error('Error categorizing selected transactions:', error);
  } finally {
    isProcessing.value = false;
  }
};

const categorizeAll = async () => {
  isProcessing.value = true;
  try {
    await categorizationStore.categorizeAll();
  } catch (error) {
    console.error('Error categorizing all transactions:', error);
  } finally {
    isProcessing.value = false;
  }
};

const categorizeTransaction = async (transactionId) => {
  try {
    await categorizationStore.categorizeTransaction(transactionId);
  } catch (error) {
    console.error('Error categorizing transaction:', error);
  }
};

defineExpose({
  categorizeTransaction
});
</script>

<template>
  <div class="flex space-x-2">
    <Button
      v-if="transactionStore.hasSelectedTransactions"
      @click="categorizeSelected"
      :disabled="isProcessing || categorizationStore.isLoading"
      variant="outline"
      class="bg-purple-50 border-purple-200 text-purple-700 hover:bg-purple-100 flex"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
      </svg>
      AI Categorize Selected ({{ transactionStore.selectedTransactions.length }})
    </Button>

    <Button
      @click="categorizeAll"
      :disabled="isProcessing || categorizationStore.isLoading"
      variant="outline"
      class="bg-blue-50 border-blue-200 text-blue-700 hover:bg-blue-100 flex"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
      </svg>
      AI Categorize All
    </Button>
  </div>
</template>
