// Connects to the Node Socket.IO bridge using a short-lived token from Laravel,
// joins the authorized rooms, and refreshes the bell (+ toasts) on live events.
// Degrades silently if the socket server / client is unavailable.
(function ($) {
    if (!window.io) return;

    function connect() {
        $.getJSON('/realtime/token').done(function (res) {
            const d = res && res.data;
            if (!d || !d.token) return;

            const socket = window.io({ path: '/socket.io/', auth: { token: d.token }, transports: ['websocket', 'polling'] });

            socket.on('notification', function (p) {
                if (window.loadNotifs) window.loadNotifs();
                if (window.toast) window.toast((p && p.title) || 'New notification', 'info');
            });

            // Re-issue the short-lived token before it expires (~5 min).
            setInterval(function () {
                $.getJSON('/realtime/token').done(function (r) {
                    if (r && r.data && r.data.token) socket.auth = { token: r.data.token };
                });
            }, 240000);
        });
    }

    $(connect);
})(jQuery);
