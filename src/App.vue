<script setup>
import { ref, computed } from 'vue';
import Header from './components/Header.vue';
import Footer from './components/Footer.vue';

// Importieren der Logik aus den neuen Dateien
import { useProducts } from './composables/useProducts';
import { useCart } from './composables/useCart';

// --- INITIALISIERUNG DER LOGIK ---
// Wir holen uns die Variablen und Funktionen aus den Composables
const { products, error, updateProductStock } = useProducts();
const { 
  cart, 
  addToCart, 
  removeFromCart, 
  updateCartQty, 
  cartTotal, 
  vatAmount, 
  totalItems 
} = useCart();

// --- LOKALER UI STATE (Nur für Anzeige wichtig) ---
const currentView = ref('catalog');
const user = ref(null);
const loginInput = ref({ username: '', password: '' });
const message = ref('');
const searchQuery = ref('');

// --- FILTER & SUCHE ---
const filteredProducts = computed(() => {
  if (!searchQuery.value) return products.value;
  const term = searchQuery.value.toLowerCase();
  return products.value.filter(p => p.title.toLowerCase().includes(term));
});

const totalWithVat = computed(() => cartTotal.value); // Brutto-Preis Logik

// --- WRAPPER FUNKTIONEN (Verbinden Logik mit UI-Feedback) ---

// Produkt zum Warenkorb hinzufügen mit Nachricht
// Produkt zum Warenkorb hinzufügen mit Nachricht
const handleAddToCart = (product) => {
  const result = addToCart(product); 
  
  if (result === "ausverkauft") {
    message.value = "⚠️ Artikel ist leider ausverkauft!";
  } else if (result === "limit_reached") {
    // NEU: Feedback für den Nutzer
    message.value = "⚠️ Maximalen Lagerbestand für diesen Artikel erreicht!";
  } else {
    message.value = `✅ ${product.title} in den Warenkorb gelegt.`;
    setTimeout(() => message.value = '', 2000); 
  }
};

// Wrapper in App.vue
const handleCartQtyChange = (item, change) => {
  // 1. Wir fragen die Logik: "Darf ich das?"
  const success = updateCartQty(item, change);
  
  // 2. Wenn die Logik "Nein" (false) sagt, zeigen wir die Nachricht im UI
  if (!success) {
    message.value = "⚠️ Nicht mehr auf Lager!";
    setTimeout(() => message.value = '', 2000);
  }
};

// Admin: Lagerbestände speichern
const handleUpdateAllStocks = async () => {
  message.value = "💾 Speichere Bestände...";
  let errorCount = 0;
  
  // Geht durch alle Produkte und sendet Updates an API
  for (const p of products.value) {
    const success = await updateProductStock(p); // Ruft Logik in useProducts.js auf
    if (!success) errorCount++;
  }

  if (errorCount === 0) {
    message.value = "✅ Alle Lagerbestände erfolgreich gespeichert!";
  } else {
    message.value = "⚠️ Fehler beim Speichern einiger Produkte.";
  }
};

// Login Logik
const handleLogin = () => {
  if (loginInput.value.username === 'admin' && loginInput.value.password === 'adm24') {
    user.value = { name: 'Admin', role: 'admin' };
    currentView.value = 'admin';
    message.value = "🔓 Willkommen im Admin-Bereich!";
    loginInput.value = { username: '', password: '' };
  } else {
    message.value = "❌ Zugangsdaten falsch! (Versuch: admin / adm24)";
  }
};

const logout = () => {
  user.value = null;
  currentView.value = 'catalog';
  message.value = "🔒 Erfolgreich ausgeloggt.";
};


// --- STRIPE CHECKOUT ---
const handleCheckout = async () => {
  if (cart.value.length === 0) return;

  message.value = "🔄 Verbinde mit Stripe...";
  
  try {
    const baseUrl = import.meta.env.VITE_API_BASE_URL;
    
    // 1. Session ID vom Server holen
    const res = await fetch(baseUrl + 'checkout.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ cart: cart.value })
    });

    if (!res.ok) {
       const errData = await res.json();
       throw new Error(errData.error || "Server Fehler");
    }

    const data = await res.json();

    // 2. Zu Stripe weiterleiten
    if (data.id) {
      // HIER DEINEN PUBLIC KEY EINFÜGEN! (pk_test_...)
      const stripe = Stripe('pk_test_51Sf50LPfKVgT4yvvJzL4Gz4DHUbhx2BHryUUj4JOF3gK4dpJnU0DKC2P3K6DTxNng9S4dcFEBu9uIZAf5hBpBySG00ILbYwXP4');
      
      const result = await stripe.redirectToCheckout({
        sessionId: data.id
      });
      
      if (result.error) {
        message.value = result.error.message;
      }
    }

  } catch (e) {
    console.error(e);
    message.value = "Fehler: " + e.message;
  }
};

