<script setup>
import moment from 'moment'
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { usePromptStore } from '@/stores/promptStore'
import { useArticleStore } from '@/stores/articleStore'
import { useUsageStore } from '@/stores/usageStore'
import { useUserStore } from '@/stores/userStore'
import { useNotificationStore } from '@/stores/notificationStore'
import auth from '@/services/auth'
import SparkleIcon from '@/components/icons/SparkleIcon.vue'
import EllipsesVerticalIcon from '@/components/icons/EllipsesVerticalIcon.vue'
import Button from '@/components/ui/Button.vue'
import DeletePromptModal from '@/components/prompts/DeletePromptModal.vue'
import RunPromptWarningModal from '@/components/prompts/RunPromptWarningModal.vue'

const router = useRouter()
const route = useRoute()
const teamId = route.params.teamId
const campaignId = route.params.campaignId

const promptStore = usePromptStore()
const articleStore = useArticleStore()
const usageStore = useUsageStore()
const notificationStore = useNotificationStore()
const userStore = useUserStore()

const props = defineProps({
	prompt: { type: Object, required: true },
	isSelected: { type: Boolean, default: false },
	jobs: { type: Array, default: () => [] }
})

const emit = defineEmits(['select', 'run'])
const isMenuOpen = ref(false)
const CLOSE_ALL_EVENT = 'prompt-item-menu:close-all'
const menuButtonRef = ref(null)
const menuRef = ref(null)
const isDeleteOpen = ref(false)
const isRunWarningOpen = ref(false)
const pendingRunCount = ref(null)

const isLoading = computed(() => promptStore.loadingPromptIds.includes(props.prompt.id))

// Auth-based permissions
const user = computed(() => userStore.currentUser?.value ?? auth.getUser())
const isSuperAdmin = computed(() => user.value?.is_super_admin)
const hasAcknowledgedRunWarning = computed(() => !!user.value?.acknowledged_individual_run_warning)

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
	if (counts.queued) parts.push(`${counts.queued} response${counts.queued > 1 ? 's' : ''} queued`)
	if (counts.in_progress) parts.push(`${counts.in_progress} response${counts.in_progress > 1 ? 's' : ''} in progress`)
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

const toggleMenu = () => {
	if (!isMenuOpen.value) {
		// Close any other open menus in sibling items before opening this one
		document.dispatchEvent(new Event(CLOSE_ALL_EVENT))
	}
	isMenuOpen.value = !isMenuOpen.value
}

const closeMenu = () => {
	isMenuOpen.value = false
}

const runPrompt = (count) => {
	emit('run', props.prompt.id, count)
	closeMenu()
}

const onClickRun = (count) => {
	if (!hasAcknowledgedRunWarning.value) {
		pendingRunCount.value = count
		isRunWarningOpen.value = true
		closeMenu()
		return
	}
	runPrompt(count)
}

const openDelete = () => {
	isDeleteOpen.value = true
	closeMenu()
}

const closeDelete = () => {
	isDeleteOpen.value = false
}

const confirmDelete = async () => {
	try {
		await promptStore.deletePrompt(props.prompt.id)
	} finally {
		isDeleteOpen.value = false
	}
}

const confirmRunWarning = async () => {
	try {
		await userStore.acknowledgeIndividualRunWarning()
		isRunWarningOpen.value = false
		if (pendingRunCount.value) {
			runPrompt(pendingRunCount.value)
		}
	} finally {
		pendingRunCount.value = null
	}
}

const cancelRunWarning = () => {
	isRunWarningOpen.value = false
	pendingRunCount.value = null
}

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

// Close menu on outside click
const handleDocumentClick = () => {
	if (isMenuOpen.value) {
		isMenuOpen.value = false
	}
}

onMounted(() => {
	document.addEventListener('click', handleDocumentClick)
	document.addEventListener(CLOSE_ALL_EVENT, closeMenu)
})

onBeforeUnmount(() => {
	document.removeEventListener('click', handleDocumentClick)
	document.removeEventListener(CLOSE_ALL_EVENT, closeMenu)
})
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
					{{ inProgressSummary }} <template v-if="inProgressLastUpdatedRelative"> {{ inProgressLastUpdatedRelative }}</template>
				</span>
			</div>

			<!-- Create article button -->
			<Button @click.stop="createArticle" class="flex items-center gap-2 mr-2" variant="success_outline" size="sm">
				<SparkleIcon />
				Improve visibility
			</Button>

			<!-- Actions menu (Run + Delete) -->
			<div class="relative flex items-center">
				<button
					ref="menuButtonRef"
					@click.stop="toggleMenu"
					class="-mr-2 p-1.5 text-neutral-400 hover:text-neutral-600 transition-colors cursor-pointer"
					:aria-expanded="isMenuOpen ? 'true' : 'false'"
					aria-haspopup="menu"
					aria-label="Open actions menu"
				>
					<EllipsesVerticalIcon />
				</button>

				<div
					v-if="isMenuOpen"
					ref="menuRef"
					class="absolute right-0 mt-1 w-28 bg-white border border-neutral-300 rounded-md shadow-lg z-10 overflow-hidden"
					@click.stop
				>
					<template v-if="isSuperAdmin">
					<button
						@click.stop="onClickRun(1)"
						class="w-full px-3 py-1.5 text-left text-xs hover:bg-neutral-100 transition-colors cursor-pointer disabled:opacity-50"
						:disabled="isLoading"
					>
						Run 1x
					</button>
					<button
						@click.stop="onClickRun(3)"
						class="w-full px-3 py-1.5 text-left text-xs hover:bg-neutral-100 transition-colors cursor-pointer disabled:opacity-50"
						:disabled="isLoading"
					>
						Run 3x
					</button>
					<button
						@click.stop="onClickRun(5)"
						class="w-full px-3 py-1.5 text-left text-xs hover:bg-neutral-100 transition-colors cursor-pointer disabled:opacity-50"
						:disabled="isLoading"
					>
						Run 5x
					</button>
						<div class="border-t border-neutral-200"></div>
					</template>
					<button
						@click.stop="openDelete"
						class="w-full px-3 py-1.5 text-left text-xs text-red-600 hover:bg-red-50 transition-colors cursor-pointer"
					>
						Delete
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Delete Confirmation Modal -->
	<DeletePromptModal :is-open="isDeleteOpen" @cancel="closeDelete" @confirm="confirmDelete" />

	<!-- Run Warning Modal (shown once) -->
	<RunPromptWarningModal :is-open="isRunWarningOpen" @cancel="cancelRunWarning" @confirm="confirmRunWarning" />
</template>
