import { ref, computed, watch } from 'vue';
import http from '../services/api/http';
import { fetchCategories } from '../services/api/categories';
import { getBrands } from '../services/api/brands';
import { searchProducts, getProduct } from '../services/api/products'; // ← добавьте getProduct
import { fetchCategoryAttributes, type AttributeDef } from '../services/api/categoryAttributes';
import { fetchBrandsFetcher, fetchProductsFetcher } from './useCatalogFetchers';

export function useCatalogSelection() {
    const categoryId = ref<number | null>(null);
    const brandId = ref<number | null>(null);
    const productId = ref<number | null>(null);
    const attributes = ref<AttributeDef[]>([]);
    const product = ref<{ id: number; name: string; slug?: string; description?: string } | null>(null); // ← новое

    const isLeaf = computed(() => !!productId.value);

    // Загрузка данных товара при смене productId
    watch(productId, async (id) => {
        if (!id) {
            product.value = null;
            return;
        }
        try {
            const p = await getProduct(id);
            product.value = {
                id: p.id,
                name: p.name || '',
                slug: p.slug || '',
                description: p.description || ''
            };
        } catch (e) {
            console.error('Не удалось загрузить товар:', e);
            product.value = null;
        }
    });

    // при смене категории — грузим variant_attributes и сбрасываем остальное
    watch(categoryId, async (id) => {
        productId.value = null;
        product.value = null; // ← сброс
        attributes.value = [];

        if (!id) {
            brandId.value = null;
            return;
        }
        try {
            attributes.value = await fetchCategoryAttributes(id);
        } catch (e) {
            console.error('Ошибка загрузки атрибутов категории:', e);
            attributes.value = [];
        }
    });

    watch(brandId, () => {
        productId.value = null;
        product.value = null; // ← сброс
    });

    return {
        // state
        categoryId,
        brandId,
        productId,
        product, // ← добавлено
        attributes,
        // computed
        isLeaf,
        // fetchers
        fetchCategories,
        fetchBrands: fetchBrandsFetcher,
        fetchProducts: fetchProductsFetcher,
    };
}