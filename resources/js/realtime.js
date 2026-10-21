import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { reactive } from 'vue';
import { api } from './api';
import { emitLive } from './live';

/**
 * Realtime connection to Laravel Reverb. Optional: when the server has no
 * Reverb configured (or it is down), everything keeps working through polling.
 */
export const realtime = reactive({ connected: false });

let echo = null;
let channelName = null;

export function connectRealtime(config, familyId, onAnyEvent) {
    disconnectRealtime();
    if (!config || !familyId) return;

    window.Pusher = Pusher;
    echo = new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        // Private channel auth goes through our session-authenticated axios instance.
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                api.post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name }, { baseURL: '/' })
                    .then((r) => callback(null, r.data))
                    .catch((e) => callback(e, null));
            },
        }),
    });

    const connection = echo.connector.pusher.connection;
    connection.bind('state_change', ({ current }) => (realtime.connected = current === 'connected'));

    channelName = `family.${familyId}`;
    echo.private(channelName).listen('.updated', (event) => {
        emitLive(event.type, event);
        onAnyEvent?.(event);
    });
}

export function disconnectRealtime() {
    if (echo) {
        if (channelName) echo.leave(channelName);
        echo.disconnect();
    }
    echo = null;
    channelName = null;
    realtime.connected = false;
}
