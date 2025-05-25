import { createRouter, createWebHistory } from 'vue-router';

// Import pages
import Home from '@/pages/Home.vue';
import CMS from '@/pages/CMS.vue';
import OrganizationCreate from '@/pages/organizations/Create.vue';

const routes = [
  {
    path: '/',
    name: 'home',
    component: Home,
  },
  {
    path: '/cms',
    name: 'cms',
    component: CMS,
  },
  {
    path: '/organizations/create',
    name: 'organizations.create',
    component: OrganizationCreate,
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;
