import { ref, computed } from 'vue';

const cart = ref([]);

export function useCart() {
  
  // Funktion: Zum Warenkorb hinzufügen
  const addToCart = (product) => {
    // 1. Ist überhaupt Bestand da?
    if (product.stock <= 0) return "ausverkauft";

    const item = cart.value.find(i => i.id === product.id);
    const currentQty = item ? item.qty : 0;

    // 2. Würde das Hinzufügen das Limit sprengen?
    if (currentQty + 1 > product.stock) {
      return "limit_reached"; // Neuer Rückgabewert!
    }

    // 3. Hinzufügen erlaubt
    if (item) {
      item.qty++;
    } else {
      // Wir speichern 'stock' mit im Warenkorb, um später Limits zu prüfen
      cart.value.push({ ...product, qty: 1 });
    }
    return "success";
  };

  const removeFromCart = (index) => {
    cart.value.splice(index, 1);
  };

  // Funktion: Menge im Warenkorb ändern (+/-)
  const updateCartQty = (item, change) => {
    const newQty = item.qty + change;

    // A. Prüfung: Lagerbestand überschritten?
    if (change > 0 && newQty > item.stock) {
      return false; // WICHTIG: Wir geben FALSE zurück -> "Hat nicht geklappt"
    }

    // B. Alles ok -> ändern
    item.qty = newQty;
    if (item.qty <= 0) {
      cart.value = cart.value.filter(i => i.id !== item.id);
    }
    
    return true; // WICHTIG: Wir geben TRUE zurück -> "Hat geklappt"
  };
// ...

  const cartTotal = computed(() => {
    return cart.value.reduce((sum, item) => sum + (item.price * item.qty), 0);
  });

  const vatAmount = computed(() => cartTotal.value * 0.07);

  const totalItems = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.qty, 0);
  });

  return { 
    cart, 
    addToCart, 
    removeFromCart, 
    updateCartQty, 
    cartTotal, 
    vatAmount, 
    totalItems 
  };
}