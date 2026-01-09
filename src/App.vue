<script setup>
import { ref, computed, onMounted } from 'vue';
import Header from './components/Header.vue';
import Footer from './components/Footer.vue';
import { useProducts } from './composables/useProducts';
import { useCart } from './composables/useCart';

// --- INITIALISIERUNG ---
const { products, error, updateProductStock, fetchProducts } = useProducts();
const { 
  cart, addToCart, removeFromCart, updateCartQty, 
  cartTotal, vatAmount, totalItems 
} = useCart();

const currentView = ref('catalog');
const user = ref(null);
const loginInput = ref({ username: '', password: '' });
const message = ref('');
const searchQuery = ref('');
const adminOrders = ref([]);

// --- KUNDENDATEN ---
const customer = ref({
  vorname: '',
  nachname: '',
  email: '',
  strasse: '',
  ort: '',
  plz: '', 
  alter: null,
  interesse: 5,
  nachricht: ''
});

// --- VALIDIERUNG (DIAGNOSE MODUS) ---
const validationStatus = computed(() => {
  const c = customer.value;
  // Wir wandeln alles sicher in Strings um
  const vn = String(c.vorname || '').trim();
  const nn = String(c.nachname || '').trim();
  const mail = String(c.email || '').trim();
  const p = String(c.plz || '').trim();

  return {
    vorname: vn.length >= 2, // Mind. 2 Zeichen
    nachname: nn.length >= 2, // Mind. 2 Zeichen
    email: mail.includes('@'), // Muss @ enthalten (Punkt optional für localhost)
    plz: /^\d{5}$/.test(p)     // Exakt 5 Ziffern (00000 bis 99999)
  };
});

const isFormValid = computed(() => {
  const s = validationStatus.value;
  return s.vorname && s.nachname && s.email && s.plz;
});

// --- SUCHE ---
const filteredProducts = computed(() => {
  if (!searchQuery.value) return products.value;
  const term = searchQuery.value.toLowerCase();
  return products.value.filter(p => p.title.toLowerCase().includes(term));
});

const totalWithVat = computed(() => cartTotal.value);

// --- HANDLER ---
const handleAddToCart = (product) => {
  const result = addToCart(product);
  if (result === "ausverkauft") message.value = "⚠️ Artikel ist ausverkauft!";
  else if (result === "limit_reached") message.value = "⚠️ Lagerbestand reicht nicht.";
  else {
    message.value = `✅ ${product.title} eingepackt!`;
    setTimeout(() => message.value = '', 2000);
  }
};

const handleCartQtyChange = (item, change) => {
  if (!updateCartQty(item, change)) {
    message.value = "⚠️ Lagerbestand reicht nicht!";
    setTimeout(() => message.value = '', 2000);
  }
};

const fetchOrders = async () => {
  try {
    const res = await fetch(import.meta.env.VITE_API_BASE_URL + 'get_orders.php');
    adminOrders.value = await res.json();
  } catch(e) { console.error(e); }
};

const handleLogin = () => {
  if (loginInput.value.username === 'admin' && loginInput.value.password === 'adm24') {
    user.value = { name: 'Admin', role: 'admin' };
    currentView.value = 'admin';
    message.value = "🔓 Hallo Admin.";
    fetchOrders();
  } else {
    message.value = "❌ Falsches Passwort!";
  }
};

const handleUpdateAllStocks = async () => {
  message.value = "💾 Speichere...";
  let err = 0;
  for (const p of products.value) {
    if (!await updateProductStock(p)) err++;
  }
  message.value = err === 0 ? "✅ Alles gespeichert!" : "⚠️ Probleme beim Speichern.";
};

