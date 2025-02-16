<script>
    htmx.on("htmx:afterRequest", function (evt) {
        const timeout = 1000;
        try {
            res = JSON.parse(evt.detail.xhr.response);
        } catch (e) {
            Swal.fire({
                heightAuto: false,
                icon: 'error',
                title: 'System error',
                text: 'An error occurred while processing the response.'
            });
            return;
        }

        if (res.ret === 1) {
            Swal.fire({
                heightAuto: false,
                icon: 'success',
                title: 'Success',
                text: res.msg,
                showConfirmButton: true,
                timer: 2000,
                timerProgressBar: true,
                allowOutsideClick: false,
            });
        } else {
            Swal.fire({
                heightAuto: false,
                icon: 'error',
                title: 'Error',
                text: res.msg
            });
        }

        if (evt.detail.xhr.getResponseHeader('HX-Redirect')) {
            return;
        }
        if (evt.detail.xhr.getResponseHeader('HX-Refresh')) {
            setTimeout(function () {
                location.reload();
            }, timeout);
        }
    });
</script>
</html>