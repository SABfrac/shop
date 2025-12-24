import { ref, computed, watch } from 'vue';
import http from '../services/api/http';
import { listSkus } from '../services/api/skus';
import { useVendorStore } from '../stores/vendor';

type Sku = {
    id: number;
    barcode?: string | null;
    variant_values?: Record<string, string>;
    attributes?: any[];
    my_offer?: {
        id: number;
        vendor_sku: string | null;
        price: number;
        stock: number;
        warranty?: number | null;
        condition?: string; // ← добавлено
        status?: number;    // ← добавлено
    } | null;
};

type OfferDraft = {
    vendor_sku?: string | null;
    sku_id: number;
    vendor_id: number;
    price: number;
    stock: number;
    condition: 'new' | 'used' | 'refurbished';
    warranty?: number | null;
    status: 0 | 1 | 2;
    sort_order: number;
};

export function useOfferCreate(deps: {
    productId: ReturnType<typeof ref<number | null>>;
}) {
    const { productId } = deps;
    const vendorStore = useVendorStore();
    const skus = ref<Sku[]>([]);
    const selectedSkuIds = ref<number[]>([]);
    const offers = ref<Record<number, OfferDraft>>({});

    const hasProduct = computed(() => !!productId.value);

    async function loadSkus() {
        if (!productId.value) {
            skus.value = [];
            selectedSkuIds.value = [];
            offers.value = {};
            return;
        }

        try {
            const skuArray = await listSkus(productId.value, {
                with: 'attributes,my_offer'
            });

            skus.value = Array.isArray(skuArray) ? skuArray : [];

            // Инициализируем offers
            const newOffers: Record<number, OfferDraft> = {};
            for (const s of skus.value) {
                const myOffer = s.my_offer;
                newOffers[s.id] = {
                    vendor_sku: myOffer?. vendor_sku ?? null,
                    sku_id: s.id,
                    vendor_id: vendorStore.vendorId ?? 1,
                    price: myOffer?.price ?? 0,
                    stock: myOffer?.stock ?? 0,
                    warranty: myOffer?.warranty ?? null,
                    condition: (myOffer?.condition as any) ?? 'new',
                    status: (myOffer?.status as any) ?? 2, // 2 = черновик/на модерации
                    sort_order: 0,
                };
            }
            offers.value = newOffers;
        } catch (err) {
            console.error('Ошибка загрузки SKU:', err);
            skus.value = [];
            offers.value = {};
        }
    }

    watch(productId, loadSkus, { immediate: true });

    function setMassPrice(val: number) {
        selectedSkuIds.value.forEach(id => {
            if (offers.value[id]) offers.value[id].price = val;
        });
    }

    function setMassStock(val: number) {
        selectedSkuIds.value.forEach(id => {
            if (offers.value[id]) offers.value[id].stock = val;
        });
    }

    return {
        skus,
        selectedSkuIds,
        offers,
        hasProduct,
        loadSkus,
        setMassPrice,
        setMassStock,
    };
}