// --- CHECKOUT & DEBUGGING ---
const handleCheckout = async () => {
  if (cart.value.length === 0) return;

  // DEBUGGING: Ausgabe in der Konsole (F12)
  console.log("--- DEBUG START ---");
  console.log("Vorname ok?", validationStatus.value.vorname, `('${customer.value.vorname}')`);
  console.log("Nachname ok?", validationStatus.value.nachname, `('${customer.value.nachname}')`);
  console.log("Email ok?", validationStatus.value.email, `('${customer.value.email}')`);
  console.log("PLZ ok?", validationStatus.value.plz, `('${customer.value.plz}') - Muss genau 5 Zahlen sein!`);
  console.log("--- DEBUG ENDE ---");

  // Wenn Formular ungültig -> Abbrechen mit Nachricht
  if (!isFormValid.value) {
    message.value = "⚠️ Formular fehlerhaft! Siehe rote Felder oder Konsole (F12).";
    return;
  }

  message.value = "🔄 Verbinde mit Stripe...";
  
  try {
    const baseUrl = import.meta.env.VITE_API_BASE_URL;
    const res = await fetch(baseUrl + 'checkout.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ cart: cart.value, customer: customer.value })
    });

    if (!res.ok) {
       const txt = await res.text();
       console.error("Server Antwort:", txt);
       throw new Error("Server Fehler (siehe Konsole)");
    }

    const data = await res.json();
    console.log("Stripe Antwort:", data);

    if (data.id) {
      // HIER DEINEN PUBLIC KEY PRÜFEN
      const stripe = Stripe('pk_test_51Sf50LPfKVgT4yvvJzL4Gz4DHUbhx2BHryUUj4JOF3gK4dpJnU0DKC2P3K6DTxNng9S4dcFEBu9uIZAf5hBpBySG00ILbYwXP4');
      const result = await stripe.redirectToCheckout({ sessionId: data.id });
      if (result.error) message.value = result.error.message;
    } else {
      message.value = "Fehler: Keine Session ID von Stripe erhalten.";
    }

  } catch (e) {
    console.error(e);
    message.value = "Fehler: " + e.message;
  }
};

onMounted(() => {
  fetchProducts();
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('status') === 'success') {
    message.value = "🎉 Danke für den Einkauf!";
    cart.value = [];
    window.history.replaceState({}, document.title, window.location.pathname);
  }
});
</script>

