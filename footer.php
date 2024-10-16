<!-- If you like, you can put a page footer (something that should show up at
     the bottom of every page, such as helpful links, layout, etc.) here. -->


<!-- Bootstrap core JavaScript -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>

</body>
<script>
    htmx.on("htmx:afterRequest", function (evt) {
        let res = JSON.parse(evt.detail.xhr.response);
        const timeout = 1000;

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