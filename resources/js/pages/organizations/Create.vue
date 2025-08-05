<script setup>
import { useRouter } from 'vue-router';
import { useOrganizationStore } from '@/stores/organizationStore';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import OrganizationForm from '@/components/OrganizationForm.vue';

const router = useRouter();
const organizationStore = useOrganizationStore();

const handleSubmit = async (organizationData) => {
  try {
    await organizationStore.createOrganization(organizationData);
    router.push({ name: 'organizations.index' });
  } catch (error) {
    console.error('Error creating organization:', error);
  }
};
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8 px-4 max-w-4xl">
      <div class="mb-8">
        <router-link to="/organizations" class="text-blue-600 hover:text-blue-800 text-sm mb-2 inline-block">
          ← Back to Organizations
        </router-link>
        <h1 class="text-2xl font-bold">Create Organization</h1>
      </div>
      
      <OrganizationForm 
        @submit="handleSubmit" 
        :is-loading="organizationStore.isLoading"
      />
    </div>
  </DefaultLayout>
</template>
