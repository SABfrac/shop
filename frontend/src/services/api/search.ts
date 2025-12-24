import http from './http';
import type {  ProductSearchParams, ProductSearchResponse } from '../../types/product';

export const searchProducts = (
    params: ProductSearchParams,
    options: { signal?: AbortSignal } = {}
): Promise<ProductSearchResponse> => {
    return http
        .get('/search/products', {
            params,
            signal: options.signal, // axios поддерживает это
        })
        .then((res) => res.data);
};


/**
 * Автодополнение (suggestions) по частичному вводу
 */
export const suggestProducts = (
    query: string,
    options: { signal?: AbortSignal } = {}
): Promise<string[]> => {
    return http
        .get('/search/suggest', {
            params: { q: query },
            signal: options.signal,
        })
        .then((res) => res.data); // ожидается string[]
};


