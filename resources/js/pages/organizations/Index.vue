<script setup>
import { ref, onMounted } from 'vue';
import { useOrganizationStore } from '@/stores/organizationStore';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';

const organizationStore = useOrganizationStore();
const showCreateModal = ref(false);
const newOrganization = ref({
  name: '',
  website: '',
  is_competitor: true
});
const isSubmitting = ref(false);

onMounted(async () => {
  await organizationStore.fetchOrganizations();
});

const createOrganization = async () => {
  if (!newOrganization.value.name) return;

  isSubmitting.value = true;
  try {
    await organizationStore.createOrganization(newOrganization.value);
    resetNewOrganization();
    showCreateModal.value = false;
  } catch (error) {
    console.error('Error creating organization:', error);
  } finally {
    isSubmitting.value = false;
  }
};

const resetNewOrganization = () => {
  newOrganization.value = {
    name: '',
    website: '',
    is_competitor: true
  };
};

const deleteOrganization = async (organizationId) => {
  if (!confirm('Are you sure you want to delete this organization? This action cannot be undone.')) {
    return;
  }

  try {
    await organizationStore.deleteOrganization(organizationId);
  } catch (error) {
    console.error('Error deleting organization:', error);
  }
};
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8">
      <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold">Keywords</h1>
        <Button @click="showCreateModal = true">Add competitor</Button>
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
        <div class="mb-8">
          <h2 class="text-xl font-semibold mb-4">Your organization</h2>
          <div v-if="organizationStore.ownedOrganizations.length === 0" class="text-neutral-500">
            You don't have an organization yet.
          </div>
          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
              v-for="org in organizationStore.ownedOrganizations"
              :key="org.id"
              class="bg-neutral-100 p-4 rounded-lg shadow"
            >
              <div class="flex justify-between items-start">
                <h3 class="text-lg font-medium">{{ org.name || 'Unnamed Organization' }}</h3>
                <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Your organization</span>
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
            </div>
          </div>
        </div>

        <!-- Competitor Organizations -->
        <div>
          <h2 class="text-xl font-semibold mb-4">Competitors</h2>
          <div v-if="organizationStore.competitorOrganizations.length === 0" class="text-neutral-500">
            You haven't added any competitors yet.
          </div>
          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
              v-for="org in organizationStore.competitorOrganizations"
              :key="org.id"
              class="bg-neutral-100 p-4 rounded-lg shadow"
            >
              <div class="flex justify-between items-start">
                <h3 class="text-lg font-medium">{{ org.name || 'Unnamed Competitor' }}</h3>
                <span class="bg-neutral-200 text-neutral-800 text-xs px-2 py-1 rounded">Competitor</span>
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
                  @click="deleteOrganization(org.id)"
                  class="text-red-600 hover:text-red-800 text-sm font-medium"
                >
                  Delete
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <Modal
        :isOpen="showCreateModal"
        title="Add competitor"
        @close="showCreateModal = false"
      >
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Competitor name</label>
            <input
              v-model="newOrganization.name"
              type="text"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter competitor name"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Website</label>
            <input
              v-model="newOrganization.website"
              type="text"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter website URL"
            />
          </div>
        </div>

        <template #footer>
          <Button
            @click="showCreateModal = false"
            class="bg-neutral-200 hover:bg-neutral-100 text-neutral-800 ml-2"
          >
            Cancel
          </Button>
          <Button
            @click="createOrganization"
            :disabled="isSubmitting || !newOrganization.name"
            class="bg-neutral-800 hover:bg-neutral-700 text-white"
          >
            {{ isSubmitting ? 'Creating...' : 'Add competitor' }}
          </Button>
        </template>
      </Modal>
    </div>
  </DefaultLayout>
</template>
