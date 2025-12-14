document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.getElementById("searchInput");
  const suggestionsBox = document.getElementById("searchSuggestions");
  const searchNotFound = document.getElementById("searchNotFound");
  const selectCategoria = document.getElementById("categoria");

  if (!searchInput || !suggestionsBox || !searchNotFound) return;

  // ✅ ocultar al iniciar
  searchNotFound.classList.remove("show");

  function ocultarSugerencias() {
    suggestionsBox.innerHTML = "";
    suggestionsBox.style.display = "none";
  }

  function mostrarNoEncontrado() {
    searchNotFound.classList.add("show");
    setTimeout(() => searchNotFound.classList.remove("show"), 1600);
  }

  async function buscar(texto) {
    // ✅ admin_inventario.php está en /PHP, por eso es "buscar_productos.php"
    const resp = await fetch("buscar_productos.php?q=" + encodeURIComponent(texto));
    if (!resp.ok) return [];
    const data = await resp.json();
    return Array.isArray(data) ? data : [];
  }

  searchInput.addEventListener("input", async () => {
    const texto = searchInput.value.trim();
    searchNotFound.classList.remove("show");

    if (texto.length < 1) {
      ocultarSugerencias();
      return;
    }

    try {
      const data = await buscar(texto);

      if (!data.length) {
        ocultarSugerencias();
        return;
      }

      suggestionsBox.innerHTML = "";

      data.forEach(item => {
        const div = document.createElement("div");
        div.className = "suggestion-item";

        const spanTxt = document.createElement("span");
        spanTxt.className = "txt";
        const nombre = item.nombre || "";
        const marca  = item.marca || "";
        spanTxt.textContent = (marca ? marca + " " : "") + nombre;

        const spanIcon = document.createElement("span");
        spanIcon.className = "icon";
        spanIcon.textContent = "↗";

        div.appendChild(spanTxt);
        div.appendChild(spanIcon);

        div.addEventListener("click", () => {
          const id = item.id;
          const cat = selectCategoria?.value ? selectCategoria.value : "";
          let url = "admin_inventario.php?producto_id=" + encodeURIComponent(id);
          if (cat) url += "&categoria=" + encodeURIComponent(cat);
          window.location.href = url;
        });

        suggestionsBox.appendChild(div);
      });

      suggestionsBox.style.display = "block";
    } catch {
      ocultarSugerencias();
    }
  });

  // ENTER: solo aquí mostramos "Producto no encontrado"
  searchInput.addEventListener("keydown", async (e) => {
    if (e.key !== "Enter") return;
    e.preventDefault();

    const texto = searchInput.value.trim();
    if (!texto) return;

    ocultarSugerencias();

    try {
      const data = await buscar(texto);

      if (!data.length) {
        mostrarNoEncontrado();
        return;
      }

      // si hay resultados, filtra por q (manteniendo categoría)
      const cat = selectCategoria?.value ? selectCategoria.value : "";
      let url = "admin_inventario.php?q=" + encodeURIComponent(texto);
      if (cat) url += "&categoria=" + encodeURIComponent(cat);
      window.location.href = url;

    } catch {
      mostrarNoEncontrado();
    }
  });

  // click fuera: cerrar
  document.addEventListener("click", (e) => {
    if (!suggestionsBox.contains(e.target) && e.target !== searchInput) {
      ocultarSugerencias();
    }
  });
});
