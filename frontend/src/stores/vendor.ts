import { defineStore } from 'pinia'

import http from '../services/api/http';

export const useVendorStore = defineStore('vendor', {
    state: () => ({
        vendorId: null as number | null,
        token: null as string | null,
        isAuthenticated: false,
        profile: null as { name: string; email: string } | null,
    }),

    actions: {
        async login(email: string, password: string) {
            const response = await http.post('vendors/login', { email, password })
            if (response.data.success) {
                this.vendorId = response.data.vendor_id
                this.isAuthenticated = true
                this.profile = response.data.profile

            } else {
                throw new Error(response.data.message || 'Ошибка входа')
            }
        },

        async logout() {
            try {
                // Вызываем эндпоинт выхода, чтобы сервер удалил cookie
                await http.post('vendors/logout')
            } catch (error) {
                console.warn('Ошибка при выходе:', error)
                // Всё равно сбрасываем состояние — на случай, если сервер недоступен
            } finally {
                this.$reset()

            }
        },




        async fetchProfile() {
            try {
                const response = await http.get('vendors/me') // защищённый эндпоинт
                this.vendorId = response.data.vendor_id
                this.isAuthenticated = true
                this.profile = response.data.profile
            } catch (error) {
                this.isAuthenticated = false
                this.vendorId = null
                this.profile = null
            }
        }
        }
})


export const useOfferStore = defineStore('offer', {
    state: () => ({
        currentOfferForEdit: null as any | null,
    }),
    actions: {
        setOfferForEdit(offer: any) {
            this.currentOfferForEdit = offer
        },
        clearOfferForEdit() {
            this.currentOfferForEdit = null
        }
    }
})