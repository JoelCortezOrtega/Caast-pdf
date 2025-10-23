$(document).ready(function () {
  const tabla = $('#producto_data').DataTable({
    data: [],
    columns: [
      { title: "Archivo" },
      { title: "Tamaño" },
      { title: "Mensaje" },
      { title: "Acciones" }
    ],
    language: { emptyTable: "No hay archivos seleccionados." },
    paging: false,
    searching: false,
    info: false
  });
});

$('#pdfUploadForm').on('submit', async function (e) {
  e.preventDefault();

  const files = $('#pdfFiles')[0].files;
  const batchSize = 10; // 🔹 Procesar 10 archivos por vez
  const totalBatches = Math.ceil(files.length / batchSize);
  let allResults = {};

  if (files.length === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Sin archivos',
      text: 'Por favor selecciona al menos un archivo PDF.'
    });
    return;
  }

  // 🔹 Mostrar alerta con barra de progreso
  Swal.fire({
    title: 'Procesando archivos...',
    html: `
      <div id="progressContainer" style="width: 100%; background-color: #eee; border-radius: 8px; margin-top: 15px;">
        <div id="progressBar" style="width: 0%; background-color: #4caf50; height: 20px; border-radius: 8px;"></div>
      </div>
      <p id="progressText" style="margin-top: 10px; font-weight: 500;">0%</p>
      <p id="statusText" style="margin-top: 5px; color: #555; font-size: 13px;">Iniciando...</p>
    `,
    allowOutsideClick: false,
    didOpen: () => Swal.showLoading()
  });

  // 🔹 Procesar lotes uno por uno
  for (let i = 0; i < files.length; i += batchSize) {
    const batch = Array.from(files).slice(i, i + batchSize);
    const formData = new FormData();
    batch.forEach(f => formData.append('pdfFiles[]', f));

    const currentBatch = Math.floor(i / batchSize) + 1;

    try {
      $('#statusText').text(`Procesando lote ${currentBatch} de ${totalBatches}...`);

      const response = await $.ajax({
        url: 'verificacion.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        timeout: 60000 // ⏱️ 60s por lote
      });

      // 🔹 Validar respuesta
      if (!response || typeof response !== 'object') {
        console.warn(`⚠️ Respuesta inesperada en el lote ${currentBatch}:`, response);
        continue; // Saltar lote y seguir con el siguiente
      }

      Object.assign(allResults, response);

    } catch (err) {
      console.error(`❌ Error en el lote ${currentBatch}`, err);

      // 🔹 Continuar con el siguiente lote, pero mostrar aviso visual
      $('#statusText').text(`⚠️ Error en lote ${currentBatch}, se omitió.`);
      continue;
    }

    // 🔹 Actualizar barra de progreso
    const progreso = Math.min(((i + batchSize) / files.length) * 100, 100);
    $('#progressBar').css('width', `${progreso}%`);
    $('#progressText').text(`${Math.round(progreso)}%`);
  }

  Swal.close();
  mostrarResultadosEnTabla(allResults);
});