<template>
  <Header 
    :cart-count="totalItems"
    :is-logged-in="user !== null"
    @navigate="v => currentView = v"
    @logout="() => { user = null; currentView = 'catalog'; }"
  />

  <main class="container">
    <div v-if="message" class="alert-box">{{ message }}<span class="close-btn" @click="message = ''">×</span></div>

    <!-- KATALOG -->
    <div id="page-catalog" v-if="currentView === 'catalog'">
      <h2 class="section-title">🎄 Weihnachts-Angebote</h2>
      <div class="search-container">
        <input v-model="searchQuery" type="text" class="search-input" placeholder="🔍 Geschenke suchen...">
      </div>
      <div class="product-grid">
        <div v-for="product in filteredProducts" :key="product.id" class="card">
          <div class="card-img-container"><img :src="product.image_url" alt="Produktbild"></div>
          <div class="card-body">
            <h3 class="product-title">{{ product.title }}</h3>
            <p class="price">{{ product.price.toFixed(2) }} €</p>
            <p class="stock-info" :class="{'low-stock': product.stock < 5}">Lager: {{ product.stock }}</p>
            <button @click="handleAddToCart(product)" class="btn btn-primary">In den Korb</button>
          </div>
        </div>
      </div>
    </div>

    <!-- WARENKORB -->
    <div id="page-cart" v-if="currentView === 'cart'">
      <h2 class="section-title">🛒 Dein Warenkorb</h2>
      <div v-if="cart.length === 0" class="empty-cart">
        <p>Noch leer.</p>
        <button @click="currentView = 'catalog'" class="btn btn-secondary">Zum Shop</button>
      </div>
      <div v-else class="cart-wrapper">
        <table class="cart-table">
          <thead><tr><th>Artikel</th><th>Menge</th><th>Preis</th><th>Aktion</th></tr></thead>
          <tbody>
            <tr v-for="(item, index) in cart" :key="item.id">
              <td>{{ item.title }}</td>
              <td>
                <div class="qty-controls">
                  <button @click="handleCartQtyChange(item, -1)" class="btn-mini">-</button>
                  <span class="qty">{{ item.qty }}</span>
                  <button @click="handleCartQtyChange(item, 1)" class="btn-mini">+</button>
                </div>
              </td>
              <td>{{ (item.price * item.qty).toFixed(2) }} €</td>
              <td><button @click="removeFromCart(index)" class="btn-danger">Entfernen</button></td>
            </tr>
          </tbody>
        </table>

        <!-- FORMULAR -->
        <div class="registration-form card">
          <h3>📝 Lieferdaten</h3>
          <div class="form-grid">
            <div class="form-group">
              <label>Vorname*</label>
              <input v-model="customer.vorname" :class="{invalid: !validationStatus.vorname}" type="text" placeholder="Min. 2 Zeichen">
            </div>
            <div class="form-group">
              <label>Nachname*</label>
              <input v-model="customer.nachname" :class="{invalid: !validationStatus.nachname}" type="text" placeholder="Min. 2 Zeichen">
            </div>
            <div class="form-group full-width">
              <label>Email*</label>
              <input v-model="customer.email" :class="{invalid: !validationStatus.email}" type="email" placeholder="Muss @ enthalten">
            </div>
            <div class="form-group full-width"><label>Straße</label><input v-model="customer.strasse" type="text"></div>
            <div class="form-group">
              <label>PLZ* (5 Zahlen)</label>
              <input v-model="customer.plz" :class="{invalid: !validationStatus.plz}" type="text" maxlength="5" placeholder="12345">
            </div>
            <div class="form-group"><label>Ort</label><input v-model="customer.ort" type="text"></div>
            <div class="form-group"><label>Alter</label><input v-model="customer.alter" type="number" min="3" max="120"></div>
            <div class="form-group full-width">
              <label>Interesse (0-10): {{ customer.interesse }}</label>
              <input v-model="customer.interesse" type="range" min="0" max="10">
            </div>
            <div class="form-group full-width"><textarea v-model="customer.nachricht" rows="3" placeholder="Anmerkung..."></textarea></div>
          </div>
        </div>

        <div id="cart-summary" class="cart-summary">
          <h3>Summe</h3>
          <p>Anzahl: {{ totalItems }}</p>
          <p class="vat-info">MwSt (7%): {{ vatAmount.toFixed(2) }} €</p>
          <hr>
          <p class="total-price">Gesamt: {{ totalWithVat.toFixed(2) }} €</p>
          
          <!-- BUTTON JETZT IMMER KLICKBAR -->
          <button @click="handleCheckout" class="btn btn-checkout">
            {{ isFormValid ? 'Zur Kasse (Stripe)' : 'Formular prüfen!' }}
          </button>
          
          <!-- HILFE TEXT -->
          <div v-if="!isFormValid" style="color: red; font-size: 0.8rem; margin-top: 10px;">
            <span v-if="!validationStatus.vorname">Vorname?, </span>
            <span v-if="!validationStatus.nachname">Nachname?, </span>
            <span v-if="!validationStatus.email">Email (@)?, </span>
            <span v-if="!validationStatus.plz">PLZ (5 Zahlen)? </span>
          </div>
        </div>
      </div>
    </div>

    <!-- LOGIN & ADMIN -->
    <div id="page-login" v-if="currentView === 'login'">
      <div class="login-card">
        <h2>🔒 Admin Login</h2>
        <input v-model="loginInput.username" placeholder="User" class="login-input">
        <input v-model="loginInput.password" type="password" placeholder="Pass" class="login-input">
        <button @click="handleLogin" class="btn btn-primary w-100">Einloggen</button>
      </div>
    </div>

    <div id="page-admin" v-if="currentView === 'admin'">
      <h2 class="section-title">⚙️ Admin</h2>
      <table class="admin-table">
        <thead><tr><th>Produkt</th><th>Preis</th><th>Bestand</th></tr></thead>
        <tbody>
          <tr v-for="p in products" :key="p.id">
            <td>{{ p.title }}</td><td>{{ p.price }} €</td>
            <td><input type="number" v-model="p.stock" class="stock-input"><button @click="updateProductStock(p)" class="btn-mini">💾</button></td>
          </tr>
        </tbody>
      </table>
      <button @click="handleUpdateAllStocks" class="btn btn-warning mt-2">Alle speichern</button>
      <hr style="margin: 40px 0;">
      <h3>📦 Bestellungen</h3>
      <button @click="fetchOrders" class="btn btn-secondary">Aktualisieren</button>
      <table class="admin-table mt-2">
        <thead><tr><th>ID</th><th>Datum</th><th>Kunde</th><th>Summe</th></tr></thead>
        <tbody>
          <tr v-for="order in adminOrders" :key="order.id">
            <td>#{{ order.id }}</td><td>{{ order.created_at }}</td>
            <td><b>{{ order.customer_data.vorname }} {{ order.customer_data.nachname }}</b><br><small>{{ order.customer_data.email }}</small></td>
            <td>{{ order.total_price }} €</td>
          </tr>
        </tbody>
      </table>
    </div>
  </main>
  <Footer />
