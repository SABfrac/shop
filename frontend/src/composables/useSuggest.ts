import { ref, watch,type Ref,onScopeDispose } from 'vue';
import { debounce } from 'lodash-es';
import { suggestProducts } from '../services/api/search';

export function useSuggest(input: Ref<string>, options?: { block?: Ref<boolean> }) {
    const suggestions = ref<string[]>([]);
    const loading = ref(false);

    // Кэш для избежания повторных запросов (если юзер стер букву и написал снова)
    const cache = new Map<string, string[]>();

    // Контроллер для отмены предыдущего запроса
    let abortController: AbortController | null = null;

    onScopeDispose(() => {
        debouncedFetch.cancel();
        if (abortController) {
            abortController.abort();
        }
    });

    const fetchSuggestions = async (q: string) => {
        // 1. Проверка длины
        if (q.length < 2) {
            suggestions.value = [];
            return;
        }

        // 2. Проверка кэша (снижает нагрузку на бэкенд)
        if (cache.has(q)) {
            suggestions.value = cache.get(q)!;
            return;
        }

        // 3. Отмена предыдущего запроса, если он еще висит
        if (abortController) {
            abortController.abort();
        }
        abortController = new AbortController();
        const currentController = abortController;

        loading.value = true;
        try {
            // В suggestProducts нужно передать signal (см. ниже)
            const res = await suggestProducts(q, { signal: currentController.signal });

            const results = Array.isArray(res) ? res : [];
            suggestions.value = results;

            // Сохраняем в кэш
            cache.set(q, results);

            if (cache.size > 200) {
                const firstKey = cache.keys().next().value;
                if (firstKey !== undefined) {
                    cache.delete(firstKey);
                }
            }

        } catch (err: any) {
            // Если ошибка вызвана отменой запроса — игнорируем её
            if (err.name === 'AbortError' || err.code === 'ERR_CANCELED') {
                return;
            }
            console.warn('Suggest failed:', err);
            suggestions.value = [];
        } finally {
            // Снимаем лоадер только если это был последний активный запрос
            if (currentController === abortController && !currentController.signal.aborted) {
                loading.value = false;
            }
        }
    };

    // Debounce 300ms — золотой стандарт для маркетплейсов
    const debouncedFetch = debounce(fetchSuggestions, 300);

    watch(input, (newVal) => {
        if (options?.block?.value) return; // Наша блокировка
        debouncedFetch(newVal.trim());
    });

    const clearSuggestions = () => {
        if (abortController) abortController.abort(); // Отменяем летящий запрос
        suggestions.value = [];
        loading.value = false;
        debouncedFetch.cancel();
    };

    return { suggestions, loading, clearSuggestions };
}