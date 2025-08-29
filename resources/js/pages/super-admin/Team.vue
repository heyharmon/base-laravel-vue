<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import FullWidthLayout from '@/layouts/FullWidthLayout.vue'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import { useUsageStore } from '@/stores/usageStore'

const route = useRoute()
const usageStore = useUsageStore()
const teamId = route.params.teamId

const data = ref(null)
const isLoading = ref(false)
const selectedMonth = ref(new Date().toISOString().slice(0, 7))
const editLimit = ref(null)
const editLimitDisplay = ref('')

const formatCurrency = (value) => {
    if (value === null || value === undefined || value === '') return ''
    const num = typeof value === 'number' ? value : parseFloat(String(value).replace(/[^0-9.]/g, ''))
    if (isNaN(num)) return ''
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 2 }).format(num)
}

const onLimitFocus = () => {
    editLimitDisplay.value = editLimit.value !== null && editLimit.value !== undefined ? Number(editLimit.value).toFixed(2) : ''
}

const onLimitInput = (e) => {
    const raw = e.target.value
    // Allow only digits and a single dot
    const cleaned = raw.replace(/[^0-9.]/g, '')
    const parts = cleaned.split('.')
    const normalized = parts.length > 2 ? parts[0] + '.' + parts.slice(1).join('') : cleaned
    editLimitDisplay.value = normalized
    const parsed = parseFloat(normalized)
    editLimit.value = isNaN(parsed) ? null : parsed
}

const onLimitBlur = () => {
    editLimitDisplay.value = formatCurrency(editLimit.value)
}

const fetchUsage = async () => {
    isLoading.value = true
    try {
        const response = await usageStore.fetchAdminTeamUsage(teamId, selectedMonth.value)
        data.value = response
        // Use price-based limit for editing/display
        editLimit.value = data.value.limit_price
        editLimitDisplay.value = formatCurrency(editLimit.value)
    } catch (error) {
        console.error('Error fetching usage:', error)
    } finally {
        isLoading.value = false
    }
}

const saveLimit = async () => {
    try {
        await usageStore.updateLimit(teamId, editLimit.value)
        data.value.limit_price = editLimit.value
        editLimitDisplay.value = formatCurrency(editLimit.value)
    } catch (error) {
        console.error('Error updating limit:', error)
    }
}

onMounted(fetchUsage)
watch(selectedMonth, fetchUsage)
</script>

<template>
    <FullWidthLayout>
        <div class="p-6" v-if="data">
            <h1 class="text-2xl font-bold mb-4">{{ data.team.name }} Usage</h1>
            <div class="mb-4 flex items-center gap-2">
                <label class="text-sm">Month:</label>
                <input type="month" v-model="selectedMonth" class="border rounded px-2 py-1" />
            </div>
            <div v-if="isLoading" class="py-8 text-center">Loading...</div>
            <div v-else>
                <div class="mb-4">
                    <p>Total Tokens: {{ data.usage.total.tokens }}</p>
                    <p>Total Cost: ${{ data.usage.total.cost.toFixed(2) }}</p>
                    <p>Total Price: ${{ (data.usage.total.price || 0).toFixed(2) }}</p>
                    <p v-if="data.limit_price !== null">
                        Price Limit: ${{ data.limit_price.toFixed(2) }}
                        (Remaining: ${{ (data.limit_price - (data.usage.total.price || 0)).toFixed(2) }})
                    </p>
                    <p v-else>
                        Price Limit: Unlimited
                    </p>
                    <p>Days until reset: {{ data.period.days_until_reset }}</p>
                </div>
                <div class="mb-4">
                    <h2 class="font-semibold mb-2">Responses</h2>
                    <p>Count: {{ data.usage.responses.count }}</p>
                    <p>Tokens: {{ data.usage.responses.tokens }}</p>
                    <p>Cost: ${{ data.usage.responses.cost.toFixed(2) }}</p>
                    <p>Price: ${{ (data.usage.responses.price || 0).toFixed(2) }}</p>
                </div>
                <div class="mb-4">
                    <h2 class="font-semibold mb-2">Chats</h2>
                    <p>Count: {{ data.usage.chats.count }}</p>
                    <p>Tokens: {{ data.usage.chats.tokens }}</p>
                    <p>Cost: ${{ data.usage.chats.cost.toFixed(2) }}</p>
                    <p>Price: ${{ (data.usage.chats.price || 0).toFixed(2) }}</p>
                </div>
                <div class="mt-6">
                    <label class="block mb-1 text-sm">Monthly Price Limit (USD)</label>
                    <Input
                        type="text"
                        :value="editLimitDisplay"
                        @focus="onLimitFocus"
                        @input="onLimitInput"
                        @blur="onLimitBlur"
                        placeholder="$0.00"
                        class="w-48"
                    />
                    <Button class="ml-2" @click="saveLimit">Save</Button>
                </div>
            </div>
        </div>
    </FullWidthLayout>
</template>
