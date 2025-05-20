import { createRouter, createWebHistory } from 'vue-router';

// Import pages
import Home from '@/pages/Home.vue';
import Dashboard from '@/pages/Dashboard.vue';
import Analytics from '@/pages/Analytics.vue';
import Login from '@/Pages/Auth/Login.vue';
import Register from '@/pages/auth/Register.vue';
import ForgotPassword from '@/Pages/Auth/ForgotPassword.vue';
import ResetPassword from '@/Pages/Auth/ResetPassword.vue';
import TeamsIndex from '@/pages/teams/Index.vue';
import TeamShow from '@/pages/teams/Show.vue';

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
    component: Dashboard,
  },
  {
    path: '/analytics',
    name: 'analytics',
    component: Analytics,
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
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// Navigation guard for authentication
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token');
  
  if (to.matched.some(record => record.meta.requiresAuth)) {
    if (!token) {
      next({ name: 'login' });
    } else {
      next();
    }
  } else if (to.matched.some(record => record.meta.guest)) {
    if (token) {
      next({ name: 'home' });
    } else {
      next();
    }
  } else {
    next();
  }
});

export default router;
