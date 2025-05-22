<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useOrganizationStore } from '@/stores/organizationStore';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';

const route = useRoute();
const router = useRouter();
const organizationStore = useOrganizationStore();
const organization = ref({
  name: '',
  website: '',
  founded: '',
  employee_count: '',
  is_competitor: false
});
const isSubmitting = ref(false);
const isLoading = ref(true);

onMounted(async () => {
  try {
    const data = await organizationStore.fetchOrganization(route.params.id);
    organization.value = { ...data };
  } catch (error) {
    console.error('Error fetching organization:', error);
  } finally {
    isLoading.value = false;
  }
});

const updateOrganization = async () => {
  isSubmitting.value = true;
  try {
    await organizationStore.updateOrganization(route.params.id, organization.value);
    router.push({ name: 'organizations.index' });
  } catch (error) {
    console.error('Error updating organization:', error);
  } finally {
    isSubmitting.value = false;
  }
};

const cancelEdit = () => {
  router.push({ name: 'organizations.index' });
};
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold">Edit Organization</h1>
        <Button @click="cancelEdit" class="bg-neutral-200 hover:bg-neutral-300 text-neutral-800">
          Cancel
        </Button>
      </div>

      <!-- Loading state -->
      <div v-if="isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
      </div>

      <!-- Error state -->
      <div v-else-if="organizationStore.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        {{ organizationStore.error }}
      </div>

      <div v-else class="bg-neutral-100 p-6 rounded-lg shadow max-w-2xl mx-auto">
        <form @submit.prevent="updateOrganization" class="space-y-6">
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Organization Name</label>
            <input 
              v-model="organization.name"
              type="text"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter organization name"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Website</label>
            <input 
              v-model="organization.website"
              type="text"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter website URL"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Founded</label>
            <input 
              v-model="organization.founded"
              type="text"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter founding year"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Employee Count</label>
            <select
              v-model="organization.employee_count"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Select employee count</option>
              <option value="1-10">1-10</option>
              <option value="11-50">11-50</option>
              <option value="51-200">51-200</option>
              <option value="201-500">201-500</option>
              <option value="501-1000">501-1000</option>
              <option value="1000+">1000+</option>
            </select>
          </div>
          
          <div v-if="organization.is_competitor" class="flex items-center">
            <input 
              id="is-competitor"
              v-model="organization.is_competitor"
              type="checkbox"
              class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-neutral-300 rounded"
              :disabled="!organization.is_competitor"
            />
            <label for="is-competitor" class="ml-2 block text-sm text-neutral-700">
              This is a competitor organization
            </label>
          </div>
          
          <div class="pt-4">
            <Button 
              type="submit"
              :disabled="isSubmitting"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white"
            >
              {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
            </Button>
          </div>
        </form>
      </div>
    </div>
  </DefaultLayout>
</template>
