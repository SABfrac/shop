<template>
  <div>
    <h2>Мои предложения</h2>

    <!-- просмотр списка -->
    <table>
      <thead>
      <tr>
        <th>Product  </th><th>Price  </th><th>Stock  </th><th>Condition  </th><th>SKU  </th><th>  Status</th>
      </tr>
      </thead>
      <tbody>
      <tr v-for="o in offers" :key="o.id">
        <td>{{ o.product.name }}</td>
        <td><input v-model.number="o.price"/></td>
        <td><input v-model.number="o.stock"/></td>
        <td>{{ o.condition }}</td>
        <td>{{ o.warranty }}</td>
        <td><input type="checkbox" v-model="o.status"/></td>
        <td><button @click="saveOffer(o)">Сохранить</button></td>
      </tr>
      </tbody>
    </table>

    <!-- добавить новый оффер -->
    <h3>Новый оффер</h3>
    <form @submit.prevent="addOffer">
      <input v-model="newOffer.product_id" placeholder="Product ID"/>
      <input v-model.number="newOffer.price" placeholder="Цена" type="number"/>
      <input v-model.number="newOffer.stock" placeholder="Stock" type="number"/>
      <input v-model="newOffer.sku" placeholder="Артикул"/>
      <select v-model="newOffer.condition">
        <option value="new">New</option>
        <option value="used">Used</option>
        <option value="discounted">Discounted</option>
      </select>
      <input v-model.number="newOffer.warranty" placeholder="Гарантия" type="number"/>
      <button type="submit" >Добавить</button>
    </form>
  </div>
</template>

<script>

import {  getOffers,
  updateOffer,
  createOffer,
  deleteOffer  } from '@/services/api/offers'


export default {
  data() {
    return {
      offers: [],
      newOffer: {product_id: "", price: 0, stock: 0, condition: "new",warranty:0, sku: "", status: true}
    }
  },
  async created() {
    try {
    const {data} = await getOffers({params: {vendor_id: 101}});
    this.offers = data;
    } catch (error) {
      console.error('Ошибка при загрузке предложений:', error);

    }
  },
  methods: {
    async saveOffer(o) {
      try {
        await updateOffer(o.id, o);
        alert("Предложение обновлено");
      } catch (error) {
        console.error('Ошибка при обновлении предложения:', error);
        alert("Ошибка при обновлении оффера");
      }
    },
    async addOffer() {
      try {
        await createOffer(this.newOffer);
        alert("Предложение создан");


        // Обновляем список предложений
        const {data} = await getOffers({params: {vendor_id: 101}});
        this.offers = data;

        // Сбрасываем форму
        this.newOffer = {
          product_id: "",
          price: 0,
          stock: 0,
          condition: "new",
          warranty:0,
          sku: "",
          status: true
        };
      } catch (error) {
        console.error('Ошибка при создании предложения:', error);
        alert("Ошибка при создании предложения");
      }
    }
  }
}
</script>