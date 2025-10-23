// 🔹 Función global para formatear badges en el modal de detalles
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

$(document).ready(function () {
    $('#producto_data').DataTable({
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
    const batchSize = 10;
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
                timeout: 60000
            });

            if (!response || typeof response !== 'object') {
                console.warn(`⚠️ Respuesta inesperada en el lote ${currentBatch}:`, response);
                continue;
            }

            Object.assign(allResults, response);

        } catch (err) {
            console.error(`❌ Error en el lote ${currentBatch}`, err);
            $('#statusText').text(`⚠️ Error en lote ${currentBatch}, se omitió.`);
            continue;
        }

        const progreso = Math.min(((i + batchSize) / files.length) * 100, 100);
        $('#progressBar').css('width', `${progreso}%`);
        $('#progressText').text(`${Math.round(progreso)}%`);
    }

    Swal.close();
    mostrarResultadosEnTabla(allResults);
}

);

function mostrarResultadosEnTabla(response) {
    if ($.fn.DataTable.isDataTable('#producto_data')) {
        $('#producto_data').DataTable().clear().destroy();
    }

    let tableData = [];

    $.each(response, function(nombreArchivo, mensajes) {
        if (!mensajes) {
            console.warn("Respuesta inesperada para:", nombreArchivo, mensajes);
            return;
        }

        if (!mensajes.detalles) {
            mensajes.detalles = {
                tamaño: obtenerTamañoArchivo(nombreArchivo),
                pdf_valido: "No verificado",
                sin_contraseña: "-",
                sin_javascript: "-",
                sin_formularios: "-",
                sin_objetos_incrustados: "-",
                imagenes_grayscale: "-",
                dpi_imagenes: "-"
            };
        }

        // 🔹 Filtrar errores y warnings
        let erroresCriticos = mensajes.resumen ? mensajes.resumen.filter(msg =>
            /error|falla|no cumple/i.test(msg)
        ) : [];
        let warnings = mensajes.resumen ? mensajes.resumen.filter(msg =>
            /⚠️|advertencia|warning/i.test(msg)
        ) : [];

        let mensajeColumna = "";

        if (erroresCriticos.length === 0 && warnings.length === 0) {
            mensajeColumna = `<span style="background-color:#d4edda; color:#155724; border-radius: 12px;
                                         padding: 4px 10px; font-weight: 600; font-size: 13px; display:inline-block;">
                                    ✓ Cumple
                                  </span>`;
        } else {
            let erroresLista = erroresCriticos.map(a => `<li style="color:red;font-weight:bold;">${a}</li>`).join("");
            let warningsLista = warnings.map(a => `<li style="color:#856404;font-weight:bold;">${a}</li>`).join("");
            mensajeColumna = `
                <div style="background-color:#fff3cd; color:#856404; border-radius: 12px;
                            padding: 6px 10px; font-weight: 500; font-size: 13px;">
                    ${erroresCriticos.length > 0 ? `<strong style="color:red;">❌ ${erroresCriticos.length} error(es)</strong>` : ""}
                    ${warnings.length > 0 ? `<strong>⚠️ ${warnings.length} advertencia(s)</strong>` : ""}
                    <details style="margin-top:5px;">
                        <summary style="cursor:pointer; color:#0056b3;">Ver detalles</summary>
                        <ul style="margin:5px 0 0 15px; font-size:12px; color:#333;">
                            ${erroresLista}${warningsLista}
                        </ul>
                    </details>
                </div>`;
        }

        let botonDetalles = `
            <button 
                class="btn btn-sm btn-info btn-detalle" title="Detalles"
                data-detalles='${JSON.stringify(mensajes.detalles).replace(/'/g, "&apos;")}'
                data-nombre='${nombreArchivo}'
                type="button"
            >
                <i class="fas fa-info-circle"></i> Detalles
            </button>
        `;

        tableData.push([
            nombreArchivo,
            mensajes.detalles.tamaño || "Desconocido",
            mensajeColumna,
            botonDetalles
        ]);
    });

    let tabla = $('#producto_data').DataTable({
        data: tableData,
        columns: [
            { title: "Archivo" },
            { title: "Tamaño" },
            { title: "Resultado / Errores" },
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
            sInfo: "Mostrando un total de _TOTAL_ registros"
        }
    });

    $('#producto_data tbody').off('click', '.btn-detalle').on('click', '.btn-detalle', function() {
        let nombreArchivo = $(this).data('nombre');
        let detalles = $(this).data('detalles');

        if (!detalles) {
            try {
                detalles = JSON.parse($(this).attr('data-detalles').replace(/&apos;/g, "'"));
            } catch {
                Swal.fire('Error', 'No se pudieron recuperar los detalles del archivo.', 'error');
                return;
            }
        }

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

function obtenerTamañoArchivo(nombre) {
    const files = $('#pdfFiles')[0].files;
    for (let f of files) {
        if (f.name === nombre) {
            let sizeMB = (f.size / (1024 * 1024)).toFixed(2);
            return `${sizeMB} MB`;
        }
    }
    return "Desconocido";
}




