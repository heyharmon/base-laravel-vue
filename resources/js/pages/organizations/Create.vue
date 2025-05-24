<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useOrganizationStore } from '@/stores/organizationStore';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';

const router = useRouter();
const organizationStore = useOrganizationStore();
const isSubmitting = ref(false);
const organization = ref({
  name: '',
  website: '',
  is_competitor: true
});

const createOrganization = async () => {
  if (!organization.value.name) return;

  isSubmitting.value = true;
  try {
    await organizationStore.createOrganization(organization.value);
    router.push({ name: 'organizations.index' });
  } catch (error) {
    console.error('Error creating organization:', error);
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8">
      <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold">Add Competitor</h1>
      </div>

      <div class="bg-neutral-100 p-6 rounded-lg shadow max-w-2xl mx-auto">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Competitor name</label>
            <input
              v-model="organization.name"
              type="text"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter competitor name"
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
          <!-- Competitor checkbox removed as this page always creates competitors -->
        </div>

        <div class="mt-6 flex justify-end space-x-2">
          <Button
            @click="router.push({ name: 'organizations.index' })"
            variant="neutral"
          >
            Cancel
          </Button>
          <Button
            @click="createOrganization"
            :disabled="isSubmitting || !organization.name"
            variant="dark"
          >
            {{ isSubmitting ? 'Creating...' : 'Add competitor' }}
          </Button>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
