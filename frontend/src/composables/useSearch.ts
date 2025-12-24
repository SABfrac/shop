import { ref, computed, onScopeDispose, MaybeRef, unref } from 'vue';
import { searchProducts } from '../services/api/search';
import type { Product, ProductSearchParams } from '../types/product';
import type { ProductSearchResponse } from '../types/product';

export function useSearch(
    queryRef: MaybeRef<string>,
    filters: MaybeRef<Omit<ProductSearchParams, 'query'>> = {},
    {
        minQueryLength = 1,
    }: { minQueryLength?: number } = {}
) {
    const resolvedQuery = computed(() => unref(queryRef));
    const resolvedFilters = computed(() => unref(filters));

    const results = ref<Product[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const hasNext = ref(true);
    const nextCursor = ref<string | null>(null);

    let abortController: AbortController | null = null;

    const cleanup = () => { /* ... */ };
    onScopeDispose(cleanup);

    const searchNow = async (append = false) => {
        if (!append) {
            // Новый поиск — сбрасываем всё
            results.value = [];
            nextCursor.value = null;
            hasNext.value = true;
        }

        if (!hasNext.value) return;

        const q = resolvedQuery.value.trim();
        if (q.length < minQueryLength) {
            results.value = [];
            hasNext.value = false;
            return;
        }

        cleanup();
        abortController = new AbortController();
        loading.value = true;
        error.value = null;

        try {
            const params = {
                query: q,
                cursor: nextCursor.value,
                limit: 20,
                ...resolvedFilters.value,
            };

            const response: ProductSearchResponse = await searchProducts(params, {
                signal: abortController.signal,
            });

            if (abortController.signal.aborted) return;

            const newItems = Array.isArray(response.items) ? response.items : [];
            if (!append) {
                results.value = newItems;
            } else {
                results.value.push(...newItems);
            }

            nextCursor.value = response.next_cursor || null;
            hasNext.value = !!response.next_cursor && newItems.length > 0;
        } catch (err: any) {
            if (err.name === 'AbortError') return;
            error.value = err.message || 'Ошибка поиска';
        } finally {
            loading.value = false;
        }
    };

    return {
        results,
        loading,
        error,
        hasNext,
        searchNow,
        loadMore: () => searchNow(true),
    };
}