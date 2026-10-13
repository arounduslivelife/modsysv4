require('dotenv').config();
const express = require('express');
const cors = require('cors');
const pino = require('pino');
const QRCode = require('qrcode');
const fs = require('fs');
const path = require('path');
const {
    default: makeWASocket,
    DisconnectReason,
    useMultiFileAuthState,
    fetchLatestBaileysVersion,
    Browsers
} = require('@whiskeysockets/baileys');

const app = express();
const PORT = process.env.PORT || 3000;
const API_KEY = process.env.API_KEY || 'modsysv4_secret_key_2026';
const SESSIONS_DIR = path.resolve(__dirname, process.env.SESSIONS_DIR || './sessions');

if (!fs.existsSync(SESSIONS_DIR)) {
    fs.mkdirSync(SESSIONS_DIR, { recursive: true });
}

app.use(cors());
app.use(express.json());

// API Key Authentication Middleware
const authMiddleware = (req, res, next) => {
    const key = req.headers['x-api-key'] || req.query.api_key;
    if (!key || key !== API_KEY) {
        return res.status(401).json({ status: false, message: 'Unauthorized: Invalid API Key' });
    }
    next();
};

// Global Sessions Map
const sessions = new Map();

const logger = pino({ level: 'info' });

async function initSession(sessionKey, forceReset = false) {
    const sessionPath = path.join(SESSIONS_DIR, sessionKey);

    if (forceReset) {
        if (sessions.has(sessionKey)) {
            const existing = sessions.get(sessionKey);
            try { if (existing.socket) existing.socket.end(); } catch (e) {}
            sessions.delete(sessionKey);
        }
        try { fs.rmSync(sessionPath, { recursive: true, force: true }); } catch (e) {}
    } else if (sessions.has(sessionKey)) {
        const existing = sessions.get(sessionKey);
        if (existing.status === 'connected' || (existing.status === 'scanning' && existing.qr)) {
            return existing;
        }
        if (existing.status === 'initializing') {
            return existing;
        }
    }

    // Load auth state
    const { state, saveCreds } = await useMultiFileAuthState(sessionPath);
    let version = [2, 3000, 1015901307];
    try {
        const v = await fetchLatestBaileysVersion();
        if (v && v.version) version = v.version;
    } catch (e) {
        console.warn('Failed to fetch latest Baileys version, using fallback:', e.message);
    }

    const sessionData = {
        key: sessionKey,
        socket: null,
        qr: null,
        status: 'initializing',
        phone: null,
        lastError: null,
        attempts: 0
    };

    sessions.set(sessionKey, sessionData);

    try {
        const sock = makeWASocket({
            version,
            logger,
            auth: state,
            browser: Browsers.macOS('Desktop'),
            connectTimeoutMs: 60000,
            defaultQueryTimeoutMs: 60000,
            keepAliveIntervalMs: 25000,
            syncFullHistory: false,
            generateHighQualityLinkPreview: false,
            emitOwnEvents: false
        });

        sessionData.socket = sock;

        sock.ev.on('creds.update', saveCreds);

        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;
            console.log(`[Session ${sessionKey}] connection.update:`, {
                connection,
                hasQr: !!qr,
                statusCode: lastDisconnect?.error?.output?.statusCode,
                error: lastDisconnect?.error?.message
            });

            if (qr) {
                sessionData.status = 'scanning';
                try {
                    sessionData.qr = await QRCode.toDataURL(qr);
                    console.log(`[Session ${sessionKey}] QR Code generated successfully`);
                } catch (err) {
                    console.error(`[Session ${sessionKey}] QR render error:`, err);
                    sessionData.qr = null;
                }
            }

            if (connection === 'close') {
                const statusCode = lastDisconnect?.error?.output?.statusCode;
                const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
                sessionData.lastError = lastDisconnect?.error?.message || `Disconnected (status ${statusCode})`;

                console.log(`[Session ${sessionKey}] Connection closed. StatusCode: ${statusCode}, shouldReconnect: ${shouldReconnect}`);

                if (statusCode === DisconnectReason.loggedOut) {
                    sessionData.status = 'disconnected';
                    sessionData.qr = null;
                    sessionData.phone = null;
                    try { fs.rmSync(sessionPath, { recursive: true, force: true }); } catch (e) {}
                    sessions.delete(sessionKey);
                } else if (statusCode === 515 || statusCode === DisconnectReason.restartRequired) {
                    console.log(`[Session ${sessionKey}] Scan berhasil! Memulai ulang socket untuk login resmi...`);
                    sessions.delete(sessionKey);
                    setTimeout(() => {
                        initSession(sessionKey, false).catch(err => console.error(err));
                    }, 1000);
                } else if (shouldReconnect) {
                    if (!sessionData.phone) {
                        sessionData.status = sessionData.qr ? 'scanning' : 'disconnected';
                    }
                    setTimeout(() => {
                        console.log(`[Session ${sessionKey}] Reconnecting...`);
                        initSession(sessionKey, false).catch(err => console.error(err));
                    }, 3000);
                } else {
                    sessionData.status = 'disconnected';
                    sessionData.qr = null;
                }
            } else if (connection === 'open') {
                sessionData.status = 'connected';
                sessionData.qr = null;
                const userJid = sock.user?.id || '';
                sessionData.phone = userJid.split(':')[0].replace(/[^0-9]/g, '');
                console.log(`[Session ${sessionKey}] Connected as ${sessionData.phone}`);
            }
        });

        return sessionData;
    } catch (err) {
        console.error(`[Session ${sessionKey}] Socket creation error:`, err);
        sessionData.status = 'disconnected';
        sessionData.lastError = err.message;
        return sessionData;
    }
}

