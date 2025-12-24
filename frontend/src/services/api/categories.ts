import http from './http'

type Cat = { id: number; name: string; parent_id?: number | null }

const cache = new Map<string, { t: number; data: Cat[] }>()
const inflight = new Map<string, Promise<Cat[]>>()
const TTL = 5 * 60 * 1000 // 5 мин

function key(parentId: number | null | string) {
    return String(parentId ?? 'root')
}

export async function fetchCategories(parentId: number | null | string) : Promise<Cat[]> {
    const k = key(parentId)
    const now = Date.now()

    const hit = cache.get(k)
    if (hit && now - hit.t < TTL) return hit.data

    const same = inflight.get(k)
    if (same) return same

    const req = http.get('/categories', { params: { parent_id: parentId ?? null } })
        .then(r => r.data as Cat[])
        .then(list => {
            cache.set(k, { t: Date.now(), data: list })
            inflight.delete(k)
            return list
        })
        .catch(e => {
            inflight.delete(k)
            throw e
        })

    inflight.set(k, req)
    return req
}