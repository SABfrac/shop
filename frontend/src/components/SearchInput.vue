<template>
  <input
      type="text"
      :value="modelValue"
      @input="onInput"
      placeholder="Поиск..."
  />
</template>

<script setup lang="ts">
const props = defineProps<{
  modelValue: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const onInput = (event: Event) => {
  emit('update:modelValue', (event.target as HTMLInputElement).value)
}

</script>