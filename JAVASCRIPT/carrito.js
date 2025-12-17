// JAVASCRIPT/carrito.js

async function actualizarCarrito(productoId, accion) {
  const formData = new FormData();
  formData.append("producto_id", productoId);
  formData.append("accion", accion); // "add" | "remove" | "delete"

  const resp = await fetch("../PHP/carrito_actualizar.php", {
    method: "POST",
    body: formData
  });

  const data = await resp.json();
  return data;
}

function dinero(n) {
  const num = Number(n || 0);
  return num.toLocaleString("es-MX", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

document.addEventListener("DOMContentLoaded", () => {

  // Delegación de eventos: funciona aunque haya muchos botones
  document.addEventListener("click", async (e) => {
    const btnMas = e.target.closest(".btn-mas");
    const btnMenos = e.target.closest(".btn-menos");
    const btnEliminar = e.target.closest(".btn-eliminar");

    if (!btnMas && !btnMenos && !btnEliminar) return;

    let accion = "";
    let productoId = 0;

    if (btnMas) {
      accion = "add";
      productoId = parseInt(btnMas.dataset.id, 10);
    } else if (btnMenos) {
      accion = "remove";
      productoId = parseInt(btnMenos.dataset.id, 10);
    } else if (btnEliminar) {
      accion = "delete";
      productoId = parseInt(btnEliminar.dataset.id, 10);
    }

    if (!productoId) return;

    // Evitar doble click
    if (btnMas) btnMas.disabled = true;
    if (btnMenos) btnMenos.disabled = true;
    if (btnEliminar) btnEliminar.disabled = true;

    try {
      const r = await actualizarCarrito(productoId, accion);

      if (!r.ok) {
        alert(r.msg || "No se pudo actualizar el carrito.");
        return;
      }

      // 1) Actualizar header (artículos y total)
      const headerItems = document.querySelector(".header-items");
      const headerPrice = document.querySelector(".header-price");
      if (headerItems) headerItems.textContent = `${r.total_items} artículo${r.total_items === 1 ? "" : "s"}`;
      if (headerPrice) headerPrice.textContent = `$${dinero(r.total_carrito)}`;

      // 2) Actualizar resumen derecha
      const subtotalP = document.querySelector(".subtotal span");
      const totalStrong = document.querySelector(".total strong:last-child");
      if (subtotalP) subtotalP.textContent = `$${dinero(r.total_carrito)}`;
      if (totalStrong) totalStrong.textContent = `$${dinero(r.total_carrito)}`;

      // 3) Actualizar texto arriba "X artículos"
      const topP = document.querySelector("main.contenedor > p");
      if (topP) topP.textContent = `${r.total_items} artículos`;

      // 4) Actualizar el item en la lista
      // Buscamos el <article class="item"> del producto
      const anyBtn = btnMas || btnMenos || btnEliminar;
      const article = anyBtn.closest("article.item");

      if (!article) return;

      // Si el producto ya no existe en carrito => quitarlo del DOM
      if (r.item_qty <= 0) {
        article.remove();

        // Si ya no quedan productos, mostrar mensaje
        const productosSection = document.querySelector("section.productos");
        const hayItems = productosSection && productosSection.querySelector("article.item");
        if (!hayItems && productosSection) {
          productosSection.innerHTML = `<p>No tienes productos en el carrito.</p>`;
        }
        return;
      }

      // Si aún existe => actualizar cantidad y subtotal
      const spanCantidad = article.querySelector(".cantidad");
      const divPrecio = article.querySelector(".precio");

      if (spanCantidad) spanCantidad.textContent = r.item_qty;
      if (divPrecio) divPrecio.textContent = `$${dinero(r.item_subtotal)}`;

    } catch (err) {
      console.error(err);
      alert("Error de red o servidor. Revisa consola y PHP.");
    } finally {
      if (btnMas) btnMas.disabled = false;
      if (btnMenos) btnMenos.disabled = false;
      if (btnEliminar) btnEliminar.disabled = false;
    }
  });

});


