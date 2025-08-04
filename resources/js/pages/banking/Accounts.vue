<script setup>
import { ref, onMounted } from 'vue';
import { useAccountStore } from '@/stores/accountStore';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';

const accountStore = useAccountStore();
const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingAccount = ref(null);
const accountForm = ref({
  name: '',
  provider: ''
});
const isSubmitting = ref(false);

onMounted(async () => {
  await accountStore.fetchAccounts();
});

const openCreateModal = () => {
  accountForm.value = { name: '', provider: '' };
  showCreateModal.value = true;
};

const openEditModal = (account) => {
  editingAccount.value = account;
  accountForm.value = { ...account };
  showEditModal.value = true;
};

const closeModals = () => {
  showCreateModal.value = false;
  showEditModal.value = false;
  editingAccount.value = null;
  accountForm.value = { name: '', provider: '' };
};

const createAccount = async () => {
  if (!accountForm.value.name || !accountForm.value.provider) return;

  isSubmitting.value = true;
  try {
    await accountStore.createAccount(accountForm.value);
    closeModals();
  } catch (error) {
    console.error('Error creating account:', error);
  } finally {
    isSubmitting.value = false;
  }
};

const updateAccount = async () => {
  if (!accountForm.value.name || !accountForm.value.provider) return;

  isSubmitting.value = true;
  try {
    await accountStore.updateAccount(editingAccount.value.id, accountForm.value);
    closeModals();
  } catch (error) {
    console.error('Error updating account:', error);
  } finally {
    isSubmitting.value = false;
  }
};

const deleteAccount = async (account) => {
  if (!confirm(`Are you sure you want to delete "${account.name}"? This will also delete all associated transactions.`)) {
    return;
  }

  try {
    await accountStore.deleteAccount(account.id);
  } catch (error) {
    console.error('Error deleting account:', error);
  }
};
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold">Accounts</h1>
        <Button @click="openCreateModal">Add Account</Button>
      </div>

      <div v-if="accountStore.isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
      </div>

      <div v-else-if="accountStore.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        {{ accountStore.error }}
      </div>

      <div v-else>
        <div v-if="accountStore.accounts.length === 0" class="text-center py-12">
          <p class="text-neutral-500 mb-4">No accounts yet</p>
          <Button @click="openCreateModal">Add Your First Account</Button>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="account in accountStore.accounts"
            :key="account.id"
            class="bg-white border border-neutral-200 rounded-lg p-6 shadow-sm"
          >
            <div class="flex justify-between items-start mb-4">
              <div>
                <h3 class="text-lg font-semibold">{{ account.name }}</h3>
                <p class="text-neutral-600">{{ account.provider }}</p>
              </div>
              <div class="flex space-x-2">
                <button
                  @click="openEditModal(account)"
                  class="text-blue-600 hover:text-blue-800 text-sm"
                >
                  Edit
                </button>
                <button
                  @click="deleteAccount(account)"
                  class="text-red-600 hover:text-red-800 text-sm"
                >
                  Delete
                </button>
              </div>
            </div>

            <div class="text-sm text-neutral-500">
              {{ account.transactions_count || 0 }} transactions
            </div>

            <div class="mt-4">
              <router-link
                :to="{ name: 'banking.transactions', query: { account_id: account.id } }"
                class="text-blue-600 hover:text-blue-800 text-sm font-medium"
              >
                View Transactions
              </router-link>
            </div>
          </div>
        </div>
      </div>

      <div v-if="showCreateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
          <h2 class="text-xl font-bold mb-4">Add Account</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Account Name</label>
              <input
                v-model="accountForm.name"
                type="text"
                placeholder="e.g., Main Checking"
                class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Provider</label>
              <input
                v-model="accountForm.provider"
                type="text"
                placeholder="e.g., Wells Fargo"
                class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>
          <div class="flex justify-end space-x-2 mt-6">
            <Button @click="closeModals" variant="outline">Cancel</Button>
            <Button @click="createAccount" :disabled="isSubmitting || !accountForm.name || !accountForm.provider">
              {{ isSubmitting ? 'Creating...' : 'Create Account' }}
            </Button>
          </div>
        </div>
      </div>

      <div v-if="showEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
          <h2 class="text-xl font-bold mb-4">Edit Account</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Account Name</label>
              <input
                v-model="accountForm.name"
                type="text"
                class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Provider</label>
              <input
                v-model="accountForm.provider"
                type="text"
                class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>
          <div class="flex justify-end space-x-2 mt-6">
            <Button @click="closeModals" variant="outline">Cancel</Button>
            <Button @click="updateAccount" :disabled="isSubmitting || !accountForm.name || !accountForm.provider">
              {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
            </Button>
          </div>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
