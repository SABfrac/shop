<template>
  <div class="brand-selector">
    <label v-if="label">{{ label }}</label>

    <!-- Input для поиска -->
    <input
        v-if="enableSearch"
        v-model="searchQuery"
        type="text"
        :placeholder="searchPlaceholder"
        @input="debouncedFetchBrands($event.target.value)"
        :disabled="disabled || loading"
        class="search-input"
    />

    <!-- Выпадающий список -->
    <select
        :value="selected"
        @change="onSelectChange"
        :disabled="disabled || loading"
        v-show="!enableSearch || (enableSearch && !searchQuery)"
    >
      <option value="" disabled>{{ placeholder }}</option>
      <option v-for="b in brands" :key="b.id" :value="b.id">
        {{ b.name }}
      </option>
      <option v-if="allowCreate" value="__add">{{ addOptionText }}</option>
    </select>

    <!-- Выпадающий список для результатов поиска -->
    <select
        v-if="enableSearch && searchQuery"
        :value="selected"
        @change="onSelectChange"
        :disabled="disabled || loading"
    >
      <option value="" disabled>{{ searchResultsPlaceholder }}</option>
      <option v-for="b in brands" :key="b.id" :value="b.id">
        {{ b.name }}
      </option>
      <option v-if="allowCreate" value="__add">{{ addOptionText }}</option>
    </select>

    <small v-if="error" style="color:#d00">Не удалось загрузить бренды</small>

    <!-- Форма добавления бренда -->
    <div v-if="allowCreate && showAddForm" class="brand-add-form">
      <h4>Добавить бренд</h4>

      <div class="form-row">
        <label>Название*</label>
        <input v-model.trim="newBrand.name" type="text" placeholder="Например, Acme" />
      </div>

      <div class="form-row">
        <label>Описание</label>
        <textarea v-model.trim="newBrand.description" rows="3" />
      </div>

      <div class="form-row">
        <label>Логотип</label>
        <input type="file" accept="image/*" @change="onLogoChange" />
      </div>

      <div class="form-row">
        <label>Статус</label>
        <select v-model.number="newBrand.status">
          <option :value="1">Активный</option>
          <option :value="0">Черновик / на модерации</option>
          <option :value="-1">Архив</option>
        </select>
      </div>

      <div class="actions">
        <button @click="saveBrand" :disabled="saving || !isBrandValid">Сохранить</button>
        <button @click="cancelAddBrand" :disabled="saving">Отмена</button>
      </div>

      <small v-if="createError" style="color:#d00">{{ createError }}</small>
    </div>
  </div>
</template>

<script>
// Простая реализация debounce (если не хотите подключать lodash)
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

