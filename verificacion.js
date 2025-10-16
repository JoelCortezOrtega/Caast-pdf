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

            $.each(response, function(nombreArchivo, mensajes) {
                let mensajesUnidos = '<ul>' + mensajes.map(msg => `<li>${msg}</li>`).join('') + '</ul>';
                tableData.push([nombreArchivo, mensajesUnidos]);
            });

            $('#producto_data').DataTable({
                "aProcessing": true,
                "aServerSide": true,
                dom: 'frtip',
                data: tableData,
                columns: [
                    { title: "Archivo" },
                    { title: "Mensaje" }
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
    });
});
