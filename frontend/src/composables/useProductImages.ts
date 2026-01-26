import { ref, Ref } from 'vue'
import http from '../services/api/http';

export interface ImageRecord {
    id: number
    storage_path: string
    filename: string | null
    is_main: boolean
    sort_order: number
    preview_url: string
}

export function useProductImages() {
    const images = ref<ImageRecord[]>([])
    const loading = ref(false)

    const loadImages = async (entityType: 'global_product' | 'offer', entityId: number) => {
        loading.value = true
        try {
            const res = await http.get('/vendor-product/get-images', {
                params: {entity_type: entityType, entity_id: entityId}
            })
            images.value = res.data.images || []
            console.log(images.value)
        } catch (e) {
            console.error('Ошибка загрузки изображений:', e)
            images.value = []
        } finally {
            loading.value = false
        }
    }

    const requestUploadUrls = async (
        entityType: 'global_product' | 'offer',
        entityId: number,
        filenames: string[]
    ) => {
        const res = await http.post('/vendor-product/request-image-upload', {
            entity_type: entityType,
            entity_id: entityId,
            filenames
        })
        return res.data.urls
    }

    const confirmImages = async (
        entityType: 'global_product' | 'offer',
        entityId: number,
        imageMeta: { storage_path: string; filename: string }[]
    ) => {
        await http.post('/vendor-product/confirm-images', {
            entity_type: entityType,
            entity_id: entityId,
            images: imageMeta
        })

        await loadImages(entityType, entityId)
    }

    const setMainImage = async (imageId: number) => {
        await http.post('/vendor-product/set-main-image', {image_id: imageId})
    }


    return {
        images: images as Ref<ImageRecord[]>,
        loading: loading as Ref<boolean>,
        loadImages,
        requestUploadUrls,
        confirmImages,
        setMainImage,

    }
}