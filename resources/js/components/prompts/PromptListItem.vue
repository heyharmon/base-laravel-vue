<script setup>
import moment from 'moment'
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { usePromptStore } from '@/stores/promptStore'
import { useArticleStore } from '@/stores/articleStore'
import { useUsageStore } from '@/stores/usageStore'
import { useNotificationStore } from '@/stores/notificationStore'
import auth from '@/services/auth'
import SparkleIcon from '@/components/icons/SparkleIcon.vue'
import TrashIcon from '@/components/icons/TrashIcon.vue'
import Button from '@/components/ui/Button.vue'

const router = useRouter()
const route = useRoute()
const teamId = route.params.teamId
const campaignId = route.params.campaignId

const promptStore = usePromptStore()
const articleStore = useArticleStore()
const usageStore = useUsageStore()
const notificationStore = useNotificationStore()

const props = defineProps({
	prompt: { type: Object, required: true },
	isSelected: { type: Boolean, default: false },
	jobs: { type: Array, default: () => [] }
})

const emit = defineEmits(['select', 'run', 'delete'])
const isRunMenuOpen = ref(false)

const isLoading = computed(() => promptStore.loadingPromptIds.includes(props.prompt.id))

// Auth-based permissions
const user = computed(() => auth.getUser())
const isSuperAdmin = computed(() => user.value?.is_super_admin)

// In-progress responses provided by backend (queued + in_progress)
const inProgressResponses = computed(() => props.prompt?.in_progress_responses || [])

// Human-friendly summary like: "2 queued" or "1 queued, 1 in progress"
const inProgressSummary = computed(() => {
	const counts = inProgressResponses.value.reduce((acc, r) => {
		const st = r.status || 'in_progress'
		acc[st] = (acc[st] || 0) + 1
		return acc
	}, {})
	const parts = []
	if (counts.queued) parts.push(`${counts.queued} queued response${counts.queued > 1 ? 's' : ''}`)
	if (counts.in_progress) parts.push(`${counts.in_progress} in progress response${counts.in_progress > 1 ? 's' : ''}`)
	return parts.join(', ')
})

// Latest update time across active responses, shown as relative time (e.g., "2 minutes ago")
const inProgressLastUpdatedRelative = computed(() => {
	if (!inProgressResponses.value || inProgressResponses.value.length === 0) return ''
	let latest = null
	for (const r of inProgressResponses.value) {
		const ts = r.updated_at || r.created_at
		if (!ts) continue
		if (!latest || moment(ts).isAfter(moment(latest))) latest = ts
	}
	return latest ? moment(latest).fromNow() : ''
})

const formattedCreatedAt = computed(() => {
	if (!props.prompt.created_at) return ''
	return moment(props.prompt.created_at).fromNow()
})

const isNewPrompt = computed(() => {
	if (!props.prompt.created_at) return false
	return moment().diff(moment(props.prompt.created_at), 'minutes') <= 10
})

const toggleRunMenu = () => {
	isRunMenuOpen.value = !isRunMenuOpen.value
}

const closeRunMenu = () => {
	isRunMenuOpen.value = false
}

const runPrompt = (count) => {
	emit('run', props.prompt.id, count)
	closeRunMenu()
}

const confirmDelete = () => emit('delete', props.prompt)

const createArticle = async () => {
	try {
		const newArticle = await articleStore.createArticle(teamId, campaignId, {
			title: 'Untitled article',
			prompt_id: props.prompt.id
		})
		await usageStore.fetchUsage(teamId)
		router.push({
			name: 'articles.edit',
			params: { teamId, campaignId, articleId: newArticle.id }
		})
	} catch (error) {
		notificationStore.addNotification({
			message: error?.message || 'Unable to create article',
			type: 'error'
		})
	}
}
</script>

<template>
	<div
		class="flex items-start justify-between p-4 border border-neutral-300 hover:border-neutral-500 rounded-lg cursor-pointer"
		:class="{ 'border-2 border-neutral-400 bg-neutral-50': isSelected }"
		@click="$emit('select', prompt)"
	>
		<div>
			<div v-if="isNewPrompt" class="flex items-center gap-2 text-xs mb-2">
				<span class="bg-green-100 text-green-800 rounded-full px-2 py-0.5"> Created {{ formattedCreatedAt }} </span>
			</div>
			<p class="text-neutral-800 text-lg">{{ prompt.content }}</p>
			<div v-if="prompt.terms_count >= 0" class="flex items-center gap-2 text-sm text-neutral-500 mt-1">
				<p v-if="prompt.mentions_percentage !== undefined">
					Mentioned {{ prompt.mentions_percentage }}% of the time out of
					{{ prompt.responses_count }}
					{{ prompt.responses_count === 1 ? 'response' : 'responses' }}
				</p>
				<!-- <p>•</p> -->
				<!-- <p>
					{{ prompt.terms_count }} term
					{{ prompt.terms_count === 1 ? 'occurrence' : 'occurrences' }}
				</p> -->
			</div>
			<div v-else class="text-sm text-neutral-500 mt-1">New prompt</div>
		</div>

		<div class="flex justify-end items-center space-x-4">
			<!-- Dedicated processing status (shows for all roles when any responses are active) -->
			<div v-if="inProgressResponses.length > 0" class="flex items-center gap-1.5 text-sm text-neutral-600">
				<div class="animate-spin rounded-full h-3 w-3 border border-b-transparent border-neutral-800"></div>
				<span>
					Processing {{ inProgressSummary }} <template v-if="inProgressLastUpdatedRelative"> {{ inProgressLastUpdatedRelative }}</template>
				</span>
			</div>

			<!-- Create article button -->
			<Button @click.stop="createArticle" class="flex items-center gap-2 mr-2" variant="success_outline" size="sm">
				<SparkleIcon />
				Improve visibility
			</Button>

			<!-- Run prompt button -->
			<div v-if="isSuperAdmin" class="relative flex items-center">
				<Button @click.stop="toggleRunMenu" :disabled="isLoading" variant="outline" size="sm">
					<span>Run</span>
				</Button>

				<div
					v-if="isRunMenuOpen"
					class="absolute right-0 mt-1 w-20 bg-white border border-neutral-300 rounded-md shadow-lg z-10 overflow-hidden"
					@click.stop
				>
					<button @click.stop="runPrompt(1)" class="w-full px-3 py-1.5 text-left text-xs hover:bg-neutral-100 transition-colors cursor-pointer">
						Run 1x
					</button>
					<button @click.stop="runPrompt(3)" class="w-full px-3 py-1.5 text-left text-xs hover:bg-neutral-100 transition-colors cursor-pointer">
						Run 3x
					</button>
					<button @click.stop="runPrompt(5)" class="w-full px-3 py-1.5 text-left text-xs hover:bg-neutral-100 transition-colors cursor-pointer">
						Run 5x
					</button>
				</div>
			</div>

			<button
				@click.stop="confirmDelete"
				class="-mr-2 p-1.5 text-neutral-400 hover:text-neutral-600 transition-colors cursor-pointer"
				aria-label="Delete prompt"
			>
				<TrashIcon />
			</button>
		</div>
	</div>
</template>