export default {
  name: "BrandSelector",
  props: {
    // стандартный v-model
    modelValue: {type: [Number, String, null], default: null},
    // для обратной совместимости с v-model:value
    value: {type: [Number, String, null], default: undefined},

    categoryId: {type: [Number, String, null], default: null},

    // Инъекции API
    //  fetcher(categoryId, search) -> Promise<Array<{id,name}>> (обязателен)
    fetcher: {type: Function, required: true}, // <-- Теперь ожидается, что он принимает search
    // creator(formData) -> Promise<{id,name,...}> (опционально)
    creator: {type: Function, default: null},
    // attacher(brandId, categoryId) -> Promise<void> (опционально)
    attacher: {type: Function, default: null},

    // UI/поведение
    allowCreate: {type: Boolean, default: true},
    disabled: {type: Boolean, default: false},
    label: {type: String, default: "Бренд"},
    placeholder: {type: String, default: "Выберите бренд"},
    addOptionText: {type: String, default: "+ Добавить новый бренд…"},
    autoSelectCreated: {type: Boolean, default: true},
    // Новый пропс для включения поиска
    enableSearch: {type: Boolean, default: false},
    // Новые пропсы для placeholder'ов
    searchPlaceholder: {type: String, default: "Поиск бренда..."},
    searchResultsPlaceholder: {type: String, default: "Результаты поиска..."}
  },
  emits: [
    "update:modelValue",
    "update:value",          // для v-model:value
    "brand-selected",
    "created",
    "error",
    "loaded"
  ],
  data() {
    return {
      brands: [],
      selected: this.value !== undefined ? this.value : this.modelValue,
      prevSelected: this.value !== undefined ? this.value : this.modelValue,

      error: null,
      loading: false,

      showAddForm: false,
      saving: false,
      createError: null,
      newBrand: {
        name: "",
        description: "",
        logoFile: null,
        status: 1
      },
      // Новое состояние для поиска
      searchQuery: ""
    };
  },
  computed: {
    isBrandValid() {
      return (this.newBrand.name || "").length >= 2;
    }
  },
  created() {
    // Создаём debounced версию fetchBrands
    this.debouncedFetchBrands = debounce(this.internalFetchBrands, 300);
  },
  methods: {
    // публичный метод (можно вызвать через ref)
    async reload() {
      await this.internalFetchBrands();
    },

    // Внутренний метод для загрузки брендов, теперь принимает search
    async internalFetchBrands(search = null) {
      // Используем categoryId из props и search из аргумента или локального состояния
      const categoryId = this.categoryId;
      if (typeof this.fetcher !== "function") {
        console.error('[BrandSelector] prop "fetcher" must be a function');
        this.error = "Некорректный fetcher";
        return;
      }
      if (!categoryId) {
        this.brands = [];
        return;
      }
      this.loading = true;
      try {
        // Вызываем переданный fetcher с categoryId и search
        const res = await this.fetcher(categoryId, search);
        const list = Array.isArray(res) ? res : (Array.isArray(res?.data) ? res.data : []);
        this.brands = list || [];
        this.error = null;

        this.$emit("loaded", this.brands);
      } catch (e) {
        this.error = e;
        this.$emit("error", e);
        console.error("Ошибка загрузки брендов", e);
      } finally {
        this.loading = false;
      }
    },

    onSelectChange(evt) {
      const val = evt.target.value;
      if (val === "__add") {
        if (!this.allowCreate) return;
        this.openAddBrandForm();
        // вернуть визуально предыдущий выбор
        evt.target.value = this.prevSelected == null ? "" : String(this.prevSelected);
        return;
      }
      const normalized = /^\d+$/.test(val) ? Number(val) : (val || null);
      this.selected = normalized;
      this.prevSelected = normalized;
      this.emitSelected();
    },

    emitSelected() {
      this.$emit("brand-selected", this.selected);
      // поддержим оба паттерна v-model
      this.$emit("update:modelValue", this.selected);
      this.$emit("update:value", this.selected);
    },

    openAddBrandForm() {
      if (!this.allowCreate) return;
      this.showAddForm = true;
      this.createError = null;
    },
    cancelAddBrand() {
      this.showAddForm = false;
      this.createError = null;
      this.newBrand = {name: "", description: "", logoFile: null, status: 1};
    },
    onLogoChange(e) {
      const file = e.target.files && e.target.files[0];
      this.newBrand.logoFile = file || null;
    },

    async saveBrand() {
      if (typeof this.creator !== "function") {
        this.createError = "Создание бренда не настроено";
        return;
      }

      this.saving = true;
      this.createError = null;
      try {
        const fd = new FormData();
        fd.append("name", this.newBrand.name);
        if (this.newBrand.description) fd.append("description", this.newBrand.description);
        fd.append("status", String(this.newBrand.status));
        if (this.newBrand.logoFile) fd.append("logo", this.newBrand.logoFile);

        if (this.categoryId != null && this.categoryId !== "") {
          fd.append("category_id", String(this.categoryId));
        }

        const createdRaw = await this.creator(fd);
        const created = createdRaw?.data && createdRaw.data.id ? createdRaw.data : createdRaw;

        if (!created?.id) throw new Error("Некорректный ответ при создании бренда");

        // привязка к категории при наличии attacher
        if (this.categoryId && typeof this.attacher === "function") {
          await this.attacher(created.id, this.categoryId);
        }

        // Обновим список и выбор
        this.brands.unshift(created);
        if (this.autoSelectCreated) {
          this.selected = created.id;
          this.prevSelected = created.id;
          this.emitSelected();
        }

        this.$emit("created", created);
        this.cancelAddBrand();
      } catch (e) {
        console.error("Ошибка создания бренда", e);
        this.createError =
            e?.response?.data?.message ||
            (Array.isArray(e?.response?.data) ? e.response.data.join(", ") : "") ||
            "Не удалось создать бренд";
      } finally {
        this.saving = false;
      }
    }
  },
  watch: {
    // подхватываем внешнее изменение v-model (любой из паттернов)
    value(v) {
      if (v !== undefined && v !== this.selected) {
        this.selected = v;
        this.prevSelected = v;
      }
    },
    modelValue(v) {
      if (v !== this.selected) {
        this.selected = v;
        this.prevSelected = v;
      }
    },
    // как только выбрана категория — грузим бренды по ней
    categoryId: {
      immediate: true, // Оставляем immediate: true, чтобы грузить при монтировании
      handler(id) {
        if (!id) {
          this.brands = [];
          // сбросим выбор, если он был
          if (this.selected) {
            this.selected = null;
            this.prevSelected = null;
            this.emitSelected();
          }
          return;
        }
        // Вызываем внутреннюю функцию с текущим searchQuery
        this.internalFetchBrands(this.searchQuery).then(() => {
          // если ранее выбранный бренд не принадлежит новой категории — сбросим выбор
          if (this.selected && !this.brands.some(b => b.id === this.selected)) {
            this.selected = null;
            this.prevSelected = null;
            this.emitSelected();
          }
        });
      }
    },
    // Новый watcher для searchQuery
    searchQuery(newVal) {
      if (this.enableSearch) {
        // Вызываем debounced версию, передавая текущий searchQuery
        this.debouncedFetchBrands(newVal);
      }
    }
  }
};

</script>

<style scoped>
.brand-selector {
  .brand-add-form {
    margin-top: 8px;
    padding: 10px;
    border: 1px dashed #ccc;
    border-radius: 6px;
  }

  .form-row {
    margin-bottom: 8px;
  }

  .brand-add-form label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-bottom: 4px;
  }

  .brand-add-form input[type="text"],
  .brand-add-form textarea,
  .brand-add-form select {
    width: 100%;
  }

  .actions {
    display: flex;
    gap: 8px;
    margin-top: 8px;
  }
}
.search-input {
  width: 100%;
  padding: 8px;
  margin-bottom: 5px;
  box-sizing: border-box;
}

.brand-add-form {
  margin-top: 10px;
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 4px;
}

.form-row {
  margin-bottom: 10px;
}

.form-row label {
  display: block;
  margin-bottom: 4px;
}

.actions {
  margin-top: 10px;
}

.actions button {
  margin-right: 10px;
}
</style>



