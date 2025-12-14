document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.getElementById("adminSearchInput");
  const suggestionsBox = document.getElementById("adminSearchSuggestions");
  const notFoundBox = document.getElementById("adminSearchNotFound");
  const selectCategoria = document.getElementById("categoria");

  if (!searchInput || !suggestionsBox || !notFoundBox) return;

  // ✅ asegurar que al cargar SIEMPRE quede oculto
  notFoundBox.classList.remove("show");

  function hideSuggestions() {
    suggestionsBox.innerHTML = "";
    suggestionsBox.style.display = "none";
  }

  function showNotFound() {
    notFoundBox.classList.add("show");
    setTimeout(() => notFoundBox.classList.remove("show"), 1600);
  }

  async function buscar(q) {
    const resp = await fetch("../PHP/buscar_productos.php?q=" + encodeURIComponent(q));
    if (!resp.ok) return [];
    const data = await resp.json();
    return Array.isArray(data) ? data : [];
  }

  // Input: solo sugerencias, nunca "no encontrado"
  searchInput.addEventListener("input", async () => {
    const texto = searchInput.value.trim();

    // ✅ si el usuario borra, ocultar todo
    notFoundBox.classList.remove("show");
    if (texto.length < 1) {
      hideSuggestions();
      return;
    }

    try {
      const data = await buscar(texto);

      if (data.length === 0) {
        hideSuggestions();
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
      hideSuggestions();
    }
  });

  // ENTER: aquí sí "no encontrado"
  searchInput.addEventListener("keydown", async (e) => {
    if (e.key !== "Enter") return;

    e.preventDefault();
    const texto = searchInput.value.trim();
    hideSuggestions();

    if (!texto) {
      // ✅ si le da enter vacío, no hacer nada, y ocultar mensaje
      notFoundBox.classList.remove("show");
      return;
    }

    try {
      const data = await buscar(texto);

      if (data.length === 0) {
        showNotFound();
        return;
      }

      const cat = selectCategoria?.value ? selectCategoria.value : "";
      let url = "admin_inventario.php?q=" + encodeURIComponent(texto);
      if (cat) url += "&categoria=" + encodeURIComponent(cat);
      window.location.href = url;

    } catch {
      showNotFound();
    }
  });

  document.addEventListener("click", (e) => {
    if (!suggestionsBox.contains(e.target) && e.target !== searchInput) {
      hideSuggestions();
    }
  });
});
