<script setup>
defineProps({
  cartCount: Number,
  isLoggedIn: Boolean
});

defineEmits(['navigate', 'logout']);
</script>

<template>
  <header class="header">
    <div class="logo" @click="$emit('navigate', 'catalog')">
      Weihnachtsshop G13
    </div>
    
    <nav class="nav-links">
      <button @click="$emit('navigate', 'catalog')">Katalog</button>
      
      <button @click="$emit('navigate', 'cart')" class="cart-btn">
        Warenkorb ({{ cartCount }})
      </button>
      
      <!-- Zeige Login nur wenn NICHT eingeloggt -->
      <button v-if="!isLoggedIn" @click="$emit('navigate', 'login')">Login</button>
      
      <!-- Zeige Admin & Logout nur wenn eingeloggt -->
      <template v-else>
        <button @click="$emit('navigate', 'admin')" class="admin-btn">Admin</button>
        <button @click="$emit('logout')" class="logout-btn">Logout</button>
      </template>
    </nav>
  </header>
</template>

<style scoped>
.header {
  background: linear-gradient(135deg, #09442c, #78a98f);
  color: white;
  padding: 1rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}
.logo { font-size: 1.5rem; font-weight: bold; cursor: pointer; }
.nav-links button {
  background: rgba(255,255,255,0.1);
  border: none;
  color: white;
  padding: 8px 15px;
  margin-left: 10px;
  border-radius: 20px;
  cursor: pointer;
  transition: all 0.3s;
}
.nav-links button:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); }
.admin-btn { background-color: #e67e22 !important; }
.logout-btn { background-color: #c0392b !important; }
</style>