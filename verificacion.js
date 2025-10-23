$(document).ready(function () {
  const tabla = $('#producto_data').DataTable({
    //serverSide: false, // 🔥 sin esto, el mensaje de emptyTable no funciona
    data: [], // 👈 Importante: array vacío para que muestre "emptyTable"
    columns: [
      { title: "Archivo" },
      { title: "Tamaño" },
      { title: "Mensaje" },
      { title: "Acciones" }
    ],
    language: {
      emptyTable: "No hay archivos seleccionados."
    },
    paging: false,
    searching: false,
    info: false
  });
});

$('#pdfUploadForm').on('submit', function(e) {
    e.preventDefault();

    let formData = new FormData(this);

    Swal.fire({
        title: 'Procesando...',
        html: 'Por favor espera mientras se carga el archivo.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: 'verificacion.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            Swal.close();
            // Cerrar SweetAlert cuando termine
            if ($.fn.DataTable.isDataTable('#producto_data')) {
                $('#producto_data').DataTable().clear().destroy();
            }

            let tableData = [];

            $.each(response, function(nombreArchivo, mensajes) {
                // Filtrar avisos (puedes ajustar esta lógica)
                    let avisos = mensajes.resumen.filter(msg => {
                        return msg.includes('⚠️') || /no cumple|error|falla|no /i.test(msg);
                    });

                    let mensajeColumna = '';
                    if (avisos.length === 0) {
                        mensajeColumna = `<span style="background-color:#d4edda; color:#155724; border-radius: 12px; padding: 4px 10px; font-weight: 600; font-size: 13px; display:inline-block;">
                            &#10003; Cumple
                        </span>`;
                    } else {
                        mensajeColumna = `<span style="background-color:#fff3cd; color:#856404; border-radius: 12px; padding: 4px 10px; font-weight: 600; font-size: 13px; display:inline-block;">
                            ${avisos.length} aviso(s)
                        </span>`;
                    }

                // Guardar detalles en un objeto para usar luego en el modal
                let detalles = {
                    pdf_valido: mensajes.detalles.pdf_valido,
                    tamaño: mensajes.detalles.tamaño,
                    sin_contraseña: mensajes.detalles.sin_contraseña,
                    sin_formularios: mensajes.detalles.sin_formularios,
                    sin_javascript: mensajes.detalles.sin_javascript,
                    sin_objetos_incrustados: mensajes.detalles.sin_objetos_incrustados,
                    imagenes: mensajes.detalles.imagenes,
                    imagenes_grayscale: mensajes.detalles.imagenes_grayscale,
                    dpi_imagenes: mensajes.detalles.dpi_imagenes
                };

                // Creamos los botones con data-detalles (stringificado JSON)
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
                    mensajes.detalles.tamaño,
                    mensajeColumna,
                    botonDetalles
                ]);
            });

            let tabla = $('#producto_data').DataTable({
                aProcessing: true,
                aServerSide: true,
                dom: 'frtip',
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
                bInfo: true,
                aProcessing: true,
                aServerSide: false,
                iDisplayLength: 10,
                order: [[0, "asc"]],
                language: {
                    sProcessing: "Procesando...",
                    sLengthMenu: "Mostrar _MENU_ registros",
                    sZeroRecords: "No se encontraron resultados",
                    sEmptyTable: "Ningún dato disponible en esta tabla",
                    sInfo: "Mostrando un total de _TOTAL_ registros",
                    sInfoEmpty: "Mostrando un total de 0 registros",
                    sInfoFiltered: "(filtrado de un total de _MAX_ registros)",
                    sSearch: "Buscar:",
                    oPaginate: {
                        sFirst: "Primero",
                        sLast: "Último",
                        sNext: "Siguiente",
                        sPrevious: "Anterior"
                    },
                    oAria: {
                        sSortAscending: ": Activar para ordenar la columna de manera ascendente",
                        sSortDescending: ": Activar para ordenar la columna de manera descendente"
                    }
                }
            });

            // Evento click para el botón detalles
            $('#producto_data tbody').off('click', '.btn-detalle').on('click', '.btn-detalle', function() {
                let detalles = $(this).data('detalles');
                let nombreArchivo = $(this).data('nombre');

                // Construimos el HTML para SweetAlert
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
                    confirmButtonText: 'Cerrar',
                    customClass: {
                        popup: 'swal2-border-radius'
                    }
                });
            });

            // Función para dar formato con badges verdes o rojos
            function formatBadge(valor) {
                if (typeof valor === "string") {
                    if (/^(sí|si|true|ok|✔️|verdadero)$/i.test(valor.trim())) {
                        return `<span style="color:green;font-weight:bold;">✔️ ${valor}</span>`;
                    } else if (/^(no|false|❌|falso)$/i.test(valor.trim())) {
                        return `<span style="color:red;font-weight:bold;">❌ ${valor}</span>`;
                    }
                }
                return `<span>${valor}</span>`;
            }
        },
        error: function(xhr) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Ocurrió un error: ' + xhr.responseText
            });
        }
    });
});