</template>

<style>
/* ... (Das CSS kannst du exakt so lassen wie im vorherigen Beispiel) ... */
:root { --primary: #2c3e50; --accent: #e67e22; --bg: #f8f9fa; }
body { font-family: 'Open Sans', sans-serif; background: var(--bg); margin: 0; color: #333; }
.container { max-width: 1000px; margin: 30px auto; padding: 0 20px; min-height: 80vh; }
.section-title, .catalog-title, h2 { font-family: 'Mountains of Christmas', cursive; color: var(--primary); }
.product-title, h3 { font-family: 'Open Sans', sans-serif; font-weight: bold; }
.section-title { text-align: center; font-size: 2.5rem; margin-bottom: 30px; }
.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
.card { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.card-img-container { height: 200px; width: 100%; border-bottom: 1px solid #eee; }
.card img { width: 100%; height: 100%; object-fit: cover; }
.card-body { padding: 15px; text-align: center; }
input, textarea { color: #000 !important; background: #fff !important; border: 1px solid #ccc; padding: 10px; border-radius: 5px; width: 100%; box-sizing: border-box; }
input.invalid { border-color: red; background-color: #fff0f0 !important; }
.search-input { max-width: 400px; display: block; margin: 0 auto 20px; border-radius: 20px; }
.cart-wrapper { display: flex; flex-direction: column; gap: 20px; }
.cart-table { width: 100%; background: #fff; border-radius: 8px; border-collapse: collapse; }
.cart-table th, .cart-table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
.qty-controls { display: flex; align-items: center; gap: 5px; }
.registration-form { padding: 20px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.full-width { grid-column: span 2; }
.form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.9rem; }
.btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; color: white; transition: 0.2s; }
.btn-primary { background: var(--primary); }
.btn-secondary { background: #95a5a6; }
.btn-danger { background: #e74c3c; }
.btn-warning { background: var(--accent); }
.btn-checkout { background: #27ae60; width: 100%; font-size: 1.1rem; margin-top: 10px; }
.btn-mini { background: #eee; color: #333; width: 30px; height: 30px; border-radius: 50%; border: 1px solid #ccc; cursor: pointer; }
.admin-table { width: 100%; background: #fff; border-collapse: collapse; }
.admin-table th, .admin-table td { border: 1px solid #ddd; padding: 8px; }
.stock-input { width: 70px; margin-right: 5px; }
.alert-box { background: #333; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; }
</style>