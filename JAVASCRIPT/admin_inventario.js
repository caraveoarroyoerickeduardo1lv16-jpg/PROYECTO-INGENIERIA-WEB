document.addEventListener("DOMContentLoaded", () => {
  const input = document.getElementById("invSearchInput");
  const box = document.getElementById("invSearchSuggestions");
  const notFound = document.getElementById("invNotFound");
  const clearBtn = document.getElementById("invClearBtn");
  const categoria = document.getElementById("categoria");

  if (!input || !box || !notFound) return;

  function hideBox() {
    box.innerHTML = "";
    box.style.display = "none";
  }

  function showNotFound() {
    notFound.style.display = "block";
    setTimeout(() => (notFound.style.display = "none"), 1800);
  }

  function updateClear() {
    clearBtn.style.display = input.value.trim() ? "inline-block" : "none";
  }

  async function buscar(q) {
    // ✅ admin_inventario.php está en /PHP => ruta correcta:
    const resp = await fetch("buscar_productos.php?q=" + encodeURIComponent(q));
    if (!resp.ok) return [];
    const data = await resp.json();
    return Array.isArray(data) ? data : [];
  }

  clearBtn.addEventListener("click", () => {
    input.value = "";
    updateClear();
    hideBox();
    notFound.style.display = "none";
    input.focus();
  });

  input.addEventListener("input", async () => {
    const texto = input.value.trim();
    notFound.style.display = "none";
    updateClear();

    if (texto.length < 1) {
      hideBox();
      return;
    }

    const data = await buscar(texto);

    if (!data.length) {
      hideBox();
      return;
    }

    box.innerHTML = "";
    data.forEach(item => {
      const div = document.createElement("div");
      div.className = "inv-sug-item";

      const spanName = document.createElement("span");
      spanName.className = "inv-sug-name";
      const nombre = item.nombre || "";
      const marca = item.marca || "";
      spanName.textContent = (marca ? marca + " " : "") + nombre;

      const spanIcon = document.createElement("span");
      spanIcon.className = "inv-sug-icon";
      spanIcon.textContent = "↗";

      div.appendChild(spanName);
      div.appendChild(spanIcon);

      div.addEventListener("click", () => {
        const cat = categoria?.value ? categoria.value : "";
        let url = "admin_inventario.php?producto_id=" + encodeURIComponent(item.id);
        if (cat) url += "&categoria=" + encodeURIComponent(cat);
        window.location.href = url;
      });

      box.appendChild(div);
    });

    box.style.display = "block";
  });

  // ENTER => si existe filtra por q, si no existe muestra mensaje
  input.addEventListener("keydown", async (e) => {
    if (e.key !== "Enter") return;
    e.preventDefault();

    const texto = input.value.trim();
    hideBox();

    if (!texto) return;

    const data = await buscar(texto);

    if (!data.length) {
      showNotFound();
      return;
    }

    const cat = categoria?.value ? categoria.value : "";
    let url = "admin_inventario.php?q=" + encodeURIComponent(texto);
    if (cat) url += "&categoria=" + encodeURIComponent(cat);
    window.location.href = url;
  });

  // click fuera
  document.addEventListener("click", (e) => {
    if (!box.contains(e.target) && e.target !== input) hideBox();
  });

  // estado inicial
  updateClear();
  notFound.style.display = "none";
});

