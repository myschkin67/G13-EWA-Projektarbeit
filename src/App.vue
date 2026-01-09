<script setup>
import { ref, computed, onMounted } from 'vue';
import Header from './components/Header.vue';
import Footer from './components/Footer.vue';
import { useProducts } from './composables/useProducts';
import { useCart } from './composables/useCart';

// --- INITIALISIERUNG ---
// hier holen wir uns die logik aus den anderen dateien rein
const { products, error, updateProductStock, fetchProducts } = useProducts();
const { 
  cart, addToCart, removeFromCart, updateCartQty, 
  cartTotal, vatAmount, totalItems 
} = useCart();

// variablen für die oberfläche
const currentView = ref('catalog'); // steuert welche seite man sieht (spa)
const user = ref(null);
const loginInput = ref({ username: '', password: '' });
const message = ref('');
const searchQuery = ref('');
const adminOrders = ref([]); // hier kommen die bestellungen rein

// --- KUNDENDATEN FORMULAR ---
// die felder aus der html5 aufgabe
const customer = ref({
  vorname: '',
  nachname: '',
  email: '',
  strasse: '',
  ort: '',
  plz: '', 
  alter: null,
  interesse: 5, // range slider startwert
  nachricht: ''
});

// validierung (damit kein quatsch abgeschickt wird)
const isFormValid = computed(() => {
  // plz muss genau 5 zahlen haben
  const plzValid = /^\d{5}$/.test(customer.value.plz);
  
  // check ob alles ausgefüllt ist
  return customer.value.vorname.length >= 2 &&
         customer.value.nachname.length >= 2 &&
         customer.value.email.includes('@') &&
         plzValid;
});

// --- SUCHE ---
// filtert die produktliste live
const filteredProducts = computed(() => {
  if (!searchQuery.value) return products.value;
  const term = searchQuery.value.toLowerCase();
  return products.value.filter(p => p.title.toLowerCase().includes(term));
});

const totalWithVat = computed(() => cartTotal.value);

// --- CLICK HANDLER ---

// fügt produkt hinzu und zeigt nachricht an
const handleAddToCart = (product) => {
  const result = addToCart(product);
  if (result === "ausverkauft") {
    message.value = "⚠️ artikel ist leider weg!";
  } else if (result === "limit_reached") {
    message.value = "⚠️ mehr haben wir davon nicht auf lager.";
  } else {
    message.value = `✅ ${product.title} eingepackt!`;
    setTimeout(() => message.value = '', 2000);
  }
};

const handleCartQtyChange = (item, change) => {
  const success = updateCartQty(item, change);
  if (!success) {
    message.value = "⚠️ lagerbestand reicht nicht!";
    setTimeout(() => message.value = '', 2000);
  }
};

// admin: bestellungen laden
const fetchOrders = async () => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL;
  try {
    const res = await fetch(baseUrl + 'get_orders.php');
    adminOrders.value = await res.json();
  } catch(e) {
    console.error("fehler beim laden der bestellungen", e);
  }
};

// login logik (hardcoded wie in aufgabe verlangt)
const handleLogin = () => {
  if (loginInput.value.username === 'admin' && loginInput.value.password === 'adm24') {
    user.value = { name: 'Admin', role: 'admin' };
    currentView.value = 'admin';
    message.value = "🔓 hallo admin.";
    loginInput.value = { username: '', password: '' };
    fetchOrders(); // direkt liste laden
  } else {
    message.value = "❌ falsches passwort! (tipp: admin / adm24)";
  }
};

const handleUpdateAllStocks = async () => {
  message.value = "💾 speichere bestände in db...";
  let errorCount = 0;
  for (const p of products.value) {
    const success = await updateProductStock(p);
    if (!success) errorCount++;
  }
  if (errorCount === 0) {
    message.value = "✅ alles gespeichert!";
  } else {
    message.value = "⚠️ bei ein paar artikeln gabs probleme.";
  }
};

// --- CHECKOUT ---
const handleCheckout = async () => {
  if (cart.value.length === 0) return;
  
  if (!isFormValid.value) {
    message.value = "⚠️ formular nicht korrekt ausgefüllt (plz 5 stellen, name min 2)!";
    return;
  }

  message.value = "🔄 verbinde mit stripe...";
  
  try {
    const baseUrl = import.meta.env.VITE_API_BASE_URL;
    
    // wir schicken warenkorb UND kundendaten an php
    const res = await fetch(baseUrl + 'checkout.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        cart: cart.value,
        customer: customer.value 
      })
    });

    if (!res.ok) {
       const errData = await res.json();
       throw new Error(errData.error || "server fehler");
    }

    const data = await res.json();

    // wenn alles ok ist leitet stripe uns weiter
    if (data.id) {
      const stripe = Stripe('pk_test_51Sf50LPfKVgT4yvvJzL4Gz4DHUbhx2BHryUUj4JOF3gK4dpJnU0DKC2P3K6DTxNng9S4dcFEBu9uIZAf5hBpBySG00ILbYwXP4');
      const result = await stripe.redirectToCheckout({ sessionId: data.id });
      if (result.error) message.value = result.error.message;
    }

  } catch (e) {
    message.value = "fehler: " + e.message;
  }
};

