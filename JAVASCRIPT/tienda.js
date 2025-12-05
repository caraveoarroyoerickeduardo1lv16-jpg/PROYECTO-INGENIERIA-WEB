// JAVASCRIPT/tienda.js

async function actualizarCarrito(productoId, accion) {
    const formData = new FormData();
    formData.append("producto_id", productoId);
    formData.append("accion", accion);

    const response = await fetch("../PHP/carrito_actualizar.php", {
        method: "POST",
        body: formData
    });

    return await response.json();
}

document.addEventListener("DOMContentLoaded", () => {
    const cards = document.querySelectorAll(".producto-card");
    const cartTotalItems = document.getElementById("cartTotalItems");
    const cartTotalPrice = document.getElementById("cartTotalPrice");

    function actualizarHeader(items, total) {
        cartTotalItems.textContent =
            items + " artículo" + (items !== 1 ? "s" : "");
        cartTotalPrice.textContent = "$" + total.toFixed(2);
    }

    cards.forEach(card => {
        const productoId = parseInt(card.dataset.id, 10);

        const btnAgregar   = card.querySelector(".btn-agregar");
        const controlCant  = card.querySelector(".cantidad-control");
        const btnMas       = card.querySelector(".btn-mas");
        const btnMenos     = card.querySelector(".btn-menos");
        const spanCantidad = card.querySelector(".cantidad");

        let cantidadLocal = 0;

        // CLIC en "+ Agregar"
        btnAgregar.addEventListener("click", async () => {
            const data = await actualizarCarrito(productoId, "add");
            if (!data.success) {
                alert(data.message);
                return;
            }

            cantidadLocal = data.cantidad;
            spanCantidad.textContent = cantidadLocal;

            btnAgregar.style.display = "none";
            controlCant.classList.remove("oculto");

            actualizarHeader(data.total_items, data.total_carrito);
        });

        // CLIC en "+"
        btnMas.addEventListener("click", async () => {
            const data = await actualizarCarrito(productoId, "add");
            if (!data.success) {
                alert(data.message);
                return;
            }

            cantidadLocal = data.cantidad;
            spanCantidad.textContent = cantidadLocal;

            actualizarHeader(data.total_items, data.total_carrito);
        });

        // CLIC en "−"
        btnMenos.addEventListener("click", async () => {
            const data = await actualizarCarrito(productoId, "remove");
            if (!data.success) {
                alert(data.message);
                return;
            }

            cantidadLocal = data.cantidad;

            if (cantidadLocal <= 0) {
                controlCant.classList.add("oculto");
                btnAgregar.style.display = "inline-block";
            } else {
                spanCantidad.textContent = cantidadLocal;
            }

            actualizarHeader(data.total_items, data.total_carrito);
        });
    });
});
