document.addEventListener("DOMContentLoaded", () => {
  const input = document.getElementById("invSearchInput");
  const box = document.getElementById("invSearchSuggestions");
  const notFound = document.getElementById("invSearchNotFound");
  const categoria = document.getElementById("categoria");

  if (!input || !box || !notFound) return;

  notFound.classList.remove("show");

  function hideBox() {
    box.innerHTML = "";
    box.style.display = "none";
  }

  function showNotFound() {
    notFound.classList.add("show");
    setTimeout(() => notFound.classList.remove("show"), 1600);
  }

  async function buscar(q) {
    const resp = await fetch("../PHP/buscar_productos.php?q=" + encodeURIComponent(q));
    if (!resp.ok) return [];
    const data = await resp.json();
    return Array.isArray(data) ? data : [];
  }

  input.addEventListener("input", async () => {
    const texto = input.value.trim();
    notFound.classList.remove("show");

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
      const row = document.createElement("div");
      row.className = "inv-suggestion-item";

      const txt = document.createElement("span");
      txt.className = "inv-txt";
      const nombre = item.nombre || "";
      const marca = item.marca || "";
      txt.textContent = (marca ? marca + " " : "") + nombre;

      const icon = document.createElement("span");
      icon.className = "inv-icon";
      icon.textContent = "↗";

      row.appendChild(txt);
      row.appendChild(icon);

      row.addEventListener("click", () => {
        const cat = categoria?.value ? categoria.value : "";
        let url = "admin_inventario.php?producto_id=" + encodeURIComponent(item.id);
        if (cat) url += "&categoria=" + encodeURIComponent(cat);
        window.location.href = url;
      });

      box.appendChild(row);
    });

    box.style.display = "block";
  });

  input.addEventListener("keydown", async (e) => {
    if (e.key !== "Enter") return;
    e.preventDefault();

    const texto = input.value.trim();
    hideBox();

    if (!texto) {
      notFound.classList.remove("show");
      return;
    }

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

  document.addEventListener("click", (e) => {
    if (!box.contains(e.target) && e.target !== input) hideBox();
  });
});

