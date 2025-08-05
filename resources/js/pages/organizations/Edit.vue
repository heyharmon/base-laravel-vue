<script setup>
import { onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useOrganizationStore } from '@/stores/organizationStore';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import OrganizationForm from '@/components/OrganizationForm.vue';

const route = useRoute();
const router = useRouter();
const organizationStore = useOrganizationStore();
const organizationId = route.params.id;

onMounted(async () => {
  await organizationStore.fetchOrganization(organizationId);
});

const handleSubmit = async (organizationData) => {
  try {
    await organizationStore.updateOrganization(organizationId, organizationData);
    router.push({ name: 'organizations.show', params: { id: organizationId } });
  } catch (error) {
    console.error('Error updating organization:', error);
  }
};
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8 px-4 max-w-4xl">
      <div class="mb-8">
        <router-link :to="{ name: 'organizations.show', params: { id: organizationId } }" class="text-blue-600 hover:text-blue-800 text-sm mb-2 inline-block">
          ← Back to Organization
        </router-link>
        <h1 class="text-2xl font-bold">Edit Organization</h1>
      </div>
      
      <div v-if="organizationStore.isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
      </div>
      
      <OrganizationForm 
        v-else
        :organization="organizationStore.currentOrganization"
        @submit="handleSubmit" 
        :is-loading="organizationStore.isLoading"
      />
    </div>
  </DefaultLayout>
</template>
