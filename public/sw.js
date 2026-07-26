/* LordSerial service worker — Web Push */
self.addEventListener('push', function (event) {
    var data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'LordSerial', body: event.data ? event.data.text() : '' };
    }

    var title = data.title || 'LordSerial';
    var options = {
        body: data.body || '',
        icon: data.icon || '/favicon.ico',
        badge: data.badge || data.icon || '/favicon.ico',
        tag: data.tag || 'lordserial-episode',
        data: {
            url: data.url || '/',
        },
        renotify: true,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if ('focus' in client) {
                    if (client.url.indexOf(self.location.origin) === 0) {
                        client.navigate(url);
                        return client.focus();
                    }
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});