</script>

<template>
  <!-- Header nutzt jetzt 'totalItems' aus useCart -->
  <Header 
    :cart-count="totalItems"
    :is-logged-in="user !== null"
    @navigate="v => currentView = v"
    @logout="logout"
  />

  <main class="container">
    
    <!-- Globale Nachrichten Box -->
    <div v-if="message" class="alert-box">
      {{ message }}
      <span class="close-btn" @click="message = ''">×</span>
    </div>

    <!-- === KATALOG === -->
    <div id="page-catalog" v-if="currentView === 'catalog'">
      <h2 class="catalog-title">Weihnachts-Angebote</h2>
      
      <div class="search-container">
        <input v-model="searchQuery" type="text" class="search-input" placeholder="🔍 Geschenke suchen..." data-testid="search-input">
      </div>

      <!-- Zeige Fehler beim Laden der Produkte -->
      <div v-if="error" style="color:red; text-align:center;">{{ error }}</div>

      <div class="product-grid">
        <div v-for="product in filteredProducts" :key="product.id" class="card" data-testid="product">
          <div class="card-img-container">
            <img :src="product.image_url" data-testid="product-image">
          </div>
          <div class="card-body">
            <h3 data-testid="product-title">{{ product.title }}</h3>
            <p class="price" data-testid="product-price">{{ product.price.toFixed(2) }} €</p>
            <p class="stock-info" :class="{'low-stock': product.stock < 5}">
              Lager: {{ product.stock }} Stück
            </p>
            <!-- Ruft jetzt den Wrapper handleAddToCart auf -->
            <button @click="handleAddToCart(product)" class="btn btn-primary" data-testid="product-order">
              In den Warenkorb
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- === WARENKORB === -->
    <div id="page-cart" v-if="currentView === 'cart'">
      <h2 class="section-title">🛒 Dein Warenkorb</h2>
      
      <div v-if="cart.length === 0" class="empty-cart">
        <p>Dein Warenkorb ist leer.</p>
        <button @click="currentView = 'catalog'" class="btn btn-secondary">Zum Shop</button>
      </div>

      <div v-else class="cart-container">
        <table class="cart-table">
          <thead><tr><th>Artikel</th><th>Preis</th><th>Menge</th><th>Gesamt</th><th>Aktion</th></tr></thead>
          <tbody>
            <tr v-for="(item, index) in cart" :key="item.id">
              <td>{{ item.title }}</td>
              <td>{{ item.price.toFixed(2) }} €</td>
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

        <div id="cart-summary" class="cart-summary">
          <h3>Zusammenfassung</h3>
          <p>Anzahl Artikel: <span data-testid="cart-total-items">{{ totalItems }}</span></p>
          <p style="font-size: 0.9rem; color: #7f8c8d;">Enthaltene MwSt. (7%): {{ vatAmount.toFixed(2) }} €</p>
          <hr>
          <p class="total-price">Gesamt: <span data-testid="cart-total-price">{{ totalWithVat.toFixed(2) }} €</span></p>
          <button @click="handleCheckout" class="btn btn-checkout">Zur Kasse (Stripe)</button>
        </div>
      </div>
    </div>

    <!-- === LOGIN === -->
    <div id="page-login" v-if="currentView === 'login'">
      <div class="login-card">
        <h2>🔒 Admin Login</h2>
        <div class="form-group">
          <label>Benutzername</label>
          <input v-model="loginInput.username" type="text" data-testid="login-username" placeholder="admin">
        </div>
        <div class="form-group">
          <label>Passwort</label>
          <input v-model="loginInput.password" type="password" data-testid="login-password" placeholder="adm24">
        </div>
        <button @click="handleLogin" class="btn btn-primary w-100" data-testid="login-submit">Einloggen</button>
      </div>
    </div>

    <!-- === ADMIN === -->
    <div id="page-admin" v-if="currentView === 'admin'">
      <h2 class="section-title">⚙️ Lagerverwaltung</h2>
      <table class="admin-table" data-testid="admin-stock-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Produkt</th>
            <th>Preis</th>
            <th>Lagerbestand (Aktuell & Neu)</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="product in products" :key="product.id">
            <td>{{ product.id }}</td>
            <td style="font-weight:bold;">{{ product.title }}</td>
            <td>{{ product.price.toFixed(2) }} €</td>
            <td>
              <div style="display: flex; align-items: center; gap: 15px;">
                <!-- Anzeige als reiner Text -->
                <span style="font-size: 1.1em; min-width: 30px;">
                  {{ product.stock }}
                </span>
                
                <!-- Das Eingabefeld zum Ändern -->
                <span>➜</span>
                <input 
                  type="number" 
                  v-model="product.stock" 
                  class="stock-input" 
                  min="0"
                >
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="admin-actions">
        <!-- Ruft jetzt handleUpdateAllStocks auf -->
        <button @click="handleUpdateAllStocks" class="btn btn-warning" data-testid="admin-update-stock">
          Bestände Speichern
        </button>
      </div>
    </div>

  </main>
  <Footer />
