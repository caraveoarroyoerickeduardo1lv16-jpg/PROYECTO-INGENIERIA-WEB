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
    const card = document.querySelector(".producto-card");

    if (!card) return;

    const productoId = 1;

    const btnAgregar   = card.querySelector(".btn-agregar");
    const controlCantidad = card.querySelector(".cantidad-control");
    const btnMas       = card.querySelector(".btn-mas");
    const btnMenos     = card.querySelector(".btn-menos");
    const spanCantidad = card.querySelector(".cantidad");

    const cartTotalItems = document.getElementById("cartTotalItems");

    let cantidad = 0;
    let totalCarrito = 0;

    function actualizarHeader() {
        cartTotalItems.textContent =
            totalCarrito + " artículo" + (totalCarrito !== 1 ? "s" : "");
    }

    // BOTÓN "AGREGAR"
    btnAgregar.addEventListener("click", async () => {
        const data = await actualizarCarrito(productoId, "add");

        if (!data.success) return alert(data.message);

        cantidad = data.cantidad;
        totalCarrito = data.total_carrito;

        spanCantidad.textContent = cantidad;
        btnAgregar.style.display = "none";
        controlCantidad.classList.remove("oculto");

        actualizarHeader();
    });

    // BOTÓN "+"
    btnMas.addEventListener("click", async () => {
        const data = await actualizarCarrito(productoId, "add");

        if (!data.success) return alert(data.message);

        cantidad = data.cantidad;
        totalCarrito = data.total_carrito;

        spanCantidad.textContent = cantidad;
        actualizarHeader();
    });

    // BOTÓN "-"
    btnMenos.addEventListener("click", async () => {
        const data = await actualizarCarrito(productoId, "remove");

        if (!data.success) return alert(data.message);

        cantidad = data.cantidad;
        totalCarrito = data.total_carrito;

        if (cantidad <= 0) {
            controlCantidad.classList.add("oculto");
            btnAgregar.style.display = "inline-block";
        } else {
            spanCantidad.textContent = cantidad;
        }

        actualizarHeader();
    });

});