function mostrarResultadosEnTabla(response) {
  if ($.fn.DataTable.isDataTable('#producto_data')) {
    $('#producto_data').DataTable().clear().destroy();
  }

  let tableData = [];

  $.each(response, function (nombreArchivo, mensajes) {
    if (!mensajes) {
    console.warn("Respuesta inesperada para:", nombreArchivo, mensajes);
    return;
    }

    // 🔹 Si no hay 'detalles', generamos valores por defecto
    if (!mensajes.detalles) {
    mensajes.detalles = {
        tamaño: "Desconocido",
        pdf_valido: "No evaluado",
        sin_contraseña: "-",
        sin_javascript: "-",
        sin_formularios: "-",
        sin_objetos_incrustados: "-",
        imagenes_grayscale: "-",
        dpi_imagenes: "-"
    };
    }

    let avisos = mensajes.resumen.filter(msg =>
      msg.includes('⚠️') || /no cumple|error|falla|no /i.test(msg)
    );

    let mensajeColumna = avisos.length === 0
      ? `<span style="background-color:#d4edda; color:#155724; border-radius: 12px; padding: 4px 10px; font-weight: 600; font-size: 13px; display:inline-block;">
          ✓ Cumple
        </span>`
      : `<span style="background-color:#fff3cd; color:#856404; border-radius: 12px; padding: 4px 10px; font-weight: 600; font-size: 13px; display:inline-block;">
          ${avisos.length} aviso(s)
        </span>`;

    let detalles = mensajes.detalles;

    let botonDetalles = `
      <button 
        class="btn btn-sm btn-info btn-detalle" title="Detalles"
        data-detalles='${JSON.stringify(detalles).replace(/'/g, "&apos;")}'
        data-nombre='${nombreArchivo}'
        type="button"
      >
        <i class="fas fa-info-circle"></i> Detalles
      </button>
      <button 
        class="btn btn-sm btn-danger btn-eliminar" title="Eliminar"
        data-nombre='${nombreArchivo}'
        type="button"
      >
        <i class="fa fa-times"></i> Eliminar
      </button>
    `;

    tableData.push([
      nombreArchivo,
      detalles.tamaño,
      mensajeColumna,
      botonDetalles
    ]);
  });

  // 🔹 Inicializar tabla
  let tabla = $('#producto_data').DataTable({
    data: tableData,
    columns: [
      { title: "Archivo" },
      { title: "Tamaño" },
      { title: "Revisión técnica" },
      { title: "Acciones", orderable: false }
    ],
    autoWidth: false,
    bDestroy: true,
    responsive: true,
    paging: false,
    searching: false,
    info: true,
    order: [[0, "asc"]],
    language: {
      sEmptyTable: "Ningún dato disponible en esta tabla",
      sInfo: "Mostrando un total de _TOTAL_ registros",
    }
  });

  // 🔹 Mostrar detalles en SweetAlert
  $('#producto_data tbody').off('click', '.btn-detalle').on('click', '.btn-detalle', function () {
    let detalles = $(this).data('detalles');
    let nombreArchivo = $(this).data('nombre');

    let htmlDetalles = `
      <div style="text-align: left; font-size: 14px;">
        <p><strong>Archivo:</strong> ${nombreArchivo}</p>
        <p><strong>Resultado</strong></p>
        <ul>
          <li><strong>PDF válido:</strong> ${formatBadge(detalles.pdf_valido)}</li>
          <li><strong>Tamaño ≤ 3 MB:</strong> ${formatBadge(detalles.tamaño)}</li>
          <li><strong>Sin contraseña:</strong> ${formatBadge(detalles.sin_contraseña)}</li>
          <li><strong>Sin JavaScript:</strong> ${formatBadge(detalles.sin_javascript)}</li>
          <li><strong>Sin formularios:</strong> ${formatBadge(detalles.sin_formularios)}</li>
          <li><strong>Sin objetos incrustados:</strong> ${formatBadge(detalles.sin_objetos_incrustados)}</li>
          <li><strong>Escala de grises a 8 bits:</strong> ${formatBadge(detalles.imagenes_grayscale)}</li>
          <li><strong>Resolución 300 DPI:</strong> ${formatBadge(detalles.dpi_imagenes)}</li>
        </ul>
      </div>
    `;

    Swal.fire({
      title: `Detalles para: ${nombreArchivo}`,
      html: htmlDetalles,
      width: '600px',
      confirmButtonText: 'Cerrar'
    });
  });
}

// 🔹 Formateo de badges visuales
function formatBadge(valor) {
  if (typeof valor === "string") {
    if (/^(sí|si|true|ok|✔️|✅|verdadero)$/i.test(valor.trim())) {
      return `<span style="color:green;font-weight:bold;">✔️ ${valor}</span>`;
    } else if (/^(no|false|❌|falso)$/i.test(valor.trim())) {
      return `<span style="color:red;font-weight:bold;">❌ ${valor}</span>`;
    }
  }
  return `<span>${valor}</span>`;
}



