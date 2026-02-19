<?php
require_once './controller/admin_controller/admin_config.php';
checkAdminAuth();

$serverId = $_GET['server_id'] ?? '';
if (empty($serverId)) {
    header('Location: ?p=admin_ssh_servers');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Terminal - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css">
    <style>
        :root {
            --bg-body: #000;
            --border-color: rgba(229,9,20,.3);
            --accent: #E50914;
            --terminal-bg: #0a0a0a;
        }
        body { background: var(--bg-body); color:#fff; font-family:'Segoe UI',sans-serif; min-height:100vh; }

        .terminal-header {
            background: linear-gradient(135deg,rgba(229,9,20,.15),rgba(229,9,20,.05));
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 1rem;
        }
        .terminal-info { display:flex; align-items:center; gap:1rem; }
        .terminal-icon {
            width:45px; height:45px;
            background: linear-gradient(135deg,var(--accent),#ff3d47);
            border-radius:12px; display:flex; align-items:center; justify-content:center;
            color:#fff; font-size:1.2rem;
        }
        .terminal-title  { margin:0; font-weight:700; color:#fff; }
        .terminal-subtitle { font-size:.85rem; color:#888; margin:0; }

        .status-indicator {
            display:flex; align-items:center; gap:8px;
            padding:8px 16px; border-radius:50px; font-size:.85rem; font-weight:600;
        }
        .status-indicator.connecting { background:rgba(245,158,11,.2); color:#f59e0b; }
        .status-indicator.connected  { background:rgba(16,185,129,.2);  color:#10b981; }
        .status-indicator.disconnected { background:rgba(239,68,68,.2); color:#ef4444; }
        .status-dot { width:8px; height:8px; border-radius:50%; animation:pulse 2s infinite; }
        .status-indicator.connecting .status-dot  { background:#f59e0b; }
        .status-indicator.connected  .status-dot  { background:#10b981; }
        .status-indicator.disconnected .status-dot { background:#ef4444; animation:none; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }

        .terminal-container {
            background: var(--terminal-bg);
            border: 1px solid var(--border-color);
            border-radius: 0 0 16px 16px;
            overflow: hidden;
            height: calc(100vh - 180px);
            min-height: 400px;
        }
        #terminal { height:100%; }

        .terminal-actions { display:flex; gap:.5rem; }
        .btn-terminal { padding:8px 16px; border-radius:8px; font-weight:600; font-size:.85rem; transition:all .3s; }
        .btn-disconnect { background:rgba(239,68,68,.2);  border:1px solid rgba(239,68,68,.3);  color:#f87171; }
        .btn-disconnect:hover { background:rgba(239,68,68,.4); color:#fff; }
        .btn-reconnect  { background:rgba(16,185,129,.2); border:1px solid rgba(16,185,129,.3); color:#10b981; }
        .btn-reconnect:hover  { background:rgba(16,185,129,.4); color:#fff; }
        .btn-back       { background:rgba(99,102,241,.2); border:1px solid rgba(99,102,241,.3); color:#818cf8; }
        .btn-back:hover       { background:rgba(99,102,241,.4); color:#fff; }

        .xterm { padding:10px; }
        .xterm-viewport { overflow-y:auto !important; }
    </style>
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid px-4 py-3">
        <div class="terminal-header rounded-top">
            <div class="terminal-info">
                <div class="terminal-icon"><i class="fas fa-terminal"></i></div>
                <div>
                    <h5 class="terminal-title" id="serverName">Loading...</h5>
                    <p class="terminal-subtitle" id="serverHost">Connecting...</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="status-indicator connecting" id="statusIndicator">
                    <span class="status-dot"></span>
                    <span id="statusText">Connecting...</span>
                </div>
                <div class="terminal-actions">
                    <button class="btn btn-terminal btn-reconnect" onclick="reconnect()" id="btnReconnect" style="display:none;">
                        <i class="fas fa-sync-alt me-2"></i>Reconnect
                    </button>
                    <button class="btn btn-terminal btn-disconnect" onclick="disconnect()" id="btnDisconnect">
                        <i class="fas fa-power-off me-2"></i>Disconnect
                    </button>
                    <a href="?p=admin_ssh_servers" class="btn btn-terminal btn-back">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>
        </div>
        <div class="terminal-container">
            <div id="terminal"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const serverId     = '<?php echo htmlspecialchars($serverId); ?>';
    const controllerUrl = '../controller/admin_controller/terminal_controller.php';

    let sessionId    = null;
    let term         = null;
    let fitAddon     = null;
    let eventSource  = null;
    let isConnected  = false;
    let inputBuffer  = '';
    let inputTimer   = null;

    // ── Terminal init ────────────────────────────────────────────────────────
    function initTerminal() {
        term = new Terminal({
            cursorBlink: true,
            cursorStyle: 'block',
            fontSize: 14,
            fontFamily: '"Cascadia Code","Fira Code","JetBrains Mono",Consolas,monospace',
            theme: {
                background: '#0a0a0a', foreground: '#e0e0e0',
                cursor: '#E50914',     cursorAccent: '#000',
                selection: 'rgba(229,9,20,.3)',
                black:   '#000',     red:     '#E50914', green:   '#10b981', yellow:  '#f59e0b',
                blue:    '#3b82f6',  magenta: '#a855f7', cyan:    '#06b6d4', white:   '#f0f0f0',
                brightBlack: '#666', brightRed: '#ff4757', brightGreen: '#2ed573',
                brightYellow: '#ffa502', brightBlue: '#70a1ff', brightMagenta: '#c084fc',
                brightCyan: '#22d3ee', brightWhite: '#ffffff',
            },
            scrollback: 10000,
            convertEol: false,
            allowTransparency: true,
        });

        fitAddon = new FitAddon.FitAddon();
        term.loadAddon(fitAddon);
        term.open(document.getElementById('terminal'));
        fitAddon.fit();

        // Resize observer
        window.addEventListener('resize', () => { fitAddon.fit(); sendResize(); });

        // Forward ALL raw input (chars, escape sequences, paste) to server
        term.onData(data => {
            if (!isConnected || !sessionId) return;
            inputBuffer += data;
            clearTimeout(inputTimer);
            // 20 ms batching reduces HTTP requests without noticeable latency
            inputTimer = setTimeout(flushInput, 20);
        });

        term.writeln('\x1b[1;31m╔══════════════════════════════════════════════════════════════╗\x1b[0m');
        term.writeln('\x1b[1;31m║\x1b[0m              \x1b[1;37mWeb Terminal\x1b[0m - SSH via Browser                  \x1b[1;31m║\x1b[0m');
        term.writeln('\x1b[1;31m╚══════════════════════════════════════════════════════════════╝\x1b[0m');
        term.writeln('');
        term.writeln('\x1b[33mConnecting to server...\x1b[0m');

        connect();
    }

    // ── Input forwarding ─────────────────────────────────────────────────────
    function flushInput() {
        if (!inputBuffer || !sessionId) return;
        const data = inputBuffer;
        inputBuffer = '';
        const fd = new FormData();
        fd.append('action',     'send_input');
        fd.append('session_id', sessionId);
        fd.append('input',      data);
        fetch(controllerUrl, { method: 'POST', body: fd }).catch(() => {});
    }

    // ── Terminal resize notification ─────────────────────────────────────────
    function sendResize() {
        if (!sessionId) return;
        const fd = new FormData();
        fd.append('action',     'resize');
        fd.append('session_id', sessionId);
        fd.append('cols',       term.cols);
        fd.append('rows',       term.rows);
        fetch(controllerUrl, { method: 'POST', body: fd }).catch(() => {});
    }

    // ── SSE stream ───────────────────────────────────────────────────────────
    function startStream() {
        if (eventSource) { eventSource.close(); eventSource = null; }

        const url = `${controllerUrl}?action=stream&session_id=${encodeURIComponent(sessionId)}`;
        eventSource = new EventSource(url);

        // Received output chunk (base64 encoded binary)
        eventSource.addEventListener('data', e => {
            try {
                const binary = atob(e.data);
                const bytes  = new Uint8Array(binary.length);
                for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
                term.write(bytes);
            } catch (_) {}
        });

        // Server-side error
        eventSource.addEventListener('error', e => {
            try {
                const msg = e.data ? atob(e.data) : 'Connection lost';
                term.writeln('\r\n\x1b[31mError: ' + msg + '\x1b[0m');
            } catch(_) {}
            onDisconnected();
        });

        // Stream closed cleanly
        eventSource.addEventListener('close', () => {
            term.writeln('\r\n\x1b[33mShell session ended.\x1b[0m');
            onDisconnected();
        });

        // Browser-level EventSource error (network)
        eventSource.onerror = () => {
            if (eventSource && eventSource.readyState === EventSource.CLOSED) {
                onDisconnected();
            }
        };
    }

    function onDisconnected() {
        isConnected = false;
        sessionId   = null;
        if (eventSource) { eventSource.close(); eventSource = null; }
        setStatus('disconnected', 'Disconnected');
        document.getElementById('btnReconnect').style.display  = 'inline-flex';
        document.getElementById('btnDisconnect').style.display = 'none';
    }

    // ── Connection management ─────────────────────────────────────────────────
    function setStatus(cls, text) {
        document.getElementById('statusIndicator').className = 'status-indicator ' + cls;
        document.getElementById('statusText').textContent    = text;
    }

    async function connect() {
        try {
            // Fetch server info for display
            const infoRes = await fetch(`${controllerUrl}?action=get_server_info&server_id=${serverId}`);
            const info    = await infoRes.json();
            if (info.success) {
                document.getElementById('serverName').textContent = info.data.server_name;
                document.getElementById('serverHost').textContent =
                    `${info.data.username}@${info.data.server_host}:${info.data.ssh_port}`;
            }

            // Establish session
            const fd = new FormData();
            fd.append('action', 'connect');
            fd.append('server_id', serverId);

            const res  = await fetch(controllerUrl, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                sessionId   = data.session_id;
                isConnected = true;
                setStatus('connected', 'Connected');
                document.getElementById('btnReconnect').style.display  = 'none';
                document.getElementById('btnDisconnect').style.display = 'inline-flex';
                term.writeln('\x1b[32m✓ Connected! Opening interactive shell...\x1b[0m');
                term.writeln('');
                startStream();
                // Send current terminal dimensions once stream is alive
                setTimeout(sendResize, 600);
            } else {
                setStatus('disconnected', 'Failed');
                term.writeln('\x1b[31m✗ Connection failed: ' + (data.message || '') + '\x1b[0m');
                document.getElementById('btnReconnect').style.display  = 'inline-flex';
                document.getElementById('btnDisconnect').style.display = 'none';
            }
        } catch (err) {
            setStatus('disconnected', 'Error');
            term.writeln('\x1b[31m✗ Error: ' + err.message + '\x1b[0m');
            document.getElementById('btnReconnect').style.display  = 'inline-flex';
            document.getElementById('btnDisconnect').style.display = 'none';
        }
    }

    async function disconnect() {
        isConnected = false;
        clearTimeout(inputTimer);
        flushInput();
        if (eventSource) { eventSource.close(); eventSource = null; }
        if (sessionId) {
            try {
                const fd = new FormData();
                fd.append('action',     'disconnect');
                fd.append('session_id', sessionId);
                await fetch(controllerUrl, { method: 'POST', body: fd });
            } catch (_) {}
        }
        sessionId = null;
        setStatus('disconnected', 'Disconnected');
        term.writeln('\r\n\x1b[33mDisconnected.\x1b[0m');
        document.getElementById('btnReconnect').style.display  = 'inline-flex';
        document.getElementById('btnDisconnect').style.display = 'none';
    }

    function reconnect() {
        if (eventSource) { eventSource.close(); eventSource = null; }
        sessionId   = null;
        isConnected = false;
        inputBuffer = '';
        term.writeln('\r\n\x1b[33mReconnecting...\x1b[0m');
        setStatus('connecting', 'Connecting...');
        connect();
    }

    // Clean up on page leave
    window.addEventListener('beforeunload', () => {
        if (sessionId) {
            const fd = new FormData();
            fd.append('action',     'disconnect');
            fd.append('session_id', sessionId);
            navigator.sendBeacon(controllerUrl, fd);
        }
    });

    document.addEventListener('DOMContentLoaded', initTerminal);
    </script>
</body>
</html>