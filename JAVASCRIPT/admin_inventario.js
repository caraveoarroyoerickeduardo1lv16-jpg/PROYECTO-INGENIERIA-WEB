document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.getElementById("searchInput");
  const suggestionsBox = document.getElementById("searchSuggestions");
  const notFoundBox = document.getElementById("searchNotFound");
  const selectCategoria = document.getElementById("categoria");

  if (!searchInput || !suggestionsBox || !notFoundBox) return;

  function ocultarSugerencias() {
    suggestionsBox.innerHTML = "";
    suggestionsBox.style.display = "none";
  }

  function ocultarNoEncontrado() {
    notFoundBox.classList.remove("show");
  }

  function mostrarNoEncontrado() {
    notFoundBox.classList.add("show");
    setTimeout(() => notFoundBox.classList.remove("show"), 1800);
  }

  async function buscar(q) {
    const resp = await fetch("../PHP/buscar_productos.php?q=" + encodeURIComponent(q));
    if (!resp.ok) return [];
    const data = await resp.json();
    return Array.isArray(data) ? data : [];
  }

  // Escribir: mostrar sugerencias (NO mostrar "no encontrado" aquí)
  searchInput.addEventListener("input", async () => {
    const texto = searchInput.value.trim();

    ocultarNoEncontrado();

    if (texto.length < 1) {
      ocultarSugerencias();
      return;
    }

    try {
      const data = await buscar(texto);

      if (data.length === 0) {
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
        const marca = item.marca || "";
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
    } catch (e) {
      ocultarSugerencias();
    }
  });

  // ENTER: aquí SÍ mostramos "no encontrado" si no hay resultados
  searchInput.addEventListener("keydown", async (e) => {
    if (e.key !== "Enter") return;

    e.preventDefault();
    const texto = searchInput.value.trim();
    if (!texto) return;

    ocultarSugerencias();

    try {
      const data = await buscar(texto);

      if (data.length === 0) {
        mostrarNoEncontrado();
        return;
      }

      // Si existe: filtra tabla por q (manteniendo categoría)
      const cat = selectCategoria?.value ? selectCategoria.value : "";
      let url = "admin_inventario.php?q=" + encodeURIComponent(texto);
      if (cat) url += "&categoria=" + encodeURIComponent(cat);
      window.location.href = url;
    } catch (e2) {
      mostrarNoEncontrado();
    }
  });

  // Click fuera: ocultar sugerencias
  document.addEventListener("click", (e) => {
    if (!suggestionsBox.contains(e.target) && e.target !== searchInput) {
      ocultarSugerencias();
    }
  });
});
