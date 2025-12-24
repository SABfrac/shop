import { getBrands } from '../services/api/brands';
import { searchProducts } from '../services/api/products';

export async function fetchBrandsFetcher(catId: number, search: string | null = null) {
    // Передаём search в getBrands
    const res = await getBrands(catId, search);
    return Array.isArray((res as any)?.data) ? (res as any).data : Array.isArray(res) ? res : [];
}

export async function fetchProductsFetcher({ categoryId, brandId, q, page = 1 }: {
    categoryId: number;
    brandId: number;
    q?: string;
    page?: number;
}) {
    return searchProducts({ categoryId, brandId, q, limit: 15, page });
}