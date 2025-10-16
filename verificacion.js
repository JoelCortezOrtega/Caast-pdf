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
            console.log('Éxito:', response);
        },
        error: function(xhr) {
            console.error('Error:', xhr.responseText);
        }
    });
});
