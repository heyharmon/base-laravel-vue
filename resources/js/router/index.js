import { createRouter, createWebHistory } from 'vue-router';

// Import pages
import Home from '@/pages/Home.vue';
import Dashboard from '@/pages/Dashboard.vue';
import Analytics from '@/pages/Analytics.vue';

const routes = [
  {
    path: '/',
    name: 'home',
    component: Dashboard,
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
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;