// Health check
app.get('/health', (req, res) => {
    res.json({ status: true, message: 'modsysv4 WA Gateway is running', timestamp: new Date() });
});

// Create / Initialize Session
app.post('/sessions', authMiddleware, async (req, res) => {
    const { session_key } = req.body;
    if (!session_key) {
        return res.status(400).json({ status: false, message: 'session_key is required' });
    }

    try {
        const session = await initSession(session_key);
        res.json({
            status: true,
            session_key,
            connection_status: session.status,
            phone: session.phone
        });
    } catch (e) {
        res.status(500).json({ status: false, message: e.message });
    }
});

// Get QR Code with smart wait
app.get('/sessions/:key/qr', authMiddleware, async (req, res) => {
    const sessionKey = req.params.key;
    let session = sessions.get(sessionKey);

    // If no session or previously disconnected without pair, force clean session
    if (!session || (session.status === 'disconnected' && !session.phone) || (!session.qr && session.status !== 'connected' && session.status !== 'initializing')) {
        console.log(`[Session ${sessionKey}] Triggering clean session init for QR`);
        session = await initSession(sessionKey, true);
    }

    // Wait up to 10 seconds for QR event if not generated yet
    if (!session.qr && session.status !== 'connected') {
        for (let i = 0; i < 20; i++) {
            await new Promise(r => setTimeout(r, 500));
            session = sessions.get(sessionKey) || session;
            if (session.qr || session.status === 'connected') break;
        }
    }

    res.json({
        status: true,
        session_key: sessionKey,
        connection_status: session.status,
        qr: session.qr,
        phone: session.phone,
        last_error: session.lastError
    });
});

// Reset Session
app.post('/sessions/:key/reset', authMiddleware, async (req, res) => {
    const sessionKey = req.params.key;
    const session = await initSession(sessionKey, true);
    res.json({
        status: true,
        message: `Session ${sessionKey} reset successfully`,
        connection_status: session.status
    });
});

// Get Session Status
app.get('/sessions/:key/status', authMiddleware, async (req, res) => {
    const sessionKey = req.params.key;
    let session = sessions.get(sessionKey);

    if (!session) {
        const sessionPath = path.join(SESSIONS_DIR, sessionKey);
        if (fs.existsSync(sessionPath)) {
            session = await initSession(sessionKey);
        } else {
            return res.json({
                status: true,
                session_key: sessionKey,
                connection_status: 'disconnected',
                phone: null
            });
        }
    }

    res.json({
        status: true,
        session_key: sessionKey,
        connection_status: session.status,
        phone: session.phone,
        has_qr: !!session.qr
    });
});

