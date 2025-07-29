<script setup>
import { ref, onMounted } from 'vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import { useWebsiteStore } from '@/stores/websiteStore'

const store = useWebsiteStore()
const showModal = ref(false)
const name = ref('')
const domain = ref('')
const submitting = ref(false)

onMounted(() => {
  store.fetchWebsites()
})

const createWebsite = async () => {
  if (!name.value || !domain.value) return
  submitting.value = true
  try {
    await store.createWebsite({ name: name.value, domain: domain.value })
    name.value = ''
    domain.value = ''
    showModal.value = false
  } catch (e) {
    console.error(e)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold">Websites</h1>
        <Button @click="showModal = true">Add Website</Button>
      </div>

      <div v-if="store.isLoading" class="py-8 text-center">Loading...</div>
      <div v-else-if="store.error" class="py-8 text-red-600">{{ store.error }}</div>
      <div v-else>
        <div v-if="store.websites.length === 0" class="text-neutral-500">No websites yet.</div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="site in store.websites" :key="site.id" class="p-4 bg-neutral-100 rounded shadow">
            <div class="font-medium">{{ site.name }}</div>
            <div class="text-sm text-neutral-600">{{ site.domain }}</div>
            <router-link :to="{ name: 'websites.show', params: { id: site.id } }" class="text-blue-600 text-sm mt-2 inline-block">View</router-link>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Add Website</h2>
        <div class="mb-4">
          <label class="block text-sm mb-1">Name</label>
          <input v-model="name" type="text" class="w-full border px-3 py-2 rounded" />
        </div>
        <div class="mb-4">
          <label class="block text-sm mb-1">Domain</label>
          <input v-model="domain" type="text" class="w-full border px-3 py-2 rounded" placeholder="example.com" />
        </div>
        <div class="flex justify-end space-x-2">
          <Button variant="secondary" @click="showModal = false">Cancel</Button>
          <Button @click="createWebsite" :disabled="submitting">{{ submitting ? 'Saving...' : 'Save' }}</Button>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
