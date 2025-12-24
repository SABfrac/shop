export interface ProductAttribute {
    name: string;
    value: string;
}

export interface Product {
    id: number;
    product_id: number;
    sku_id: string;
    category_id: number;
    product_name: string;
    brand_id: number;
    brand_name: string;
    vendor_id: number;
    vendor_sku: string;
    price: number;
    stock: number;
    condition: string;
    warranty: number | null;
    status: number;
    sort_order: number;
    attributes: ProductAttribute[];
    flat_attributes: Record<string, any>; // или конкретизировать: { Цвет?: string; size?: string; weight?: number }
    created_at: string; // ISO 8601
    updated_at: string; // ISO 8601
}

export interface ProductSearchResponse {
    items: Product[];
    total: number;
    next_cursor: string | null;
    // page: number;
    // limit: number;
}

export interface ProductSearchParams {
    query: string;
    category_id?: number;
    brand_id?: number;
    cursor?: string;   // ← добавлено
    limit?: number;
    // Добавьте другие фильтры по мере необходимости:
    // vendor_id?: number;
    // stock?: boolean;
}