// Delete / Logout Session
app.delete('/sessions/:key', authMiddleware, async (req, res) => {
    const sessionKey = req.params.key;
    const session = sessions.get(sessionKey);

    try {
        if (session && session.socket) {
            await session.socket.logout().catch(() => {});
            session.socket.end();
        }
    } catch (e) {}

    const sessionPath = path.join(SESSIONS_DIR, sessionKey);
    try {
        fs.rmSync(sessionPath, { recursive: true, force: true });
    } catch (e) {}

    sessions.delete(sessionKey);

    res.json({ status: true, message: `Session ${sessionKey} deleted successfully` });
});

// Send Message
app.post('/messages/send', authMiddleware, async (req, res) => {
    const { session_key, phone, message } = req.body;

    if (!session_key || !phone || !message) {
        return res.status(400).json({
            status: false,
            message: 'session_key, phone, and message are required'
        });
    }

    const session = sessions.get(session_key);
    if (!session || session.status !== 'connected' || !session.socket) {
        return res.status(400).json({
            status: false,
            message: `Session ${session_key} is not connected to WhatsApp`
        });
    }

    try {
        let cleanPhone = phone.replace(/[^0-9]/g, '');
        if (cleanPhone.startsWith('08')) {
            cleanPhone = '628' + cleanPhone.substring(2);
        }
        const jid = `${cleanPhone}@s.whatsapp.net`;

        // Send text message
        const result = await session.socket.sendMessage(jid, { text: message });

        res.json({
            status: true,
            message: 'Pesan berhasil dikirim',
            message_id: result?.key?.id,
            timestamp: new Date()
        });
    } catch (e) {
        res.status(500).json({
            status: false,
            message: 'Gagal mengirim pesan: ' + e.message
        });
    }
});

// Auto-restore previous sessions on startup (only if valid paired credentials exist)
fs.readdir(SESSIONS_DIR, (err, files) => {
    if (!err && files) {
        files.forEach((file) => {
            const fullPath = path.join(SESSIONS_DIR, file);
            if (fs.statSync(fullPath).isDirectory()) {
                const credsFile = path.join(fullPath, 'creds.json');
                if (fs.existsSync(credsFile)) {
                    initSession(file).catch(() => {});
                }
            }
        });
    }
});

// Network Diagnostics Endpoint
app.get('/diag', async (req, res) => {
    const dnsPromises = require('dns').promises;
    let WebSocket;
    try {
        WebSocket = require('ws');
    } catch (e) {
        try { WebSocket = require('@whiskeysockets/baileys/node_modules/ws'); } catch (e2) {}
    }

    const diag = { dns_v4: null, dns_v6: null, http_test: null, ws_test: null };

    try { diag.dns_v4 = await dnsPromises.resolve4('web.whatsapp.com'); } catch (e) { diag.dns_v4 = e.message; }
    try { diag.dns_v6 = await dnsPromises.resolve6('web.whatsapp.com'); } catch (e) { diag.dns_v6 = e.message; }

    diag.http_test = await new Promise((resolve) => {
        const req = https.get('https://web.whatsapp.com', {
            headers: { 'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36' },
            timeout: 7000
        }, (r) => {
            resolve({ statusCode: r.statusCode });
        });
        req.on('error', (e) => resolve({ error: e.message, code: e.code }));
        req.on('timeout', () => { req.destroy(); resolve({ error: 'timeout' }); });
    });

    if (WebSocket) {
        diag.ws_test = await new Promise((resolve) => {
            try {
                const ws = new WebSocket('wss://web.whatsapp.com/ws/chat', {
                    headers: {
                        'Origin': 'https://web.whatsapp.com',
                        'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36'
                    },
                    handshakeTimeout: 7000
                });
                ws.on('open', () => {
                    ws.close();
                    resolve('CONNECTED_SUCCESS');
                });
                ws.on('error', (err) => {
                    resolve({ error: err.message, code: err.code });
                });
                setTimeout(() => resolve('TIMEOUT_AFTER_8S'), 8000);
            } catch (e) {
                resolve({ catch: e.message });
            }
        });
    } else {
        diag.ws_test = 'WebSocket module not found';
    }

    // Curl probe
    const { exec } = require('child_process');
    diag.curl_probe = await new Promise((resolve) => {
        exec('curl -Iv https://web.whatsapp.com --max-time 6 2>&1', (err, stdout) => {
            resolve(stdout || err?.message || 'Empty stdout');
        });
    });

    res.json(diag);
});

app.listen(PORT, () => {
    console.log(`modsysv4 WA Gateway running on port ${PORT}`);
});
