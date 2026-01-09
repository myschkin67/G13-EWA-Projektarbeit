import { ref, computed } from 'vue';

// wir machen die cart variable ausserhalb der funktion.
// damit bleibt der warenkorb erhalten auch wenn man die seite wechselt (singleton).
const cart = ref([]);

export function useCart() {
  
  // produkt hinzufügen, aber vorher checken ob bestand reicht
  const addToCart = (product) => {
    // erst mal schauen ob überhaupt bestand da ist
    if (product.stock <= 0) return "ausverkauft";

    const item = cart.value.find(i => i.id === product.id);
    const currentQty = item ? item.qty : 0;

    // wir dürfen nicht mehr reinlegen als im lager ist
    if (currentQty + 1 > product.stock) {
      return "limit_reached"; 
    }

    if (item) {
      item.qty++;
    } else {
      // kopie vom produkt machen und menge auf 1 setzen
      cart.value.push({ ...product, qty: 1 });
    }
    return "success";
  };

  const removeFromCart = (index) => {
    cart.value.splice(index, 1);
  };

  // logik für plus/minus buttons
  const updateCartQty = (item, change) => {
    const newQty = item.qty + change;

    // beim erhöhen müssen wir wieder das limit prüfen
    if (change > 0) {
      if (newQty > item.stock) {
        return false; // ging nicht, fehler zurückgeben
      }
    }

    item.qty = newQty;

    // wenn menge 0 ist, fliegt der artikel ganz raus
    if (item.qty <= 0) {
      cart.value = cart.value.filter(i => i.id !== item.id);
    }
    
    return true; 
  };

  // summe berechnen
  const cartTotal = computed(() => {
    return cart.value.reduce((sum, item) => sum + (item.price * item.qty), 0);
  });

  // mehrwertsteuer 7% (laut aufgabe)
  const vatAmount = computed(() => {
    return cartTotal.value * 0.07; 
  });

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