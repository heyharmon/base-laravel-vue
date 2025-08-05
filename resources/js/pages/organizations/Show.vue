<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useOrganizationStore } from '@/stores/organizationStore';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';

const route = useRoute();
const router = useRouter();
const organizationStore = useOrganizationStore();
const organizationId = route.params.id;

onMounted(async () => {
  await loadOrganization();
});

const loadOrganization = async () => {
  try {
    await organizationStore.fetchOrganization(organizationId);
  } catch (error) {
    console.error('Error loading organization:', error);
  }
};

const deleteOrganization = async () => {
  if (!confirm('Are you sure you want to delete this organization?')) return;
  try {
    await organizationStore.deleteOrganization(organizationId);
    router.push({ name: 'organizations.index' });
  } catch (error) {
    console.error('Error deleting organization:', error);
  }
};
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8 px-4">
      <div v-if="organizationStore.isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
      </div>

      <div v-else-if="organizationStore.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        {{ organizationStore.error }}
      </div>

      <div v-else-if="organizationStore.currentOrganization" class="max-w-4xl mx-auto">
        <div class="flex justify-between items-start mb-8">
          <div>
            <router-link to="/organizations" class="text-blue-600 hover:text-blue-800 text-sm mb-2 inline-block">
              ← Back to Organizations
            </router-link>
            <h1 class="text-3xl font-bold text-neutral-900">{{ organizationStore.currentOrganization.name }}</h1>
            <p v-if="organizationStore.currentOrganization.category" class="text-lg text-neutral-600 mt-1">
              {{ organizationStore.currentOrganization.category }}
            </p>
          </div>
          <div class="flex space-x-2">
            <router-link :to="{ name: 'organizations.edit', params: { id: organizationId } }">
              <Button class="bg-blue-600 hover:bg-blue-700 text-white">Edit</Button>
            </router-link>
            <Button @click="deleteOrganization" class="bg-red-600 hover:bg-red-700 text-white">
              Delete
            </Button>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div class="lg:col-span-2 space-y-6">
            <div v-if="organizationStore.currentOrganization.banner" class="bg-white rounded-lg shadow-sm border border-neutral-200 overflow-hidden">
              <img :src="organizationStore.currentOrganization.banner" :alt="organizationStore.currentOrganization.name" class="w-full h-64 object-cover">
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
              <h2 class="text-xl font-semibold mb-4">Contact Information</h2>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-if="organizationStore.currentOrganization.phone">
                  <label class="block text-sm font-medium text-neutral-700 mb-1">Phone</label>
                  <a :href="`tel:${organizationStore.currentOrganization.phone}`" class="text-blue-600 hover:text-blue-800">
                    {{ organizationStore.currentOrganization.phone }}
                  </a>
                </div>
                
                <div v-if="organizationStore.currentOrganization.website">
                  <label class="block text-sm font-medium text-neutral-700 mb-1">Website</label>
                  <a :href="organizationStore.currentOrganization.formatted_website" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800">
                    {{ organizationStore.currentOrganization.website }}
                  </a>
                </div>
              </div>
            </div>

            <div v-if="organizationStore.currentOrganization.full_address" class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
              <h2 class="text-xl font-semibold mb-4">Address</h2>
              <p class="text-neutral-900">{{ organizationStore.currentOrganization.full_address }}</p>
              <div v-if="organizationStore.currentOrganization.map_url" class="mt-4">
                <a :href="organizationStore.currentOrganization.map_url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800">
                  View on Google Maps →
                </a>
              </div>
            </div>

            <div v-if="organizationStore.currentOrganization.notes" class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
              <h2 class="text-xl font-semibold mb-4">Notes</h2>
              <p class="text-neutral-900 whitespace-pre-wrap">{{ organizationStore.currentOrganization.notes }}</p>
            </div>
          </div>

          <div class="space-y-6">
            <div v-if="organizationStore.currentOrganization.score" class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
              <h3 class="text-lg font-semibold mb-4">Rating</h3>
              <div class="flex items-center">
                <span class="text-3xl text-yellow-400">★</span>
                <span class="text-2xl font-bold ml-2">{{ organizationStore.currentOrganization.score }}</span>
                <span v-if="organizationStore.currentOrganization.reviews" class="text-neutral-500 ml-2">
                  ({{ organizationStore.currentOrganization.reviews }} reviews)
                </span>
              </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
              <h3 class="text-lg font-semibold mb-4">Quick Info</h3>
              <div class="space-y-3">
                <div v-if="organizationStore.currentOrganization.category">
                  <span class="text-sm font-medium text-neutral-700">Category:</span>
                  <span class="ml-2 text-sm text-neutral-900">{{ organizationStore.currentOrganization.category }}</span>
                </div>
                
                <div v-if="organizationStore.currentOrganization.city">
                  <span class="text-sm font-medium text-neutral-700">City:</span>
                  <span class="ml-2 text-sm text-neutral-900">{{ organizationStore.currentOrganization.city }}</span>
                </div>
                
                <div v-if="organizationStore.currentOrganization.state">
                  <span class="text-sm font-medium text-neutral-700">State:</span>
                  <span class="ml-2 text-sm text-neutral-900">{{ organizationStore.currentOrganization.state }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
