import { ref, onMounted } from 'vue';

export function useProducts() {
  const products = ref([]);
  const error = ref(null);

  // daten von der php api holen
  const fetchProducts = async () => {
    try {
      // url kommt aus der .env datei (lokal localhost, live api/)
      const baseUrl = import.meta.env.VITE_API_BASE_URL;
      const res = await fetch(baseUrl + 'products.php');
      
      if (!res.ok) throw new Error("API antwortet nicht");
      
      products.value = await res.json();
    } catch (e) {
      error.value = "konnte produkte nicht laden: " + e.message;
    }
  };

  // funktion um bestand zu ändern (für admin)
  const updateProductStock = async (product) => {
    const baseUrl = import.meta.env.VITE_API_BASE_URL;
    try {
      const res = await fetch(baseUrl + 'update_stock.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: product.id, stock: product.stock })
      });
      return res.ok;
    } catch (e) {
      return false;
    }
  };

  // direkt beim laden ausführen
  onMounted(fetchProducts);

  return { products, error, fetchProducts, updateProductStock };
}