import http from "./http";

export async function createSku(payload: {
    product_id: number | string;
    attributes: Array<any>;
    code?: string | null;
    barcode?: string | null;
    status?: number;
}) {
    const {data} = await http.post("/skus", payload);
    return data;
}

export async function listSkus(productId: number | string, params: {
     with?: string;
    status?: number;
    page?: number;
    limit?: number
} = {}) {
    const {data} = await http.get("/skus", {
        params: {product_id: productId, ...params},

    });
    // Возвращаем только items, если структура как в вашем API
    if (Array.isArray(data?.items)) {
        return data.items;
    }

    // На случай, если API когда-то вернёт просто массив
    if (Array.isArray(data)) {
        return data;
    }
}