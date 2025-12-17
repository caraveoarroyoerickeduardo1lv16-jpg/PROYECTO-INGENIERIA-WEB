function mostrarPaso(num) {
  ['paso1', 'paso2', 'paso3'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    if (id === 'paso' + num) el.classList.remove('hidden');
    else el.classList.add('hidden');
  });
}

// Convierte etiqueta tipo "1pm-2pm" a hora inicio 24h (13)
function parseStartHour(label) {
  const m = String(label).trim().match(/^(\d{1,2})(am|pm)\s*-/i);
  if (!m) return null;
  let h = parseInt(m[1], 10);
  const ap = m[2].toLowerCase();
  if (ap === 'pm' && h !== 12) h += 12;
  if (ap === 'am' && h === 12) h = 0;
  return h;
}

// Filtra slots según día (0=hoy,1=mañana,2=pasado)
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
      // HOY: si ya es después de las 21:00, NO hay hoy.
      if (currentHour >= 21) {
        show = false;
      } else {
        // mínimo 2 horas, y no pasar de cierre (21)
        if (startHour < limitHour || startHour >= 21) show = false;
      }
    } else {
      show = true;
    }

    if (show) {
      slot.style.display = 'flex';
      visibles++;
      const r = slot.querySelector('input[type="radio"]');
      if (!firstRadio && r && !r.disabled) firstRadio = r;
    } else {
      slot.style.display = 'none';
    }
  });

  if (statusHoy) {
    if (selectedDay === 0 && visibles === 0) {
      statusHoy.textContent = 'Hoy: Agotado';
      statusHoy.style.display = 'block';
    } else {
      statusHoy.style.display = 'none';
    }
  }

  // Paso 3: si hay radios visibles, auto-seleccionar el primero visible
  if (stepId === 3) {
    const btnPago = document.getElementById('btnContinuarPago');
    if (btnPago) btnPago.disabled = (selectedDay === 0 && visibles === 0);

    if (firstRadio) {
      firstRadio.checked = true;
      actualizarSeleccionHorario();
    }
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

  const hiddenDia = document.getElementById('dia_envio'); // ✅ hidden en paso 3
  const selectedDayByStep = { 1: 0, 3: 0 };

  const dayButtons = document.querySelectorAll('.day-button');
  dayButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const step = parseInt(btn.dataset.step, 10);
      const dia  = parseInt(btn.dataset.dia, 10);

      selectedDayByStep[step] = dia;

      const cont = document.getElementById('paso' + step);
      cont.querySelectorAll('.day-button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      aplicarFiltroSlots(step, dia);

      // ✅ Si estamos en paso 3, guardar el día en hidden input
      if (step === 3 && hiddenDia) hiddenDia.value = String(dia);
    });
  });

  const radiosHorario = document.querySelectorAll('#paso3 input[name="horario"]');
  radiosHorario.forEach(r => r.addEventListener('change', actualizarSeleccionHorario));

  // inicial
  aplicarFiltroSlots(1, selectedDayByStep[1]);
  aplicarFiltroSlots(3, selectedDayByStep[3]);
  if (hiddenDia) hiddenDia.value = String(selectedDayByStep[3]);
  actualizarSeleccionHorario();
});


