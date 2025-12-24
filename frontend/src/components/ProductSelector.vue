<template>
  <div class="product-selector">
    <label>Продукт (SPU) в категории/бренде</label>
    <input
        type="search"
        v-model="query"
        placeholder="Найти продукт…"
    />
    <div v-if="loading">Поиск…</div>

    <ul v-if="!loading && options.length">
      <li
          v-for="opt in options"
          :key="opt.id"
          @click="select(opt)"
          :class="{ selected: opt.id === modelValue }"
          style="cursor:pointer;padding:4px 8px"
      >
        {{ opt.canonical_name }} <small v-if="opt.slug">({{ opt.slug }})</small>
      </li>
    </ul>

    <div v-if="!loading && !options.length && query">
      Ничего не найдено
    </div>



    <!-- пагинация -->
    <div v-if="meta && totalPages > 1" class="pagination">
      <button :disabled="meta.page === 1" @click="loadPage(meta.page - 1)">
        ←
      </button>

      <button
          v-for="p in visiblePages"
          :key="p"
          :class="{ active: p === meta.page }"
          @click="loadPage(p)"
      >
        {{ p }}
      </button>

      <button v-if="meta.page < totalPages" @click="loadPage(totalPages)">
        Последняя → ({{ totalPages }})
      </button>
    </div>



    <button v-if="modelValue" type="button" @click="clear">Сбросить выбор</button>
  </div>
</template>

<script>
export default {
  name: "ProductSelector",
  props: {
    modelValue: [Number, String, null],
    categoryId: [Number, String],
    brandId: [Number, String],
    fetcher: { type: Function, required: true }
  },
  data() {
    return {
      query: "",
      options: [],
      meta: null,
      loading: false,
      t: null
    };
  },
  computed: {
    totalPages() {
      if (!this.meta) return 0;
      return Math.ceil(this.meta.total / this.meta.limit);
    },
    visiblePages() {
      if (!this.meta) return [];
      const current = this.meta.page;
      const total = this.totalPages;

      const delta = 2; // показываем по 2 страницы слева и справа
      let start = Math.max(1, current - delta);
      let end = Math.min(total, current + delta);

      // корректируем когда в начале или в конце
      if (current <= delta) {
        end = Math.min(total, 1 + delta * 2);
      }
      if (current > total - delta) {
        start = Math.max(1, total - delta * 2);
      }

      const pages = [];
      for (let i = start; i <= end; i++) {
        pages.push(i);
      }
      return pages;
    }
  },

  watch: {
    categoryId() {
      this.resetPagination();
      this.debouncedSearch();
    },
    brandId() {
      this.resetPagination();
      this.debouncedSearch();
    },
    query() {
      this.debouncedSearch();
    }
  },

  methods: {
    resetPagination() {
      this.meta = null;
    },
    debouncedSearch() {
      clearTimeout(this.t);
      this.t = setTimeout(() => {
        this.resetPagination();
        this.search(1);
      }, 300);
    },
    async search(page = 1) {
      if (!this.categoryId || !this.brandId) {
        this.options = [];
        this.meta = null;
        return;
      }

      this.loading = true;
      try {
        const result = await this.fetcher({
          categoryId: this.categoryId,
          brandId: this.brandId,
          q: this.query || undefined,
          page
        });

        if (Array.isArray(result)) {
          this.options = result;
          this.meta = null;
        } else if (result && Array.isArray(result.items)) {
          this.options = result.items;

          this.meta = result.meta || null;
        } else {
          this.options = [];
          this.meta = null;
        }
      } catch (e) {
        console.error(e);
        this.options = [];
        this.meta = null;
      } finally {
        this.loading = false;
      }
    },
    loadPage(page) {
      if (page < 1 || page > this.totalPages) return;
      this.search(page);
    },
    select(opt) {
      this.$emit("update:modelValue", opt.id);
    },
    clear() {
      this.$emit("update:modelValue", null);
      this.query = "";
      this.options = [];
      this.meta = null;
    }
  },
  mounted() {
    this.search();
  }
};
</script>

<style scoped>
.pagination {
  margin: 10px 0;
  display: flex;
  align-items: center;
  gap: 6px;
}

.pagination button {
  padding: 4px 8px;
  border: 1px solid #ccc;
  background: #fff;
  cursor: pointer;
  border-radius: 4px;
}

.pagination button.active {
  background: #007bff;
  color: white;
  font-weight: bold;
  border-color: #007bff;
}

.pagination button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>