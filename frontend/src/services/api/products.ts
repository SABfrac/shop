import http from './http'


export async function searchProducts({ categoryId, brandId, q, limit = 10, page = 1 }) {
    const { data } = await http.get("/products", {
        params: {
            category_id: categoryId,
            brand_id: brandId,
            q,
            limit,
            page
        },

    });
    // Верните напрямую массив или объект вида { items: [...] }
    return data;
}

export async function getProduct(id) {
    const { data } = await http.get(`/products/${id}`, );
    return data;
}