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
    <title>Web Terminal -
        <?php echo SITE_NAME; ?>
    </title>
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css">
    <style>
        :root {
            --bg-body: #000000;
            --border-color: rgba(229, 9, 20, 0.3);
            --accent: #E50914;
            --terminal-bg: #0a0a0a;
        }

        body {
            background: var(--bg-body);
            color: #fff;
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
        }

        .terminal-header {
            background: linear-gradient(135deg, rgba(229, 9, 20, 0.15), rgba(229, 9, 20, 0.05));
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .terminal-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .terminal-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--accent), #ff3d47);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .terminal-title {
            margin: 0;
            font-weight: 700;
            color: #fff;
        }

        .terminal-subtitle {
            font-size: 0.85rem;
            color: #888;
            margin: 0;
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-indicator.connecting {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .status-indicator.connected {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .status-indicator.disconnected {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .status-indicator.connecting .status-dot {
            background: #f59e0b;
        }

        .status-indicator.connected .status-dot {
            background: #10b981;
        }

        .status-indicator.disconnected .status-dot {
            background: #ef4444;
            animation: none;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .terminal-container {
            background: var(--terminal-bg);
            border: 1px solid var(--border-color);
            border-radius: 0 0 16px 16px;
            overflow: hidden;
            height: calc(100vh - 180px);
            min-height: 400px;
        }

        #terminal {
            height: 100%;
            padding: 10px;
        }

        .terminal-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-terminal {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .btn-disconnect {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        .btn-disconnect:hover {
            background: rgba(239, 68, 68, 0.4);
            color: #fff;
        }

        .btn-reconnect {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
        }

        .btn-reconnect:hover {
            background: rgba(16, 185, 129, 0.4);
            color: #fff;
        }

        .btn-back {
            background: rgba(99, 102, 241, 0.2);
            border: 1px solid rgba(99, 102, 241, 0.3);
            color: #818cf8;
        }

        .btn-back:hover {
            background: rgba(99, 102, 241, 0.4);
            color: #fff;
        }

        .xterm {
            padding: 10px;
        }

        .xterm-viewport {
            overflow-y: auto !important;
        }
    </style>
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php'))
        include __DIR__ . '/../include/theme_head.php'; ?>
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
                    <button class="btn btn-terminal btn-reconnect" onclick="reconnect()" id="btnReconnect"
                        style="display:none;">
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
        const serverId = '<?php echo htmlspecialchars($serverId); ?>';
        const controllerUrl = '../controller/admin_controller/terminal_controller.php';
        let sessionId = null;
        let term = null;
        let fitAddon = null;
        let currentLine = '';
        let prompt = '$ ';
        let commandHistory = [];
        let historyIndex = -1;

        function initTerminal() {
            term = new Terminal({
                cursorBlink: true,
                cursorStyle: 'block',
                fontSize: 14,
                fontFamily: '"Cascadia Code", "Fira Code", "JetBrains Mono", monospace',
                theme: {
                    background: '#0a0a0a',
                    foreground: '#e0e0e0',
                    cursor: '#E50914',
                    cursorAccent: '#000',
                    selection: 'rgba(229, 9, 20, 0.3)',
                    black: '#000', red: '#E50914', green: '#10b981', yellow: '#f59e0b',
                    blue: '#3b82f6', magenta: '#a855f7', cyan: '#06b6d4', white: '#fff',
                    brightBlack: '#666', brightRed: '#ff4757', brightGreen: '#2ed573',
                    brightYellow: '#ffa502', brightBlue: '#70a1ff', brightMagenta: '#c084fc',
                    brightCyan: '#22d3ee', brightWhite: '#fff'
                },
                scrollback: 1000,
                convertEol: true
            });

            fitAddon = new FitAddon.FitAddon();
            term.loadAddon(fitAddon);
            term.open(document.getElementById('terminal'));
            fitAddon.fit();

            window.addEventListener('resize', () => fitAddon.fit());

            term.onKey(({ key, domEvent }) => {
                const ev = domEvent;
                const printable = !ev.altKey && !ev.ctrlKey && !ev.metaKey;

                if (ev.keyCode === 13) { // Enter
                    term.write('\r\n');
                    if (currentLine.trim()) {
                        commandHistory.push(currentLine);
                        historyIndex = commandHistory.length;
                        executeCommand(currentLine);
                    } else {
                        writePrompt();
                    }
                    currentLine = '';
                } else if (ev.keyCode === 8) { // Backspace
                    if (currentLine.length > 0) {
                        currentLine = currentLine.slice(0, -1);
                        term.write('\b \b');
                    }
                } else if (ev.keyCode === 38) { // Up
                    if (historyIndex > 0) {
                        historyIndex--;
                        clearCurrentLine();
                        currentLine = commandHistory[historyIndex];
                        term.write(currentLine);
                    }
                } else if (ev.keyCode === 40) { // Down
                    if (historyIndex < commandHistory.length - 1) {
                        historyIndex++;
                        clearCurrentLine();
                        currentLine = commandHistory[historyIndex];
                        term.write(currentLine);
                    } else {
                        historyIndex = commandHistory.length;
                        clearCurrentLine();
                        currentLine = '';
                    }
                } else if (ev.ctrlKey && ev.keyCode === 67) { // Ctrl+C
                    term.write('^C\r\n');
                    currentLine = '';
                    writePrompt();
                } else if (ev.ctrlKey && ev.keyCode === 76) { // Ctrl+L
                    term.clear();
                    writePrompt();
                } else if (printable) {
                    currentLine += key;
                    term.write(key);
                }
            });

            term.onData(data => {
                if (data.length > 1 && !data.startsWith('\x1b')) {
                    currentLine += data;
                    term.write(data);
                }
            });

            // Welcome
            term.writeln('\x1b[1;31m╔══════════════════════════════════════════════════════════════╗\x1b[0m');
            term.writeln('\x1b[1;31m║\x1b[0m              \x1b[1;37mWeb Terminal\x1b[0m - SSH via Browser                  \x1b[1;31m║\x1b[0m');
            term.writeln('\x1b[1;31m╚══════════════════════════════════════════════════════════════╝\x1b[0m');
            term.writeln('');
            term.writeln('\x1b[33mConnecting to server...\x1b[0m');

            connect();
        }

        function clearCurrentLine() {
            for (let i = 0; i < currentLine.length; i++) term.write('\b \b');
        }

        function writePrompt() {
            term.write('\x1b[1;32m' + prompt + '\x1b[0m');
        }

        function setStatus(status, text) {
            document.getElementById('statusIndicator').className = 'status-indicator ' + status;
            document.getElementById('statusText').textContent = text;
        }

        async function connect() {
            try {
                const infoRes = await fetch(`${controllerUrl}?action=get_server_info&server_id=${serverId}`);
                const info = await infoRes.json();
                if (info.success) {
                    document.getElementById('serverName').textContent = info.data.server_name;
                    document.getElementById('serverHost').textContent = `${info.data.username}@${info.data.server_host}:${info.data.ssh_port}`;
                    prompt = `${info.data.username}@${info.data.server_host}:~$ `;
                }

                const formData = new FormData();
                formData.append('action', 'connect');
                formData.append('server_id', serverId);

                const res = await fetch(controllerUrl, { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    sessionId = data.session_id;
                    setStatus('connected', 'Connected');
                    document.getElementById('btnReconnect').style.display = 'none';
                    document.getElementById('btnDisconnect').style.display = 'inline-flex';
                    term.writeln('\x1b[32m✓ Connected successfully!\x1b[0m');
                    term.writeln('');
                    term.writeln('\x1b[90mType commands below. Use Ctrl+L to clear, Ctrl+C to cancel.\x1b[0m');
                    term.writeln('');
                    writePrompt();
                } else {
                    setStatus('disconnected', 'Failed');
                    term.writeln('\x1b[31m✗ Connection failed: ' + data.message + '\x1b[0m');
                    document.getElementById('btnReconnect').style.display = 'inline-flex';
                    document.getElementById('btnDisconnect').style.display = 'none';
                }
            } catch (err) {
                setStatus('disconnected', 'Error');
                term.writeln('\x1b[31m✗ Error: ' + err.message + '\x1b[0m');
                document.getElementById('btnReconnect').style.display = 'inline-flex';
                document.getElementById('btnDisconnect').style.display = 'none';
            }
        }

        async function executeCommand(command) {
            if (!sessionId) {
                term.writeln('\x1b[31mNot connected.\x1b[0m');
                writePrompt();
                return;
            }

            if (command === 'clear' || command === 'cls') { term.clear(); writePrompt(); return; }
            if (command === 'exit' || command === 'logout') { disconnect(); return; }

            try {
                const formData = new FormData();
                formData.append('action', 'execute');
                formData.append('session_id', sessionId);
                formData.append('command', command);

                const res = await fetch(controllerUrl, { method: 'POST', body: formData });
                const text = await res.text();

                // Try parse JSON, handle non-JSON responses
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    term.writeln('\x1b[31mServer returned invalid response\x1b[0m');
                    writePrompt();
                    return;
                }

                if (data.success && data.output) {
                    data.output.split('\n').forEach(line => term.writeln(line));
                } else if (!data.success) {
                    term.writeln('\x1b[31m' + (data.message || 'Command failed') + '\x1b[0m');
                }
            } catch (err) {
                term.writeln('\x1b[31mError: ' + err.message + '\x1b[0m');
            }
            writePrompt();
        }

        async function disconnect() {
            if (sessionId) {
                try {
                    const fd = new FormData();
                    fd.append('action', 'disconnect');
                    fd.append('session_id', sessionId);
                    await fetch(controllerUrl, { method: 'POST', body: fd });
                } catch (e) { }
            }
            sessionId = null;
            setStatus('disconnected', 'Disconnected');
            term.writeln('\r\n\x1b[33mDisconnected.\x1b[0m');
            document.getElementById('btnReconnect').style.display = 'inline-flex';
            document.getElementById('btnDisconnect').style.display = 'none';
        }

        function reconnect() {
            term.writeln('\r\n\x1b[33mReconnecting...\x1b[0m');
            setStatus('connecting', 'Connecting...');
            connect();
        }

        document.addEventListener('DOMContentLoaded', initTerminal);
    </script>
</body>

</html>