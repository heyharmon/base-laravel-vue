<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import FullWidthLayout from '@/layouts/FullWidthLayout.vue'
import UsageProgress from '@/components/UsageProgress.vue'

const route = useRoute()
const team = ref(null)
const usage = ref(null)
const form = ref({ responses_limit: null, articles_limit: null })
const start = ref('')
const end = ref('')

async function fetchUsage() {
  const params = {}
  if (start.value) params.start_date = start.value
  if (end.value) params.end_date = end.value
  const res = await api.get(`/super-admin/teams/${route.params.teamId}`, { params })
  team.value = res.team
  usage.value = res.usage
  form.value.responses_limit = team.value.responses_limit
  form.value.articles_limit = team.value.articles_limit
}

onMounted(fetchUsage)

async function save() {
  await api.put(`/super-admin/teams/${route.params.teamId}`, form.value)
  await fetchUsage()
}
</script>

<template>
  <FullWidthLayout>
    <h1 class="text-2xl font-bold mb-4">{{ team?.name }}</h1>
    <div class="flex gap-2 mb-4">
      <input type="date" v-model="start" class="border p-1 rounded" />
      <input type="date" v-model="end" class="border p-1 rounded" />
      <button @click="fetchUsage" class="px-3 py-1 border rounded">Change Period</button>
    </div>
    <UsageProgress v-if="usage" :used="usage.responses_used" :limit="usage.responses_limit" label="Responses" />
    <UsageProgress v-if="usage" :used="usage.articles_used" :limit="usage.articles_limit" label="Articles" />

    <div class="mt-6 max-w-sm">
      <h2 class="font-medium mb-2">Update Limits</h2>
      <div class="space-y-2">
        <label class="block text-sm">Responses Limit
          <input type="number" v-model.number="form.responses_limit" class="border p-1 rounded w-full" />
        </label>
        <label class="block text-sm">Articles Limit
          <input type="number" v-model.number="form.articles_limit" class="border p-1 rounded w-full" />
        </label>
        <button @click="save" class="px-3 py-1 border rounded">Save</button>
      </div>
    </div>
  </FullWidthLayout>
</template>
