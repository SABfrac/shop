import {ref, reactive, computed, watch} from 'vue';
import http from '../services/api/http';
import {fetchCategories} from '../services/api/categories';
import {getBrands, createBrand} from '../services/api/brands';
import {searchProducts, getProduct} from '../services/api/products';
import {createSku} from '../services/api/skus';
import {fetchCategoryAttributes} from '../services/api/categoryAttributes'; // новый сервис
import { fetchBrandsFetcher, fetchProductsFetcher } from './useCatalogFetchers';
import type { AttributeDef, AttributeValue,makeEmptyAttributeValue } from '../services/api/categoryAttributes'



export function useProductCreate() {
    // State
    const categoryId = ref<number | null>(null);
    const brandId = ref<number | null>(null);
    const productId = ref<number | null>(null);

    const product = reactive({
        name: '',
        slug: '',
        description: ''
    });

    const attributes = ref<AttributeDef[]>([]);
    const attributesValues = ref<AttributeValue[]>([]);
    const isLeaf = ref(false); // теперь отдельное состояние

    const sku = reactive({
        code: '',
        barcode: ''
    });

    // Helpers
    const makeEmptyAttributeValues = (): AttributeValue[] =>
        attributes.value.map(def => ({
            attribute_id: def.id,
            type: def.type,
            value_string: null,
            value_int: null,
            value_float: null,
            value_bool: false,
            attribute_option_id: null
        }));

    const resetProduct = () => {
        product.name = '';
        product.slug = '';
        product.description = '';
    };

    // Validation
    const attributesValid = computed(() => {
        for (const attr of attributes.value) {
            const v = attributesValues.value.find(x => x.attribute_id === attr.id) as Partial<AttributeValue> || {};
            if (!attr.is_required) continue;

            if (attr.type === 'string' && (!v.value_string || v.value_string === '')) return false;
            if (attr.type === 'integer' && (v.value_int === null || v.value_int === undefined)) return false;
            if (attr.type === 'float' && (v.value_float === null || v.value_float === undefined)) return false;
            if (attr.type === 'select' && (v.attribute_option_id === null || v.attribute_option_id === undefined || v.attribute_option_id === '')) return false;
            if (attr.type === 'bool' && v.value_bool !== true) return false;
        }
        return true;
    });

    const canCreateSku = computed(() => Boolean(productId.value) && attributesValid.value);

    // Effects
    watch(categoryId, async (id) => {
        // Сброс при смене категории
        attributes.value = [];
        attributesValues.value = [];
        productId.value = null;
        resetProduct();
        isLeaf.value = false;

        if (!id) {
            brandId.value = null;
            return;
        }

        // защита от гонок при быстром кліке
        const current = id;

        try {
            // 1) определяем, листовая ли категория, через дерево
            const children = await fetchCategories(id);
            if (categoryId.value !== current) return; // категория успела смениться
            isLeaf.value = children.length === 0;

            // если не лист — бренд/продукт и атрибуты сбрасываем
            if (!isLeaf.value) {
                brandId.value = null;
                productId.value = null;
                attributes.value = [];
                return;
            }

            // 2) только если лист — грузим определения атрибутов
            const defs = await fetchCategoryAttributes(id);
            if (categoryId.value !== current) return;
            attributes.value = Array.isArray(defs) ? defs : [];
        } catch (e) {
            console.error(e);
            attributes.value = [];
            isLeaf.value = false;
        }
    });

    watch(brandId, () => {
        // Сменили бренд — сбрасываем выбор продукта и значения атрибутов SKU
        productId.value = null;
        resetProduct();
        attributesValues.value = [];
    });

    watch(productId, async (id) => {
        if (!id) {
            resetProduct();
            attributesValues.value = [];
            return;
        }

        try {
            const p = await getProduct(id);
            product.name = p?.canonical_name || '';
            product.slug = p?.slug || '';
            product.description = p?.description || '';

            // новые SKU — новые значения атрибутов
            attributesValues.value = makeEmptyAttributeValues();
        } catch (e) {
            console.error(e);
        }
    });


    // Mutations
    function setAttributes(values: AttributeValue[]) {
        attributesValues.value = values;
    }

    // Actions
    async function saveProduct() {
        const payload = {
            category_id: categoryId.value,
            brand_id: brandId.value,
            ...product
        };
        await http.post('/products', payload);
        alert('SPU создан!');
    }

    async function saveSku() {
        if (!canCreateSku.value) return;

        const payload = {
            product_id: productId.value,
            attributes: attributesValues.value,
            code: sku.code || null,
            barcode: sku.barcode || null,
            status: 10
        };
        await createSku(payload);
        alert('SKU создан!');

        // Сброс значений после создания
        attributesValues.value = makeEmptyAttributeValues();
        sku.code = '';
        sku.barcode = '';
    }

    return {
        // state
        categoryId,
        brandId,
        productId,
        product,
        attributes,
        attributesValues,
        sku,

        // computed/state
        isLeaf,
        attributesValid,
        canCreateSku,

        // fetchers
        fetchCategories,           // ваш кэширующий fetcher для CategorySelector
        fetchBrands: fetchBrandsFetcher,
        createBrand,
        fetchProducts: fetchProductsFetcher,

        // mutations/actions
        setAttributes,
        saveProduct,
        saveSku
    };
}