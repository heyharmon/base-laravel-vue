<script setup>
import { onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import { useWebsiteStore } from '@/stores/websiteStore'

const store = useWebsiteStore()
const route = useRoute()
const websiteId = computed(() => route.params.id)

onMounted(() => {
  store.fetchWebsite(websiteId.value)
})
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8 px-4" v-if="store.currentWebsite">
      <h1 class="text-2xl font-bold mb-4">{{ store.currentWebsite.website.name }}</h1>
      <p class="mb-4 text-sm text-neutral-600">Domain: {{ store.currentWebsite.website.domain }}</p>
      <div class="mb-6">
        <h2 class="font-semibold mb-2">Embed Code</h2>
        <pre class="bg-neutral-100 p-4 rounded text-sm overflow-x-auto"><code>{{ store.currentWebsite.embed_code }}</code></pre>
      </div>
      <div>
        <h2 class="font-semibold mb-2">Stats (last 7 days)</h2>
        <p class="mb-2">Total LLM Page Views: {{ store.currentWebsite.stats.total }}</p>
        <table class="min-w-full bg-white border border-neutral-200">
          <thead>
            <tr class="bg-neutral-100 text-left text-sm">
              <th class="px-3 py-2 border-b">User Agent</th>
              <th class="px-3 py-2 border-b">Count</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in store.currentWebsite.stats.by_agent" :key="item.user_agent" class="text-sm">
              <td class="border-b px-3 py-2">{{ item.user_agent }}</td>
              <td class="border-b px-3 py-2">{{ item.count }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </DefaultLayout>
</template>
