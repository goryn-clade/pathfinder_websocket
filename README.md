## WebSocket server for [Pathfinder](https://github.com/exodus4d/pathfinder)

> **Running v3.0+?** Deployment is handled by [pathfinder-containers](https://github.com/goryn-clade/pathfinder-containers) — the WebSocket server runs as the `pf-socket` service. See the [v3.0 changes](#v30--breaking-changes) section below for the new required env vars and origin checks.

### Requirements
- _PHP_ (≥ v8.3 for v3.0; v7.1 for v2.x)
- A working instance of *[Pathfinder](https://github.com/exodus4d/pathfinder)* (≥ v2.0.0-rc.1)
- [_Composer_](https://getcomposer.org/download/) to install packages for the WebSocket server

---

## v3.0 — Breaking changes

These apply when running the WebSocket server against Pathfinder v3.0+. For the full deployment guide see [pathfinder-containers](https://github.com/goryn-clade/pathfinder-containers).

### New required environment variables

The server now **fails fast on startup** if these are misconfigured:

- **`WS_TOKEN_SECRET`** — HMAC secret that binds per-character WebSocket tokens to the PHP session. Must be at least 32 hex characters. Generate with `openssl rand -hex 32`. Missing or short values exit with code 1.
- **`WS_ALLOWED_ORIGINS`** — comma-separated list of hostnames allowed to open WebSocket connections. **Required in production** (`APP_ENV=production`); empty in production exits with code 1. Non-prod auto-allows localhost.
- **`APP_ENV`** — set to `production` to activate the origin allowlist enforcement.

### Behavioral changes

- **Origin allowlist enforcement** — the `WebSockets` constructor now takes `$allowedOrigins` and `$appEnv`. Browser clients whose `Origin` header is not on the allowlist are rejected.
- **WS token HMAC binding** — tokens are HMAC-bound to the PHP session using `WS_TOKEN_SECRET`. Forged or replayed tokens are rejected.
- **PHP 8.3** — the v3.0 container image runs PHP 8.3. If you run the server outside Docker, upgrade your PHP runtime accordingly.


---

### Install
1. Checkout this project in a **new** folder e.g. `/var/www/websocket.pathfinder`
1. Install [_Composer_](https://getcomposer.org/download/)
2. Install Composer dependencies from `composer.json` file:
  - `$ cd /var/www/websocket.pathfinder`
  - `$ composer install`
3. Start WebSocket server `$ php cmd.php`
 
### Configuration

#### Default

**Clients (WebBrowser) listen for connections**
- Host: `0.0.0.0.` (=> any client can connect)
- Port: `8020`
- ↪ URI: `127.0.0.1:8020` 

(=> Your WebServer (e.g. Nginx) should proxy all WebSocket connections to this source)

**TCP TcpSocket connection (Internal use for WebServer ⇄ WebSocket server communication)**
- Host: `127.0.0.1` (=> Assumed WebServer and WebSocket server running on the same machine)
- Port: `5555`
- ↪ URI: `tcp://127.0.0.1:5555` 

(=> Where _Pathfinder_ reaches the WebSocket server. This must match `SOCKET_HOST`, `SOCKET_PORT` options in `environment.ini`)
 
#### Start parameters [Optional]

The default configuration should be fine for most installations. 
You can change/overwrite the default **Host** and **Port** configuration by adding additional CLI parameters when starting the WebSocket server:

`$ php cmd.php --wsHost [CLIENTS_HOST] --wsPort [CLIENTS_PORT] --tcpHost [TCP_HOST] --tcpPort [TCP_PORT] --debug 0`
 
 For example: If you want to change the the WebSocket port and increase debug output:
 
 `$ php cmd.php --wsPort 8030 --debug 3`
 
##### --debug (default `--debug 2`)

Allows you to set log output level from `0` (silent) - errors are not logged, to `3` (debug) for detailed logging.

![alt text](https://i.imgur.com/KfNF4lk.png)

### WebSocket UI

There is a WebSocket section on _Pathinders_ `/setup` page. After the WebSocket server is started, you should check it if everything works.
You see the most recent WebSocket log entries, the current connection state, the current number of active connections and all maps that have subscriptions

![alt text](https://i.imgur.com/dDUrnx2.png)

Log entry view. Depending on the `--debug` parameter, the most recent (max 50) entries will be shown:

![alt text](https://i.imgur.com/LIn9aNm.png)

Subscriptions for each map:

![alt text](https://i.imgur.com/fANYwho.gif)

### Unix Service (systemd)

#### New Service
It is recommended to wrap the `cmd.php` script in a Unix service, that over control the WebSocket server.
This creates a systemd service on CentOS7:
1. `$ cd /etc/systemd/system`
2. `$ vi websocket.pathfinder.service`
3. Copy script and adjust `ExecStart` and `WorkingDirectory` values:

```
[Unit]
Description = WebSocket server (Pathfinder) [LIVE] environment
After = multi-user.target

[Service]
Type = idle
ExecStart = /usr/bin/php /var/www/websocket.pathfinder/pathfinder_websocket/cmd.php
WorkingDirectory = /var/www/websocket.pathfinder/pathfinder_websocket
TimeoutStopSec = 0
Restart = always
LimitNOFILE = 10000
Nice = 10

[Install]
WantedBy = multi-user.target
```

Now you can use the service to start/stop/restart your WebSocket server
- `$ systemctl start websocket.pathfinder.service`
- `$ systemctl restart websocket.pathfinder.service`
- `$ systemctl stop websocket.pathfinder.service`

#### Auto-Restart the Service
You can automatically restart your service (e.g. on _EVE-Online_ downtime). Create a new "timer" for the automatic restart.
1. `$ cd /etc/systemd/system` (same dir as before)
2. `$ vi restart.websocket.pathfinder.timer`
3. Copy script:

```
[Unit]
Description = Restart timer (EVE downtime) for WebSocket server [LIVE]

[Timer]
OnCalendar = *-*-* 12:01:00
Persistent = true

[Install]
WantedBy = timer.target
```
Now we need a new "restart service" for the timer:
1. `$ cd /etc/systemd/system` (same dir as before)
2. `$ vi restart.websocket.pathfinder.service`
3. Copy script:

```
[Unit]
Description = Restart (periodically)  WebSocket server [LIVE]

[Service]
Type = oneshot
ExecStart = /usr/bin/systemctl try-restart websocket.pathfinder.service
```
And then, we need to either restart the machine or launch
```
systemctl start restart.websocket.pathfinder.timer
```
### Info
- [*Ratchet*](http://socketo.me) - "WebSockets for PHP"
- [*ReactPHP*](https://reactphp.org) - "Event-driven, non-blocking I/O with PHP"