</template>

<style>
/* --- CSS Design --- */
/* === FIX: Schriftfarbe in Eingabefeldern erzwingen === */
input { color: #000000 !important; background-color: #ffffff !important; caret-color: #000000 !important; border: 1px solid #ccc;}
:root { --primary: #2c3e50; --accent: #e67e22; --bg: #f8f9fa; }
body { font-family: 'Open Sans', sans-serif; background-color: var(--bg); color: #333; margin: 0; }
.container { max-width: 1000px; margin: 30px auto; padding: 0 20px; min-height: 80vh; }
.alert-box { background: #333; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; box-shadow: 0 4px 10px rgba(0,0,0,0.2); animation: fadeIn 0.5s; }
.close-btn { cursor: pointer; font-weight: bold; }
.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 25px; }
.card { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: transform 0.2s; }
.card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
.card-img-container { height: 200px; width: 100%; display: block; padding: 0; background: #fff; border-bottom: 1px solid #eee;}
.card img { width: 100%; height: 100%; object-fit: cover; object-position: center; display: block;}
.card-body { padding: 15px; text-align: center; }
.price { font-size: 1.2rem; color: var(--primary); font-weight: bold; }
.stock-info { font-size: 0.9rem; color: #7f8c8d; margin-bottom: 10px; }
.low-stock { color: #e74c3c; font-weight: bold; }
.btn { border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 1rem; transition: background 0.2s; }
.btn-primary { background: var(--primary); color: white; }
.btn-primary:hover { background: #497fb0; }
.btn-secondary { background: #95a5a6; color: white; }
.btn-danger { background: #aeadad; color: white; border: none; padding: 6px 12px; border-radius: 5px; font-size: 0.9rem; cursor: pointer; transition: background 0.2s;}
.btn-warning { background: var(--accent); color: white; }
.btn-mini { background: #eee; border: 1px solid #ddd; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; }
.btn-checkout { background: #27ae60; color: white; width: 100%; padding: 15px; font-size: 1.1rem; border: none; border-radius: 5px; margin-top: 10px; cursor: pointer; }
.cart-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; background: white; border-radius: 8px; overflow: hidden; }
.cart-table th, .cart-table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
.cart-table th { background: #f1f1f1; }
.qty { margin: 0 10px; font-weight: bold; }
.cart-container { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
.cart-summary { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); height: fit-content; }
.total-price { font-size: 1.4rem; font-weight: bold; color: var(--primary); }
.login-card { max-width: 400px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; }
.form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
.admin-table { width: 100%; background: white; border-collapse: collapse; margin-bottom: 20px; }
.admin-table th, .admin-table td { padding: 12px; border: 1px solid #ddd; text-align: left; }
.stock-input { width: 80px; padding: 5px; text-align: center; font-weight: bold; border-radius: 4px; border: 1px solid #999;}
.admin-actions { text-align: right; }
.search-container { margin-bottom: 20px; text-align: center; }
.search-input { color-scheme: light; background-color: #ffffff !important; color: #000000 !important; border: 1px solid #ccc !important; padding: 12px; width: 100%; max-width: 400px; border-radius: 25px; font-size: 1rem; outline: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;}
.search-input::placeholder { color: #666666 !important; opacity: 1;}
.qty-controls { display: flex; align-items: center; justify-content: flex-start; white-space: nowrap; gap: 5px;}
.qty { min-width: 20px; text-align: center; font-weight: bold;}
.search-input:focus { border-color: var(--primary); box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.catalog-title {font-family: 'Mountains of Christmas', cursive; letter-spacing: 1px; text-align: center; margin-bottom: 30px; font-size: 2rem; color: var(--primary); }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>