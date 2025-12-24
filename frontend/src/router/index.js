import { createRouter, createWebHistory } from 'vue-router'

// страницы
import SearchPage from '@/views/SearchPage.vue'
import AttributesPage from '@/views/AttributesPage.vue'
import ProductCreate from '@/views/ProductCreate.vue'
import VendorRegister from '@/views/VendorRegister.vue'
import VendorLogin from '@/views/VendorLogin.vue'
import VendorDashboard from '@/views/VendorDashboard.vue'
import OffersCreate from '@/views/OffersCreate.vue'


const routes = [

    {
        path: '/',
        name: 'ProductSearch',
        component: SearchPage
    },

    { path: '/attributes',
        name: 'attributes',
        component: AttributesPage
    },
    { path: '/products',
        name: 'products',
        component: ProductCreate
    },

    {
        path: '/vendor/register',
        name: 'VendorRegister',
        component: VendorRegister
    },
    {
        path: '/vendor/login',
        name: 'VendorLogin',
        component: VendorLogin
    },
    {
        path: '/vendor/dashboard',
        name: 'VendorDashboard',
        component: VendorDashboard,
        meta: { requiresAuth: true }
    },

    {
        path: '/offers/create',
        name: 'OffersCreate',
        component: OffersCreate
    },
    {
        path: '/vendors/offers/new',
        component: () => import('@/views/OfferForm.vue'),
        meta: { requiresAuth: true }
    },

    {
        path: '/feed/upload',
        name: 'FeedUpload',
        component: () => import('@/views/vendor/feed/FeedUpload.vue'),
        meta: { requiresAuth: true, role: 'vendor' }
    },
    {
        path: '/vendors/offer/:id/edit',
        name: 'offer-edit',
        component: () => import('@/views/vendor/offer/OfferEdit.vue'),
        meta: { requiresAuth: true }
    },


]

export default createRouter({
    history: createWebHistory(),
    routes,
})