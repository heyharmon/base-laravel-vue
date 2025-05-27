import { createRouter, createWebHistory } from 'vue-router'
import { useTeamStore } from '@/stores/teamStore'
import auth from '@/services/auth'

// Import pages
import Home from '@/pages/Home.vue'
import Dashboard from '@/pages/Dashboard.vue'
import Analytics from '@/pages/Analytics.vue'
import Login from '@/pages/auth/Login.vue'
import Register from '@/pages/auth/Register.vue'
import ForgotPassword from '@/pages/auth/ForgotPassword.vue'
import ResetPassword from '@/pages/auth/ResetPassword.vue'
import TeamsIndex from '@/pages/teams/Index.vue'
import TeamShow from '@/pages/teams/Show.vue'
import TeamCreate from '@/pages/teams/Create.vue'
import OrganizationsIndex from '@/pages/organizations/Index.vue'
import OrganizationCreate from '@/pages/organizations/Create.vue'
import OrganizationEdit from '@/pages/organizations/Edit.vue'

const routes = [
	{
		path: '/',
		name: 'home',
		component: Dashboard,
		meta: { requiresAuth: true }
	},
	{
		path: '/dashboard',
		name: 'dashboard',
		component: Dashboard
	},
	{
		path: '/analytics',
		name: 'analytics',
		component: Analytics
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
		path: '/teams',
		name: 'teams.index',
		component: TeamsIndex,
		meta: { requiresAuth: true }
	},
	{
		path: '/teams/:id',
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
		name: 'organizations.index',
		component: OrganizationsIndex,
		meta: { requiresAuth: true }
	},
	{
		path: '/organizations/create',
		name: 'organizations.create',
		component: OrganizationCreate,
		meta: { requiresAuth: true }
	},
	{
		path: '/organizations/:id/edit',
		name: 'organizations.edit',
		component: OrganizationEdit,
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

	if (to.matched.some((record) => record.meta.requiresAuth)) {
		if (!token) {
			next({ name: 'login' })
		} else {
			// Check if the route requires a team and is not the teams.create route
			if (to.name !== 'teams.create') {
				const teamStore = useTeamStore()
				
				// Only fetch teams if we haven't already loaded them
				if (teamStore.ownedTeams.length === 0 && teamStore.joinedTeams.length === 0) {
					try {
						await teamStore.fetchTeams()
					} catch (error) {
						console.error('Error fetching teams in router guard:', error)
					}
				}
				
				// If user has no teams, redirect to teams.create
				if (teamStore.ownedTeams.length === 0 && teamStore.joinedTeams.length === 0) {
					next({ name: 'teams.create' })
					return
				}
			}
			next()
		}
	} else if (to.matched.some((record) => record.meta.guest)) {
		if (token) {
			next({ name: 'home' })
		} else {
			next()
		}
	} else {
		next()
	}
})

export default router
