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
		<div class="mx-auto max-w-7xl p-6" v-if="data">
			<!-- Header -->
			<header class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
				<div>
					<h1 class="text-2xl font-semibold tracking-tight">{{ data.team.name }} usage</h1>
					<p class="mt-1 text-sm text-neutral-600">Track tokens and spending for the selected billing month.</p>
				</div>

				<!-- Controls -->
				<form class="flex w-full flex-col items-stretch gap-3 sm:w-auto sm:flex-row sm:items-center">
					<label class="flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm shadow-sm">
						<span class="whitespace-nowrap text-neutral-600">Month</span>
						<input type="month" v-model="selectedMonth" class="w-[180px] rounded-md border-0 p-0 text-sm focus:outline-none focus:ring-0" />
					</label>

					<div class="flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm shadow-sm">
						<span class="whitespace-nowrap text-neutral-600">Monthly Price Limit</span>
						<div class="flex items-center gap-2">
							<span class="text-neutral-500">$</span>
							<input
								type="text"
								inputmode="decimal"
								:value="editLimitDisplay"
								@focus="onLimitFocus"
								@input="onLimitInput"
								@blur="onLimitBlur"
								class="w-24 rounded-md border-0 p-0 text-right text-sm focus:outline-none focus:ring-0"
							/>
							<button
								type="button"
								@click="saveLimit"
								class="rounded-lg bg-neutral-900 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-neutral-800"
							>
								Save
							</button>
						</div>
					</div>
				</form>
			</header>

			<div v-if="isLoading" class="py-8 text-center">Loading...</div>
			<div v-else>
				<!-- Overview -->
				<section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
					<article class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
						<p class="text-xs font-medium text-neutral-500">Total Tokens</p>
						<p class="mt-2 text-2xl font-semibold tabular-nums">{{ data.usage.total.tokens.toLocaleString() }}</p>
					</article>

					<article class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
						<p class="text-xs font-medium text-neutral-500">Total Cost</p>
						<p class="mt-2 text-2xl font-semibold tabular-nums">{{ formatCurrency(data.usage.total.cost) }}</p>
					</article>

					<article class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
						<p class="text-xs font-medium text-neutral-500">Total Price (Billable)</p>
						<p class="mt-2 text-2xl font-semibold tabular-nums">{{ formatCurrency(data.usage.total.price || 0) }}</p>
					</article>

					<article class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
						<div class="flex items-center justify-between">
							<p class="text-xs font-medium text-neutral-500">Days Until Reset</p>
							<span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">Monthly</span>
						</div>
						<p class="mt-2 text-2xl font-semibold tabular-nums">{{ data.period.days_until_reset }}</p>
					</article>
				</section>

				<!-- Spend vs Limit -->
				<section v-if="data.limit_price !== null" class="mt-6 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
					<div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
						<div>
							<h2 class="text-sm font-semibold tracking-tight">Spend Limit</h2>
							<p class="mt-1 text-sm text-neutral-600 space-x-1">
								Price Limit: <span class="font-medium text-neutral-900">{{ formatCurrency(data.limit_price) }}</span>
								<span class="text-neutral-400">•</span>
								Remaining:
								<span class="font-medium text-neutral-900">{{ formatCurrency(data.limit_price - (data.usage.total.price || 0)) }}</span>
							</p>
						</div>
						<p class="text-sm text-neutral-600">
							Used: <span class="font-medium text-neutral-900">{{ formatCurrency(data.usage.total.price || 0) }}</span> of
							{{ formatCurrency(data.limit_price) }}
							<span class="ml-1 rounded-full bg-neutral-100 px-1.5 py-0.5 text-xs tabular-nums"
								>{{ (((data.usage.total.price || 0) / data.limit_price) * 100).toFixed(1) }}%</span
							>
						</p>
					</div>

					<!-- Progress -->
					<div class="mt-3 h-3 w-full overflow-hidden rounded-full bg-neutral-100">
						<div
							class="h-full rounded-full bg-neutral-900 transition-all"
							:style="`width: ${Math.min(((data.usage.total.price || 0) / data.limit_price) * 100, 100)}%;`"
							:aria-valuemin="0"
							:aria-valuemax="100"
							:aria-valuenow="(((data.usage.total.price || 0) / data.limit_price) * 100).toFixed(1)"
							role="progressbar"
						></div>
					</div>
				</section>

				<!-- Breakdown -->
				<section class="mt-6 grid gap-6 lg:grid-cols-2">
					<!-- Responses -->
					<article class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
						<header class="mb-4 flex items-center justify-between">
							<h3 class="text-sm font-semibold tracking-tight">Responses</h3>
							<span class="rounded-full bg-neutral-100 px-2 py-1 text-xs font-medium text-neutral-700">
								Count: <span class="tabular-nums">{{ data.usage.responses.count.toLocaleString() }}</span>
							</span>
						</header>

						<dl class="grid grid-cols-3 gap-4">
							<div class="rounded-xl border border-neutral-100 bg-neutral-50 p-4">
								<dt class="text-xs font-medium text-neutral-500">Tokens</dt>
								<dd class="mt-1 text-lg font-semibold tabular-nums">{{ data.usage.responses.tokens.toLocaleString() }}</dd>
							</div>
							<div class="rounded-xl border border-neutral-100 bg-neutral-50 p-4">
								<dt class="text-xs font-medium text-neutral-500">Cost</dt>
								<dd class="mt-1 text-lg font-semibold tabular-nums">{{ formatCurrency(data.usage.responses.cost) }}</dd>
							</div>
							<div class="rounded-xl border border-neutral-100 bg-neutral-50 p-4">
								<dt class="text-xs font-medium text-neutral-500">Price</dt>
								<dd class="mt-1 text-lg font-semibold tabular-nums">{{ formatCurrency(data.usage.responses.price || 0) }}</dd>
							</div>
						</dl>
					</article>

					<!-- Chats -->
					<article class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
						<header class="mb-4 flex items-center justify-between">
							<h3 class="text-sm font-semibold tracking-tight">Chats</h3>
							<span class="rounded-full bg-neutral-100 px-2 py-1 text-xs font-medium text-neutral-700">
								Count: <span class="tabular-nums">{{ data.usage.chats.count.toLocaleString() }}</span>
							</span>
						</header>

						<dl class="grid grid-cols-3 gap-4">
							<div class="rounded-xl border border-neutral-100 bg-neutral-50 p-4">
								<dt class="text-xs font-medium text-neutral-500">Tokens</dt>
								<dd class="mt-1 text-lg font-semibold tabular-nums">{{ data.usage.chats.tokens.toLocaleString() }}</dd>
							</div>
							<div class="rounded-xl border border-neutral-100 bg-neutral-50 p-4">
								<dt class="text-xs font-medium text-neutral-500">Cost</dt>
								<dd class="mt-1 text-lg font-semibold tabular-nums">{{ formatCurrency(data.usage.chats.cost) }}</dd>
							</div>
							<div class="rounded-xl border border-neutral-100 bg-neutral-50 p-4">
								<dt class="text-xs font-medium text-neutral-500">Price</dt>
								<dd class="mt-1 text-lg font-semibold tabular-nums">{{ formatCurrency(data.usage.chats.price || 0) }}</dd>
							</div>
						</dl>
					</article>
				</section>

				<!-- Footnote -->
				<p class="mt-8 text-xs text-neutral-500">"Cost" is raw provider cost; "Price" is the billable amount in the teams plan.</p>
			</div>
		</div>
	</FullWidthLayout>
</template>
