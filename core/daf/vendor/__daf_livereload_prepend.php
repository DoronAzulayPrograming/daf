<?php
ob_start(function ($buffer) {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (stripos($accept, 'text/html') === false) {
        return $buffer;
    }

    $script = <<<HTML
<script>
(function () {
    try {
        const socket = new WebSocket("ws://127.0.0.1:8181");
        socket.onmessage = (event) => {
            if (event.data === "reload") {
                window.location.reload();
            }
        };
    } catch (e) {
        console.error("[Daf LiveReload] WebSocket error", e);
    }
})();
</script>
HTML;

    if (stripos($buffer, '</body>') !== false) {
        return preg_replace('~</body>~i', $script . '</body>', $buffer, 1);
    }

    return $buffer . $script;
});