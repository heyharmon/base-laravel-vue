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
const period = ref(0)
const billingInterval = ref('monthly')

async function fetchUsage() {
  const res = await api.get(`/super-admin/teams/${route.params.teamId}`, { params: { period: period.value } })
  team.value = res.team
  usage.value = res.usage
  form.value.responses_limit = team.value.responses_limit
  form.value.articles_limit = team.value.articles_limit
  billingInterval.value = res.usage.billing_interval
}

onMounted(fetchUsage)

function previousPeriod() {
  period.value++
  fetchUsage()
}

function nextPeriod() {
  if (period.value > 0) {
    period.value--
    fetchUsage()
  }
}

async function save() {
  await api.put(`/super-admin/teams/${route.params.teamId}`, {
    responses_limit: form.value.responses_limit,
    articles_limit: form.value.articles_limit,
    billing_interval: billingInterval.value,
  })
  await fetchUsage()
}
</script>

<template>
  <FullWidthLayout>
    <h1 class="text-2xl font-bold mb-4">{{ team?.name }}</h1>
    <div class="flex items-center gap-2 mb-4">
      <button @click="previousPeriod" class="px-3 py-1 border rounded">Previous</button>
      <div>{{ usage?.period_start }} - {{ usage?.period_end }}</div>
      <button @click="nextPeriod" :disabled="period === 0" class="px-3 py-1 border rounded">Next</button>
    </div>
    <UsageProgress
      v-if="usage"
      :used="usage.responses_used"
      :limit="usage.responses_limit"
      :label="`Responses (${billingInterval})`"
    />
    <UsageProgress
      v-if="usage"
      :used="usage.articles_used"
      :limit="usage.articles_limit"
      :label="`Articles (${billingInterval})`"
    />

    <div class="mt-6 max-w-sm">
      <h2 class="font-medium mb-2">Update Limits</h2>
      <div class="space-y-2">
        <label class="block text-sm">Billing Interval
          <select v-model="billingInterval" class="border p-1 rounded w-full">
            <option value="monthly">Monthly</option>
            <option value="yearly">Yearly</option>
          </select>
        </label>
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
