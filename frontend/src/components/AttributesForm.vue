<template>
  <div>
    <h3>Атрибуты товара (SKU)</h3>
    <div v-for="attr in attributes" :key="attr.id" class="form-group">
      <label :for="'attr-' + attr.id">
        {{ attr.name }} <span v-if="attr.is_required">*</span>
      </label>

      <input
          v-if="attr.type === 'string'"
          type="text"
          :id="'attr-' + attr.id"
          v-model="formValues[attr.id].value_string"
          class="form-control"
      />

      <input
          v-else-if="attr.type === 'integer'"
          type="number"
          :id="'attr-' + attr.id"
          v-model.number="formValues[attr.id].value_int"
          class="form-control"
      />

      <input
          v-else-if="attr.type === 'float'"
          type="number"
          step="0.01"
          :id="'attr-' + attr.id"
          v-model.number="formValues[attr.id].value_float"
          class="form-control"
      />

      <input
          v-else-if="attr.type === 'bool'"
          type="checkbox"
          :id="'attr-' + attr.id"
          v-model="formValues[attr.id].value_bool"
      />

      <select
          v-else-if="attr.type === 'select'"
          :id="'attr-' + attr.id"
          v-model.number="formValues[attr.id].attribute_option_id"
          class="form-control"
      >
        <option value="">-- выберите --</option>
        <option v-for="opt in attr.options || []" :key="opt.id" :value="opt.id">
          {{ opt.label }}
        </option>
      </select>
    </div>

    <button type="button" class="btn btn-primary" :disabled="submitting" @click="submitForm">
      Применить атрибуты
    </button>
  </div>
</template>

<script>
export default {
  name: "AttributesForm",
  props: {
    attributes: { type: Array, required: true },
    value: { type: Array, default: () => [] } // опционально: стартовые значения
  },
  data() {
    return {
      formValues: {},
      submitting: false
    }
  },
  watch: {
    attributes: {
      immediate: true,
      handler(newAttrs) {
        const next = {}
        ;(newAttrs || []).forEach(attr => {
          next[attr.id] = {
            attribute_id: attr.id,
            type: attr.type, // важно для хэша
            value_string: null,
            value_int: null,
            value_float: null,
            value_bool: false,
            attribute_option_id: null
          }
        })
        this.formValues = next
      }
    },
    value: {
      deep: true,
      handler(newVal) {
        if (!Array.isArray(newVal)) return;
        const map = {}
        newVal.forEach(v => { if (v && v.attribute_id) map[v.attribute_id] = v })
        Object.keys(this.formValues).forEach(id => {
          const v = map[id] || {}
          this.formValues[id] = { ...this.formValues[id], ...v }
        })
      }
    }
  },
  methods: {
    async submitForm() {
      this.submitting = true
      try {
        const errors = []
        for (const attr of this.attributes) {
          const v = this.formValues[attr.id]
          if (!attr.is_required) continue
          if (attr.type === 'string'  && (!v.value_string || v.value_string === '')) errors.push(attr.name)
          if (attr.type === 'integer' && (v.value_int === null || v.value_int === undefined)) errors.push(attr.name)
          if (attr.type === 'float'   && (v.value_float === null || v.value_float === undefined)) errors.push(attr.name)
          if (attr.type === 'select'  && (v.attribute_option_id === null || v.attribute_option_id === undefined || v.attribute_option_id === '')) errors.push(attr.name)
          if (attr.type === 'bool'    && attr.is_required && v.value_bool !== true) errors.push(attr.name)
        }
        if (errors.length) {
          alert('Заполните обязательные поля: ' + errors.join(', '))
          return
        }
        const result = Object.values(this.formValues)
        this.$emit('save-attributes', result)
      } finally {
        this.submitting = false
      }
    }
  }
}
</script>

<style scoped>
.form-group {
  margin-bottom: 1rem;
}
</style>