import { createRouter, createWebHistory } from 'vue-router';

// Import pages
import Home from '@/pages/Home.vue';
import Login from '@/pages/auth/Login.vue';
import Register from '@/pages/auth/Register.vue';
import TeamsIndex from '@/pages/teams/Index.vue';
import TeamShow from '@/pages/teams/Show.vue';
import BankingAccounts from '@/pages/banking/Accounts.vue';
import BankingTransactions from '@/pages/banking/Transactions.vue';
import CategorizationHistory from '@/pages/banking/CategorizationHistory.vue';

const routes = [
  {
    path: '/',
    name: 'home',
    component: Home,
    meta: { requiresAuth: true }
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
    path: '/banking/accounts',
    name: 'banking.accounts',
    component: BankingAccounts,
    meta: { requiresAuth: true }
  },
  {
    path: '/banking/transactions',
    name: 'banking.transactions',
    component: BankingTransactions,
    meta: { requiresAuth: true }
  },
  {
    path: '/banking/categorization-history',
    name: 'banking.categorization-history',
    component: CategorizationHistory,
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
