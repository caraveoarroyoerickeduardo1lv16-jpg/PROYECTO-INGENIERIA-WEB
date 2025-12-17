function mostrarPaso(num) {
    ['paso1', 'paso2', 'paso3'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        if (id === 'paso' + num) el.classList.remove('hidden');
        else el.classList.add('hidden');
    });
}

// ✅ Solo letras (con acentos), espacios, punto y guion
function limpiarSoloLetras(str) {
    return (str || '').replace(/[0-9]/g, '').replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s.\-]/g, '');
}

function forzarSoloLetrasInput(input) {
    if (!input) return;

    // Bloquear teclas numéricas
    input.addEventListener('keydown', (e) => {
        const k = e.key;
        if (/^\d$/.test(k)) e.preventDefault();
    });

    // Limpiar cuando pegan o escriben
    input.addEventListener('input', () => {
        const limpio = limpiarSoloLetras(input.value);
        if (input.value !== limpio) input.value = limpio;
    });

    // Bloquear pegado con números
    input.addEventListener('paste', (e) => {
        e.preventDefault();
        const txt = (e.clipboardData || window.clipboardData).getData('text');
        input.value = limpiarSoloLetras(txt);
        input.dispatchEvent(new Event('input'));
    });
}

// Filtra los horarios según el día seleccionado (0=hoy,1=mañana,2=pasado)
function aplicarFiltroSlots(stepId, selectedDay) {
    const cont = document.getElementById('paso' + stepId);
    if (!cont) return;

    const now = new Date();
    const currentHour = now.getHours();
    const limitHour = currentHour + 2; // 2 horas

    const slots = cont.querySelectorAll('.slot');
    const statusHoy = cont.querySelector('.status-hoy');

    let visibles = 0;
    let firstRadio = null;

    slots.forEach(slot => {
        const startHour = parseInt(slot.dataset.hora, 10);
        let show = true;

        if (selectedDay === 0) {
            // HOY: solo slots con al menos 2h de diferencia
            if (startHour < limitHour || startHour >= 21) show = false;
        } else {
            show = true;
        }

        const r = slot.querySelector('input[type="radio"]');

        if (show) {
            slot.style.display = 'flex';
            visibles++;

            // ✅ si es paso 3: habilitar radios visibles
            if (stepId === 3 && r) {
                r.disabled = false;
                if (!firstRadio) firstRadio = r;
            }
        } else {
            slot.style.display = 'none';

            // ✅ si es paso 3: deshabilitar radios ocultos y des-seleccionar
            if (stepId === 3 && r) {
                r.checked = false;
                r.disabled = true;
            }
        }
    });

    // Mensaje Hoy agotado
    if (statusHoy) {
        if (selectedDay === 0 && visibles === 0) {
            statusHoy.textContent = 'Hoy: Agotado';
            statusHoy.style.display = 'block';
        } else {
            statusHoy.style.display = 'none';
        }
    }

    // ✅ Manejo de botón continuar
    if (stepId === 3) {
        const btnContinuar = document.getElementById('btnContinuarPago');
        if (btnContinuar) {
            btnContinuar.disabled = (visibles === 0);
        }
    }

    // Selección automática si hay radios
    if (stepId === 3) {
        const cont3 = document.getElementById('paso3');
        const yaSeleccionado = cont3.querySelector('input[name="horario"]:checked');

        if (!yaSeleccionado && firstRadio) {
            firstRadio.checked = true;
        }

        actualizarSeleccionHorario();
    }
}

function actualizarSeleccionHorario() {
    const resumen = document.getElementById('resumenHorario');
    if (!resumen) return;

    const cont = document.getElementById('paso3');
    if (!cont) return;

    const slots = cont.querySelectorAll('.slot');
    slots.forEach(s => s.classList.remove('selected'));

    const checked = cont.querySelector('input[name="horario"]:checked');
    if (!checked) {
        resumen.textContent = 'Selecciona un horario · $49.00';
        return;
    }

    const slot = checked.closest('.slot');
    if (!slot) return;

    slot.classList.add('selected');
    const titulo = slot.querySelector('.slot-title').textContent.trim();
    const precio = slot.querySelector('.slot-price').textContent.trim();
    resumen.textContent = `${titulo} · ${precio}`;
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formCheckout');

    // ✅ bloquear números en colonia/ciudad/estado
    forzarSoloLetrasInput(document.getElementById('coloniaInput'));
    forzarSoloLetrasInput(document.getElementById('ciudadInput'));
    forzarSoloLetrasInput(document.getElementById('estadoInput'));

    const btnAgregarDireccion = document.getElementById('btnAgregarDireccion');
    if (btnAgregarDireccion) {
        btnAgregarDireccion.addEventListener('click', (e) => {
            e.preventDefault();
            mostrarPaso(2);
        });
    }

    const btnVolverPaso1 = document.getElementById('btnVolverPaso1');
    if (btnVolverPaso1) {
        btnVolverPaso1.addEventListener('click', () => {
            mostrarPaso(1);
        });
    }

    const btnVolverPaso2 = document.getElementById('btnVolverPaso2');
    if (btnVolverPaso2) {
        btnVolverPaso2.addEventListener('click', () => {
            mostrarPaso(2);
        });
    }

    // Tabs de día
    const dayButtons = document.querySelectorAll('.day-button');
    const selectedDayByStep = { 1: 0, 3: 0 };

    dayButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const step = parseInt(btn.dataset.step, 10);
            const dia  = parseInt(btn.dataset.dia, 10);

            selectedDayByStep[step] = dia;

            const cont = document.getElementById('paso' + step);
            cont.querySelectorAll('.day-button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            aplicarFiltroSlots(step, dia);
        });
    });

    // Radios de horario
    const radiosHorario = document.querySelectorAll('#paso3 input[name="horario"]');
    radiosHorario.forEach(r => r.addEventListener('change', actualizarSeleccionHorario));

    // ✅ bloquear submit a pago si no hay slot
    const btnContinuar = document.getElementById('btnContinuarPago');
    if (btnContinuar) {
        btnContinuar.addEventListener('click', (e) => {
            // si está disabled por agotado, bloquear
            if (btnContinuar.disabled) {
                e.preventDefault();
                alert("Hoy está agotado. Selecciona Mañana o Pasado mañana.");
                return;
            }

            // si no hay radio seleccionado (por cualquier razón)
            const checked = document.querySelector('#paso3 input[name="horario"]:checked');
            if (!checked) {
                e.preventDefault();
                alert("Selecciona un horario válido para continuar.");
            }
        });
    }

    aplicarFiltroSlots(1, selectedDayByStep[1]);
    aplicarFiltroSlots(3, selectedDayByStep[3]);
    actualizarSeleccionHorario();
});

