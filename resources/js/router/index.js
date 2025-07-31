import { createRouter, createWebHistory } from 'vue-router'
import auth from '@/services/auth'

// Import pages
import Dashboard from '@/pages/Dashboard.vue'
import { useCampaignStore } from '@/stores/campaignStore'
import Login from '@/pages/auth/Login.vue'
import Register from '@/pages/auth/Register.vue'
import ForgotPassword from '@/pages/auth/ForgotPassword.vue'
import ResetPassword from '@/pages/auth/ResetPassword.vue'
import InvitationsIndex from '@/pages/invitations/Index.vue'
import TeamShow from '@/pages/teams/Show.vue'
import TeamCreate from '@/pages/teams/Create.vue'
import OrganizationsIndex from '@/pages/organizations/Index.vue'
import OrganizationCreate from '@/pages/organizations/Create.vue'
import OrganizationEdit from '@/pages/organizations/Edit.vue'
import PromptsIndex from '@/pages/prompts/Index.vue'
import ArticlesIndex from '@/pages/articles/Index.vue'
import ArticleEdit from '@/pages/articles/Edit.vue'
import CampaignsIndex from '@/pages/campaigns/Index.vue'
import CampaignsEdit from '@/pages/campaigns/Edit.vue'

const routes = [
	{
		path: '/',
		redirect: () => {
			const user = JSON.parse(localStorage.getItem('user') || '{}')
			const teamId = user.current_team_id
			return teamId ? `/teams/${teamId}/campaigns` : '/login'
		}
	},
	{
		path: '/teams/:teamId/campaigns/:campaignId?',
		name: 'home',
		component: Dashboard,
		meta: { requiresAuth: true },
		beforeEnter: async (to, from, next) => {
			const campaignStore = useCampaignStore()
			const teamId = to.params.teamId

			// Always fetch fresh campaign data for the team to ensure we have the correct campaigns
			await campaignStore.fetchCampaigns(teamId)

			if (!to.params.campaignId && campaignStore.defaultCampaign) {
				return next({
					name: 'home',
					params: { teamId: teamId, campaignId: campaignStore.defaultCampaign.id }
				})
			}

			next()
		}
	},
	{
		path: '/dashboard',
		redirect: '/'
	},
	{
		path: '/login',
		name: 'login',
		component: Login,
		meta: { guest: true }
	},
	{
		path: '/register',
		name: 'register',
		component: Register,
		meta: { guest: true }
	},
	{
		path: '/forgot-password',
		name: 'forgot-password',
		component: ForgotPassword,
		meta: { guest: true }
	},
	{
		path: '/reset-password',
		name: 'reset-password',
		component: ResetPassword,
		meta: { guest: true }
	},
	{
		path: '/invitations',
		name: 'invitations.index',
		component: InvitationsIndex,
		meta: { requiresAuth: true }
	},
	{
		path: '/teams/:teamId/members',
		name: 'teams.show',
		component: TeamShow,
		meta: { requiresAuth: true }
	},
	{
		path: '/teams/create',
		name: 'teams.create',
		component: TeamCreate,
		meta: { requiresAuth: true }
	},
	{
		path: '/organizations',
		redirect: () => {
			const user = JSON.parse(localStorage.getItem('user') || '{}')
			const teamId = user.current_team_id
			const campaign = teamId ? JSON.parse(localStorage.getItem(`team_${teamId}_current_campaign`) || '{}') : null
			return teamId && campaign?.id ? `/teams/${teamId}/campaigns/${campaign.id}/organizations` : '/'
		}
	},
	{
		path: '/teams/:teamId/campaigns/:campaignId/organizations',
		name: 'organizations.index',
		component: OrganizationsIndex,
		meta: { requiresAuth: true }
	},
	{
		path: '/teams/:teamId/campaigns/:campaignId/organizations/create',
		name: 'organizations.create',
		component: OrganizationCreate,
		meta: { requiresAuth: true }
	},
	{
		path: '/teams/:teamId/campaigns/:campaignId/organizations/:organizationId/edit',
		name: 'organizations.edit',
		component: OrganizationEdit,
		meta: { requiresAuth: true }
	},
	{
		path: '/teams/:teamId/campaigns/list',
		name: 'campaigns.index',
		component: CampaignsIndex,
		meta: { requiresAuth: true }
	},
	{
		path: '/teams/:teamId/campaigns/:campaignId/edit',
		name: 'campaigns.edit',
		component: CampaignsEdit,
		meta: { requiresAuth: true }
	},
	{
		path: '/prompts',
		redirect: () => {
			const user = JSON.parse(localStorage.getItem('user') || '{}')
			const teamId = user.current_team_id
			const campaign = teamId ? JSON.parse(localStorage.getItem(`team_${teamId}_current_campaign`) || '{}') : null

			if (teamId && campaign?.id) {
				return `/teams/${teamId}/campaigns/${campaign.id}/prompts`
			}
			return '/'
		}
	},
	{
		path: '/teams/:teamId/campaigns/:campaignId/prompts',
		name: 'prompts.index',
		component: PromptsIndex,
		meta: { requiresAuth: true }
	},
	{
		path: '/articles',
		redirect: () => {
			const user = JSON.parse(localStorage.getItem('user') || '{}')
			const teamId = user.current_team_id
			const campaign = teamId ? JSON.parse(localStorage.getItem(`team_${teamId}_current_campaign`) || '{}') : null
			return teamId && campaign?.id ? `/teams/${teamId}/campaigns/${campaign.id}/articles` : '/'
		}
	},
	{
		path: '/teams/:teamId/campaigns/:campaignId/articles',
		name: 'articles.index',
		component: ArticlesIndex,
		meta: { requiresAuth: true }
	},
	{
		path: '/articles/:articleId/edit',
		name: 'articles.edit',
		component: ArticleEdit,
		meta: { requiresAuth: true }
	},
	{
		path: '/super-admin/',
		name: 'super-admin',
		component: () => import('@/pages/super-admin/SuperAdmin.vue'),
		meta: { requiresAuth: true }
	}
]

const router = createRouter({
	history: createWebHistory(),
	routes
})

// Navigation guard for authentication and team check
router.beforeEach(async (to, from, next) => {
	const token = localStorage.getItem('token')

	// Handle guest routes
	if (to.matched.some((record) => record.meta.guest)) {
		return token ? next({ name: 'home' }) : next()
	}

	// Handle non-auth routes
	if (!to.matched.some((record) => record.meta.requiresAuth)) {
		return next()
	}

	// Handle auth routes when not logged in
	if (!token) {
		return next({
			name: 'login',
			query: { redirect: to.fullPath }
		})
	}

	// Skip team check for teams.create route
	if (to.name === 'teams.create') {
		return next()
	}

	// Skip team check for invitations.index route
	if (to.name === 'invitations.index') {
		return next()
	}

	// Allow navigation
	next()
})

export default router
