<script setup>
import { ref } from 'vue';
import { useTransactionStore } from '@/stores/transactionStore';
import { useAccountStore } from '@/stores/accountStore';

const props = defineProps({
  show: Boolean
});

const emit = defineEmits(['close', 'uploaded']);

const transactionStore = useTransactionStore();
const accountStore = useAccountStore();

const selectedFile = ref(null);
const selectedAccountId = ref(null);
const isUploading = ref(false);
const uploadResult = ref(null);

const selectFile = (event) => {
  selectedFile.value = event.target.files[0];
};

const upload = async () => {
  if (!selectedFile.value || !selectedAccountId.value) return;

  isUploading.value = true;
  uploadResult.value = null;

  try {
    const result = await transactionStore.uploadCsv(selectedFile.value, selectedAccountId.value);
    uploadResult.value = result;
    emit('uploaded', result);
  } catch (error) {
    console.error('Upload failed:', error);
  } finally {
    isUploading.value = false;
  }
};

const close = () => {
  selectedFile.value = null;
  selectedAccountId.value = null;
  uploadResult.value = null;
  emit('close');
};
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
      <h2 class="text-xl font-bold mb-4">Upload CSV</h2>

      <div v-if="!uploadResult" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-neutral-700 mb-1">Account</label>
          <select
            v-model="selectedAccountId"
            class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select an account</option>
            <option
              v-for="account in accountStore.accounts"
              :key="account.id"
              :value="account.id"
            >
              {{ account.name }} - {{ account.provider }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-neutral-700 mb-1">CSV File</label>
          <input
            type="file"
            accept=".csv,.txt"
            @change="selectFile"
            class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <p class="text-sm text-neutral-500 mt-1">
            Expected columns: date (MM/DD/YYYY), amount, description
          </p>
        </div>

        <div class="flex justify-end space-x-2">
          <button
            @click="close"
            class="px-4 py-2 text-neutral-600 hover:text-neutral-800"
          >
            Cancel
          </button>
          <button
            @click="upload"
            :disabled="!selectedFile || !selectedAccountId || isUploading"
            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
          >
            {{ isUploading ? 'Uploading...' : 'Upload' }}
          </button>
        </div>
      </div>

      <div v-else class="space-y-4">
        <div class="bg-green-50 p-4 rounded-md">
          <h3 class="font-medium text-green-800">Upload Complete</h3>
          <p class="text-green-700">{{ uploadResult.imported }} transactions imported</p>
          <div v-if="uploadResult.errors.length > 0" class="mt-2">
            <p class="text-red-700 font-medium">Errors:</p>
            <ul class="text-red-600 text-sm">
              <li v-for="error in uploadResult.errors" :key="error">{{ error }}</li>
            </ul>
          </div>
        </div>

        <div class="flex justify-end">
          <button
            @click="close"
            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
          >
            Done
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
