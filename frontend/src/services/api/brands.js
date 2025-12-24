import http from './http'

// Принимает categoryId и опциональный search
export const getBrands = async (categoryId, search = null) => {
    if (!categoryId) {
        throw new Error("categoryId is required");
    }

    const params = new URLSearchParams();
    // Не нужно добавлять category_id в params, если он уже в URL path `/categories/${categoryId}/brands`
    // params.append('category_id', categoryId.toString()); // <-- Убираем эту строку

    if (search) {
        params.append('search', search);
    }

    // Формируем URL с параметрами
    const queryString = params.toString();
    const url = `/categories/${categoryId}/brands${queryString ? '?' + queryString : ''}`;

    try {
        const { data } = await http.get(url, { skipAuth: true });
        return data;
    } catch (error) {
        console.error('Ошибка загрузки брендов по категории:', error);
        throw error;
    }
};



export const createBrand = (formData) => http.post('/brands', formData);

export function attachBrandToCategory(brandId, categoryId) {
    return http.post("/brand-category", { brand_id: brandId, category_id: categoryId });
}