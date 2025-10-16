$('#pdfUploadForm').on('submit', function(e) {
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
            const contenedor = $('#resultados');
            contenedor.empty(); // Limpia resultados anteriores

            // Itera por cada archivo analizado
            $.each(response, function(nombreArchivo, mensajes) {
                const bloque = $('<div class="archivo"></div>');
                bloque.append(`<h3>${nombreArchivo}</h3>`);

                const lista = $('<ul></ul>');

                mensajes.forEach(function(msg) {
                let clase = '';
                    if (msg.includes('✅')) clase = 'ok';
                    else if (msg.includes('❌')) clase = 'error';
                    else if (msg.includes('⚠️')) clase = 'alerta';

                lista.append(`<li class="${clase}">${msg}</li>`);
                });

                bloque.append(lista);
                contenedor.append(bloque);
            });
        },
        error: function(xhr) {
            console.error('Error:', xhr.responseText);
        }
    });
});
