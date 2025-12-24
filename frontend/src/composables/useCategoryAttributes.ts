import { ref } from 'vue'
import { fetchCategoryAttributes, type AttributeDef } from '../services/api/categoryAttributes'

const cache = ref<Record<number, AttributeDef[]>>({})

export function useCategoryAttributes() {
    const load = async (catId: number) => {
        if (cache.value[catId]) {
            return cache.value[catId]
        }
        const attrs = await fetchCategoryAttributes(catId)
        cache.value[catId] = Array.isArray(attrs) ? attrs : []
        return cache.value[catId]
    }

    return { load }
}