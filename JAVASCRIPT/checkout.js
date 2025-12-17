function mostrarPaso(num) {
    ['paso1', 'paso2', 'paso3'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        if (id === 'paso' + num) el.classList.remove('hidden');
        else el.classList.add('hidden');
    });
}

// Convierte "1pm-2pm" -> 13
function parseStartHour(label) {
    if (!label) return null;
    const m = label.trim().match(/^(\d{1,2})(am|pm)\s*-\s*(\d{1,2})(am|pm)$/i);
    if (!m) return null;
    let h = parseInt(m[1], 10);
    const ap = m[2].toLowerCase();
    if (h === 12) h = 0;
    if (ap === 'pm') h += 12;
    return h;
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
        const r = slot.querySelector('input[type="radio"]');

        let show = true;

        if (selectedDay === 0) {
            // HOY: si ya cerramos (>=21) o no cumple ventana de 2h, se oculta
            if (currentHour >= 21) show = false;
            if (startHour < limitHour || startHour >= 21) show = false;
        } else {
            show = true;
        }

        if (show) {
            slot.style.display = 'flex';
            visibles++;
            if (r) r.disabled = false;
            if (!firstRadio && r) firstRadio = r;
        } else {
            slot.style.display = 'none';

            // ✅ CLAVE: deshabilitar y desmarcar radios ocultos
            if (r) {
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

    // Si estamos en el paso3 y no hay radios visibles, limpiar resumen y bloquear botón
    if (stepId === 3) {
        const btnContinuar = document.getElementById('btnContinuarPago');
        if (btnContinuar) btnContinuar.disabled = (visibles === 0);

        if (visibles === 0) {
            const resumen = document.getElementById('resumenHorario');
            if (resumen) resumen.textContent = 'Selecciona un horario · $49.00';
            return;
        }
    }

    // Si hay uno visible, marcar el primero y actualizar
    if (stepId === 3 && firstRadio) {
        firstRadio.checked = true;
        actualizarSeleccionHorario();
    }
}

function actualizarSeleccionHorario() {
    const resumen = document.getElementById('resumenHorario');
    if (!resumen) return;

    const cont = document.getElementById('paso3');
    const slots = cont.querySelectorAll('.slot');
    slots.forEach(s => s.classList.remove('selected'));

    const checked = cont.querySelector('input[name="horario"]:checked');
    if (!checked) return;

    const slot = checked.closest('.slot');
    if (!slot) return;

    slot.classList.add('selected');
    const titulo = slot.querySelector('.slot-title').textContent.trim();
    const precio = slot.querySelector('.slot-price').textContent.trim();
    resumen.textContent = `${titulo} · ${precio}`;
}

document.addEventListener('DOMContentLoaded', () => {
    const diaEnvioInput = document.getElementById('diaEnvio');

    const btnAgregarDireccion = document.getElementById('btnAgregarDireccion');
    if (btnAgregarDireccion) {
        btnAgregarDireccion.addEventListener('click', (e) => {
            e.preventDefault();
            mostrarPaso(2);
        });
    }

    const btnVolverPaso1 = document.getElementById('btnVolverPaso1');
    if (btnVolverPaso1) btnVolverPaso1.addEventListener('click', () => mostrarPaso(1));

    const btnVolverPaso2 = document.getElementById('btnVolverPaso2');
    if (btnVolverPaso2) btnVolverPaso2.addEventListener('click', () => mostrarPaso(2));

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

            // ✅ Guardar día seleccionado para validación servidor
            if (step === 3 && diaEnvioInput) diaEnvioInput.value = String(dia);

            aplicarFiltroSlots(step, dia);
        });
    });

    // Radios de horario
    const radiosHorario = document.querySelectorAll('#paso3 input[name="horario"]');
    radiosHorario.forEach(r => r.addEventListener('change', actualizarSeleccionHorario));

    // Inicial
    if (diaEnvioInput) diaEnvioInput.value = String(selectedDayByStep[3]);
    aplicarFiltroSlots(1, selectedDayByStep[1]);
    aplicarFiltroSlots(3, selectedDayByStep[3]);
    actualizarSeleccionHorario();
});

