import http from './http'

export type AttrType = 'string'|'integer'|'float'|'select'|'bool'
export interface AttributeDef {
    id: number
    name: string
    type: AttrType
    is_required?: boolean
    // опционально:
    options?: Array<{ id: number; label: string }>
    is_variant: boolean
}

export interface AttributeValue {
    attribute_id: number
    type: AttrType
    value_string: string | null
    value_int: number | null
    value_float: number | null
    value_bool: boolean
    attribute_option_id: number | string | null
}

// Вспомогательная функция для создания пустого значения
export function makeEmptyAttributeValue(def: AttributeDef): AttributeValue {
    return {
        attribute_id: def.id,
        type: def.type,
        value_string: null,
        value_int: null,
        value_float: null,
        value_bool: false,
        attribute_option_id: null
    }
}

const TTL = 60 * 60 * 1000 // 1 час
const cache = new Map<number, { t: number; data: AttributeDef[] }>()
const inflight = new Map<number, Promise<AttributeDef[]>>()

function normalize(data: any): AttributeDef[] {
    // ваш бэкенд может вернуть либо массив, либо { variant_attributes: [] }
  const variantAttrs = Array.isArray(data?.variant_attributes) ? data.variant_attributes : [];
    const specAttrs = Array.isArray(data?.spec_attributes) ? data.spec_attributes : [];

    // Добавляем is_variant к каждому атрибуту
    const allAttrs: AttributeDef[] = [
        ...variantAttrs.map(attr => ({ ...attr, is_variant: true })),
        ...specAttrs.map(attr => ({ ...attr, is_variant: false }))
    ];

    return allAttrs;
}

export async function fetchCategoryAttributes(categoryId: number): Promise<AttributeDef[]> {
    const now = Date.now()
    const hit = cache.get(categoryId)
    if (hit && now - hit.t < TTL) return hit.data

    const same = inflight.get(categoryId)
    if (same) return same

    const req = http.get(`/categories/${categoryId}/attributes`,)
        .then(res => normalize(res.data))
        .then(list => {
            cache.set(categoryId, { t: Date.now(), data: list })
            inflight.delete(categoryId)
            return list
        })
        .catch(e => { inflight.delete(categoryId); throw e })

    inflight.set(categoryId, req)
    return req
}