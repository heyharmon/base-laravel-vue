<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useTeamStore } from '@/stores/teamStore'
import { useCampaignStore } from '@/stores/campaignStore'
import auth from '@/services/auth'

const teamStore = useTeamStore()
const campaignStore = useCampaignStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

const login = async () => {
	loading.value = true
	error.value = ''

	try {
		await auth.login({
			email: email.value,
			password: password.value
		})

                // Load teams after login
                await teamStore.fetchTeams()

		// Fetch campaigns for the current team if available
		const user = JSON.parse(localStorage.getItem('user'))
		if (user && user.current_team_id) {
			await campaignStore.fetchCampaigns(user.current_team_id)
		}

		// Redirect to the originally requested page or home
		const redirectTo = route.query.redirect || '/'
		router.push(redirectTo)
	} catch (err) {
		error.value = err.message || 'Login failed. Please check your credentials.'
	} finally {
		loading.value = false
	}
}
</script>

<template>
	<div class="flex min-h-screen items-center justify-center bg-neutral-50 px-4">
		<div class="w-full max-w-md rounded-lg border border-neutral-200 bg-white p-8 shadow-sm">
			<h1 class="mb-6 text-2xl font-bold text-neutral-900">Login</h1>

			<form @submit.prevent="login" class="space-y-4">
				<div v-if="error" class="rounded-md bg-red-50 p-4 text-sm text-red-500">
					{{ error }}
				</div>

				<div>
					<label for="email" class="mb-1 block text-sm font-medium text-neutral-700">Email</label>
					<input
						id="email"
						v-model="email"
						type="email"
						required
						class="w-full rounded-md border border-neutral-300 px-3 py-2 text-neutral-900 focus:border-neutral-500 focus:outline-none"
					/>
				</div>

				<div>
					<label for="password" class="mb-1 block text-sm font-medium text-neutral-700">Password</label>
					<input
						id="password"
						v-model="password"
						type="password"
						required
						class="w-full rounded-md border border-neutral-300 px-3 py-2 text-neutral-900 focus:border-neutral-500 focus:outline-none"
					/>
				</div>

				<div>
					<button
						type="submit"
						:disabled="loading"
						class="w-full rounded-md bg-neutral-900 px-4 py-2 text-white hover:bg-neutral-800 focus:outline-none disabled:opacity-70"
					>
						{{ loading ? 'Logging in...' : 'Login' }}
					</button>
				</div>

				<div class="pt-2">
					<div class="text-center text-sm text-neutral-600 mb-2">
						Don't have an account?
						<router-link to="/register" class="font-medium text-neutral-900 hover:underline"> Register </router-link>
					</div>

					<div class="text-center text-sm text-neutral-600">
						Forgot password?
						<router-link to="/forgot-password" class="font-medium text-neutral-900 hover:underline"> Reset password </router-link>
					</div>
				</div>
			</form>
		</div>
	</div>
</template>
