import { createRouter, createWebHistory } from 'vue-router';

// Import pages
import Home from '@/pages/Home.vue';
import Login from '@/pages/auth/Login.vue';
import Register from '@/pages/auth/Register.vue';

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
