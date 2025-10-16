$('#pdfUploadForm').on('submit', function(e) {
    e.preventDefault();

    let formData = new FormData(this);

    // Mostrar SweetAlert mientras se procesa
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
            // Cerrar SweetAlert cuando termine
            Swal.close();

            if ($.fn.DataTable.isDataTable('#producto_data')) {
                $('#producto_data').DataTable().clear().destroy();
            }

            let tableData = [];

            // $.each(response, function(nombreArchivo, data) {
            //     console.log("Archivo:", nombreArchivo);
            //     console.log("Resumen:", data.resumen);
            //     console.log("Detalles:", data.detalles);
            // });
            
            // $.each(response, function(nombreArchivo, mensajes) {

            //     let mensajesUnidos = '<ul>' + mensajes.resumen.map(msg => `<li>${msg}</li>`).join('') + '</ul>';
                
            //     let detallesLista = '<ul>';
            //     for (const [clave, valor] of Object.entries(mensajes.detalles)) {
            //         detallesLista += `<li><strong>${clave}:</strong> ${valor}</li>`;
            //     }
            //     detallesLista += '</ul>';

            //     tableData.push([nombreArchivo, mensajesUnidos, detallesLista, ""]);
            // });

            $.each(response, function(nombreArchivo, mensajes) {
                // Crear la lista de mensajes de resumen (revisión técnica)
                let mensajesUnidos = '<ul>' + mensajes.resumen.map(msg => `<li>${msg}</li>`).join('') + '</ul>';
                
                // Crear el listado de detalles en formato de tabla
                let detalles = {
                    "tamaño": mensajes.detalles.tamaño,
                    "pdf_valido": mensajes.detalles.pdf_valido,
                    "sin_contraseña": mensajes.detalles.sin_contraseña,
                    "sin_formularios": mensajes.detalles.sin_formularios,
                    "sin_javascript": mensajes.detalles.sin_javascript,
                    "sin_objetos_incrustados": mensajes.detalles.sin_objetos_incrustados,
                    "imagenes": mensajes.detalles.imagenes,
                    "imagenes_grayscale": mensajes.detalles.imagenes_grayscale,
                    "dpi_imagenes": mensajes.detalles.dpi_imagenes
                };

                let size = detalles.tamaño;

                // Armar el array de datos para la tabla
                tableData.push([nombreArchivo, size , mensajesUnidos, ""]);
            });

            $('#producto_data').DataTable({
                "aProcessing": true,
                "aServerSide": true,
                dom: 'frtip',
                data: tableData,
                columns: [
                    { title: "Archivo" },
                    { title: "Tamaño" },
                    { title: "Mensaje" },
                    { title: "Acciones" }
                ],
                "autoWidth": false,
                "bDestroy": true,
                "responsive": true,
                "bInfo": true,
                "iDisplayLength": 10,
                "order": [[0, "asc"]],
                "language": {
                    "sProcessing":     "Procesando...",
                    "sLengthMenu":     "Mostrar _MENU_ registros",
                    "sZeroRecords":    "No se encontraron resultados",
                    "sEmptyTable":     "Ningún dato disponible en esta tabla",
                    "sInfo":           "Mostrando un total de _TOTAL_ registros",
                    "sInfoEmpty":      "Mostrando un total de 0 registros",
                    "sInfoFiltered":   "(filtrado de un total de _MAX_ registros)",
                    "sSearch":         "Buscar:",
                    "oPaginate": {
                        "sFirst":    "Primero",
                        "sLast":     "Último",
                        "sNext":     "Siguiente",
                        "sPrevious": "Anterior"
                    },
                    "oAria": {
                        "sSortAscending":  ": Activar para ordenar la columna de manera ascendente",
                        "sSortDescending": ": Activar para ordenar la columna de manera descendente"
                    }
                }
            });
        },
        error: function(xhr) {
             Swal.fire({
                 icon: 'error',
                 title: 'Error',
                 text: 'Ocurrió un error: ' + xhr.responseText
             });
        }
/*         error: function(xhr) {
            let responseText = xhr.responseText;

            // Si detectamos un warning específico de PHP sobre max_file_uploads
            if (responseText.includes("Maximum number of allowable file uploads has been exceeded")) {
                Swal.fire({
                    icon: 'error',
                    title: 'Demasiados archivos',
                    text: '❌ Has intentado subir más archivos de los permitidos por el servidor (PHP). Por favor, sube menos archivos.'
                });
                return;
            }

            // Intentar parsear JSON por si es otro tipo de error controlado
            try {
                const json = JSON.parse(responseText);
                if (json.error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error del servidor',
                        html: json.error.map(e => `<p>${e}</p>`).join('')
                    });
                } else {
                    throw new Error("Sin mensaje de error en JSON.");
                }
            } catch (e) {
                // Fallback: error genérico
                Swal.fire({
                    icon: 'error',
                    title: 'Error inesperado',
                    text: 'No se pudo procesar la respuesta del servidor.'
                });
            }
        } */
    });
}); 

/* $('#pdfUploadForm').on('submit', function(e) {
    e.preventDefault(); // Evita el envío normal del formulario

    let formData = new FormData(this); // Crea un objeto FormData con el archivo

    $.ajax({
        url: 'verificacion.php',
        type: 'POST',
        data: formData,
        processData: false, // Necesario para enviar FormData
        contentType: false, // Necesario para enviar FormData
        dataType: 'json',   // Espera respuesta JSON del servidor
        success: function(response) {
            console.log('Éxito:', response);
        },
        error: function(xhr) {
            console.error('Error:', xhr.responseText);
        }
    });
}); */
