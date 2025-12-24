import type { AttributeDef, AttributeValue } from '../services/api/categoryAttributes'

export function useSkuValidation() {
    const validateSkuAttributes = (
        attributes: AttributeDef[],
        values: AttributeValue[]
    ): string[] => {
        const errors: string[] = []

        for (const attr of attributes) {
            if (!attr.is_required) continue

            const v = values.find(x => x.attribute_id === attr.id) || {}

            switch (attr.type) {
                case 'string':
                    if (!v.value_string) errors.push(attr.name)
                    break
                case 'integer':
                    if (v.value_int === null || v.value_int === undefined) errors.push(attr.name)
                    break
                case 'float':
                    if (v.value_float === null || v.value_float === undefined) errors.push(attr.name)
                    break
                case 'select':
                    if (!v.attribute_option_id) errors.push(attr.name)
                    break
                case 'bool':
                    if (v.value_bool !== true) errors.push(attr.name)
                    break
                default:
                    break
            }
        }

        return errors
    }

    return {
        validateSkuAttributes
    }
}