// beim starten der app
onMounted(() => {
  fetchProducts();
  // schauen ob wir von stripe zurückkommen
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('status') === 'success') {
    message.value = "🎉 danke für den einkauf! bestellung ist gespeichert.";
    cart.value = []; // korb leeren
    // url aufräumen damit das success weggeht
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
    
    <!-- nachrichten box -->
    <div v-if="message" class="alert-box">
      {{ message }}
      <span class="close-btn" @click="message = ''">×</span>
    </div>

    <!-- === KATALOG SEITE === -->
    <div id="page-catalog" v-if="currentView === 'catalog'">
      <h2 class="section-title">🎄 Weihnachts-Angebote</h2>
      
      <div class="search-container">
        <input v-model="searchQuery" type="text" class="search-input" placeholder="🔍 Geschenke suchen...">
      </div>

      <div class="product-grid">
        <div v-for="product in filteredProducts" :key="product.id" class="card">
          <div class="card-img-container">
            <img :src="product.image_url" alt="Produktbild">
          </div>
          <div class="card-body">
            <h3>{{ product.title }}</h3>
            <p class="price">{{ product.price.toFixed(2) }} €</p>
            <p class="stock-info" :class="{'low-stock': product.stock < 5}">
              Lager: {{ product.stock }} Stück
            </p>
            <button @click="handleAddToCart(product)" class="btn btn-primary">In den Korb</button>
          </div>
        </div>
      </div>
    </div>

    <!-- === WARENKORB SEITE === -->
    <div id="page-cart" v-if="currentView === 'cart'">
      <h2 class="section-title">🛒 Dein Warenkorb</h2>
      
      <div v-if="cart.length === 0" class="empty-cart">
        <p>noch nichts drin.</p>
        <button @click="currentView = 'catalog'" class="btn btn-secondary">Zum Shop</button>
      </div>

      <div v-else class="cart-wrapper">
        <!-- tabelle mit artikeln -->
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

        <!-- formular für adresse -->
        <div class="registration-form card">
          <h3>📝 Lieferdaten eingeben</h3>
          <div class="form-grid">
            <div class="form-group">
              <label>Vorname*</label>
              <input v-model="customer.vorname" type="text" required minlength="2">
            </div>
            <div class="form-group">
              <label>Nachname*</label>
              <input v-model="customer.nachname" type="text" required minlength="2">
            </div>
            <div class="form-group full-width">
              <label>Email*</label>
              <input v-model="customer.email" type="email" required>
            </div>
            <div class="form-group full-width">
              <label>Straße</label>
              <input v-model="customer.strasse" type="text" maxlength="50">
            </div>
            <div class="form-group">
              <label>PLZ* (5 Stellen)</label>
              <input v-model="customer.plz" type="text" pattern="\d{5}" maxlength="5" placeholder="01234">
            </div>
            <div class="form-group">
              <label>Ort</label>
              <input v-model="customer.ort" type="text" maxlength="50">
            </div>
            <div class="form-group">
              <label>Alter</label>
              <input v-model="customer.alter" type="number" min="3" max="120">
            </div>
            <div class="form-group full-width">
              <label>Interesse (0-10): {{ customer.interesse }}</label>
              <input v-model="customer.interesse" type="range" min="0" max="10">
            </div>
            <div class="form-group full-width">
              <textarea v-model="customer.nachricht" rows="3" placeholder="Ihre Anfrage an uns..."></textarea>
            </div>
          </div>
        </div>

        <!-- summe -->
        <div id="cart-summary" class="cart-summary">
          <h3>Summe</h3>
          <p>Anzahl: {{ totalItems }}</p>
          <p class="vat-info">darin MwSt. (7%): {{ vatAmount.toFixed(2) }} €</p>
          <hr>
          <p class="total-price">Gesamt: {{ totalWithVat.toFixed(2) }} €</p>
          
          <button 
            @click="handleCheckout" 
            class="btn btn-checkout" 
            :disabled="!isFormValid"
            :style="{ opacity: isFormValid ? 1 : 0.5 }"
          >
            {{ isFormValid ? 'Zur Kasse (Stripe)' : 'Bitte Formular ausfüllen' }}
          </button>
        </div>
      </div>
    </div>

    <!-- === LOGIN === -->
    <div id="page-login" v-if="currentView === 'login'">
      <div class="login-card">
        <h2>🔒 Admin Login</h2>
        <input v-model="loginInput.username" placeholder="User" class="login-input">
        <input v-model="loginInput.password" type="password" placeholder="Pass" class="login-input">
        <button @click="handleLogin" class="btn btn-primary w-100">Einloggen</button>
      </div>
    </div>

    <!-- === ADMIN === -->
    <div id="page-admin" v-if="currentView === 'admin'">
      <h2 class="section-title">⚙️ Admin Bereich</h2>
      
      <!-- lagerverwaltung -->
      <h3>Lagerverwaltung</h3>
      <table class="admin-table">
        <thead><tr><th>Produkt</th><th>Preis</th><th>Bestand</th></tr></thead>
        <tbody>
          <tr v-for="p in products" :key="p.id">
            <td>{{ p.title }}</td>
            <td>{{ p.price }} €</td>
            <td>
              <input type="number" v-model="p.stock" class="stock-input">
              <button @click="updateProductStock(p)" class="btn-mini">💾</button>
            </td>
          </tr>
        </tbody>
      </table>
      <button @click="handleUpdateAllStocks" class="btn btn-warning mt-2">Alle speichern</button>

      <hr style="margin: 40px 0;">

      <!-- bestellungen anzeigen -->
      <h3>📦 Bestellungen</h3>
      <button @click="fetchOrders" class="btn btn-secondary">Aktualisieren</button>
      <table class="admin-table mt-2">
        <thead><tr><th>ID</th><th>Datum</th><th>Kunde</th><th>Summe</th></tr></thead>
        <tbody>
          <tr v-for="order in adminOrders" :key="order.id">
            <td>#{{ order.id }}</td>
            <td>{{ order.created_at }}</td>
            <td>
              <b>{{ order.customer_data.vorname }} {{ order.customer_data.nachname }}</b><br>
              <small>{{ order.customer_data.email }}</small><br>
              <small>{{ order.customer_data.strasse }}, {{ order.customer_data.plz }}</small>
            </td>
            <td>{{ order.total_price }} €</td>
          </tr>
        </tbody>
      </table>
    </div>

  </main>
  <Footer />
</template>

<style>
/* globale styles */
:root { --primary: #2c3e50; --accent: #e67e22; --bg: #f8f9fa; }
body { font-family: 'Open Sans', sans-serif; background: var(--bg); margin: 0; color: #333; }
.container { max-width: 1000px; margin: 30px auto; padding: 0 20px; min-height: 80vh; }

/* schriften */
h2, h3, .catalog-title { font-family: 'Mountains of Christmas', cursive; color: var(--primary); }
.section-title { text-align: center; font-size: 2.5rem; margin-bottom: 30px; }

/* karten layout */
.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
.card { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.card-img-container { height: 200px; width: 100%; border-bottom: 1px solid #eee; }
.card img { width: 100%; height: 100%; object-fit: cover; }
.card-body { padding: 15px; text-align: center; }

/* inputs fixen (damit man was sieht im darkmode) */
input, textarea { 
  color: #000 !important; background: #fff !important; 
  border: 1px solid #ccc; padding: 10px; border-radius: 5px; width: 100%; box-sizing: border-box; 
}
.search-input { max-width: 400px; display: block; margin: 0 auto 20px; border-radius: 20px; }

/* layout warenkorb */
.cart-wrapper { display: flex; flex-direction: column; gap: 20px; }
.cart-table { width: 100%; background: #fff; border-radius: 8px; border-collapse: collapse; }
.cart-table th, .cart-table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
.qty-controls { display: flex; align-items: center; gap: 5px; }

/* formular styling */
.registration-form { padding: 20px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.full-width { grid-column: span 2; }
.form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.9rem; }

/* buttons */
.btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; color: white; transition: 0.2s; }
.btn-primary { background: var(--primary); }
.btn-secondary { background: #95a5a6; }
.btn-danger { background: #e74c3c; }
.btn-warning { background: var(--accent); }
.btn-checkout { background: #27ae60; width: 100%; font-size: 1.1rem; margin-top: 10px; }
.btn-mini { background: #eee; color: #333; width: 30px; height: 30px; border-radius: 50%; border: 1px solid #ccc; cursor: pointer; }

/* admin tabellen */
.admin-table { width: 100%; background: #fff; border-collapse: collapse; }
.admin-table th, .admin-table td { border: 1px solid #ddd; padding: 8px; }
.stock-input { width: 70px; margin-right: 5px; }

/* popup */
.alert-box { background: #333; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; }
</style>