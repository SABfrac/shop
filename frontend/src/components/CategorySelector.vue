<template>
  <div>
    <h3>Выбор категории</h3>

    <!-- 1 уровень -->
    <select v-model="selectedCategories[0]" @change="onCategoryChange(0)">
      <option value="">-- выберите --</option>
      <option v-for="c in categories[0]" :key="c.id" :value="c.id">
        {{ c.name }}
      </option>
    </select>

    <!-- 2+ уровни -->
    <div v-for="(cats, level) in categories.slice(1)" :key="level">
      <select
          v-if="cats.length"
          v-model="selectedCategories[level+1]"
          @change="onCategoryChange(level+1)"
      >
        <option value="">-- выберите --</option>
        <option v-for="c in cats" :key="c.id" :value="c.id">
          {{ c.name }}
        </option>
      </select>
    </div>


  </div>
</template>

<script>

export default {
  name: 'Categories',
  props: {
    modelValue: { type: [Number, String, null], default: null }, // v-model
    // Функция загрузки категорий. Должна вернуть Promise<Array<{id,name}>>.
    fetcher: { type: Function, required: true },
    rootParentId: { type: [Number, String, null], default: null }, // с чего стартуем
    maxLevels: { type: Number, default: 10 }
  },
  emits: ['update:modelValue', 'category-selected'],
  data() {
    return {
      categories: [[]],
      selectedCategories: [null],
      finalCategoryId: null,
      loading: false,
      error: null
    }
  },
  async created() {
    await this.loadCategories(this.rootParentId, 0)
  },
  methods: {
    async loadCategories(parentId, level) {
      if (level >= this.maxLevels) {
        this.finalCategoryId = parentId
        return
      }
      this.loading = true
      this.error = null
      try {
        const data = await this.fetcher(parentId)
        // усечение слоёв при переходе назад
        this.categories = this.categories.slice(0, level + 1)
        this.selectedCategories = this.selectedCategories.slice(0, level + 1)

        if (data && data.length) {
          this.categories[level] = data
          this.categories[level + 1] = []
          this.selectedCategories[level] = ''
          this.finalCategoryId = null
        } else {
          this.finalCategoryId = parentId // лист
        }
      } catch (e) {
        console.error(e)
        this.error = 'Не удалось загрузить категории'
      } finally {
        this.loading = false
      }
    },
    async onCategoryChange(level) {
      const chosenId = this.selectedCategories[level]
      const node = this.categories[level].find(c => c.id === chosenId)

      if (node?.is_leaf) {
        this.finalCategoryId = chosenId
        this.$emit('update:modelValue', chosenId)
        this.$emit('category-selected', chosenId)
        this.categories = this.categories.slice(0, level + 1)
        this.selectedCategories = this.selectedCategories.slice(0, level + 1)
        return

      }
      await this.loadCategories(chosenId, level + 1)
    }
  }

}
</script>