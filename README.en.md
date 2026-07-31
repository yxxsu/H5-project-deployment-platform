# H5 Project Deployment Platform — Product Advantages

A lightweight H5 project hosting system built on PHP 7.4 + MySQL. It solves one core problem: turning locally written HTML/CSS/JS files or a full-site archive into a publicly accessible live URL within minutes — no server configuration, no command-line knowledge required, and fully self-service for ordinary users.

The advantages below are organized across six dimensions — deployment efficiency, access and sharing, security, user experience, admin management, and installation barrier — each compared against the traditional approach of manually copying files to a server.

## Three Deployment Modes Cover Nearly Every Go-Live Scenario

The platform breaks "launching an H5" into three paths. Whether you hold a single snippet of code, a single asset file, or a complete multi-file project, there is a dedicated entry point for it.

| Deployment Mode | Best For | How It Works |
| --- | --- | --- |
| Code Editor Deploy | A quick landing page, a style tweak | Enter a filename (e.g. `index.html`) in the console, paste the code, click save — a link is generated instantly |
| Single File Upload | An image, a JSON config, a standalone JS | Pick a local file and upload in one click; supports common types such as html/js/css/json/png |
| ZIP Archive Deploy | A full multi-file project (with assets, multiple pages) | Package as a zip with `index.html` in the root; the whole site is auto-extracted and deployed on upload |

The third mode is especially friendly to frontend developers — package the locally built `dist` directory and upload it directly, and the online structure stays identical to the local one, eliminating the tedium of uploading files one by one over FTP.

## Short-Link Sharing — Live the Moment You Upload

Every deployed project gets a 32-character random string access short link (of the form `domain/api/link.php?url=xxxx`). This link delivers three benefits:

- **Unguessable**: The random string is long enough that no one can stumble onto your unpublished project by enumeration.
- **Instantly live**: The link takes effect the moment the upload completes — no build or release pipeline to wait for.
- **Centrally managed**: All project links sit in a single console list, with one-click copy and inline preview, so you never have to memorize URLs.

## Multi-Layer Security — Peace of Mind for Projects and Accounts

This is where the platform invests the most effort. For ordinary users, it means two things: your projects cannot be tampered with by others, and your account is hard to compromise.

**Account and Login Layer**

- Login and registration are guarded end-to-end by CSRF tokens, preventing cross-site request forgery.
- Session ID is automatically regenerated on successful login, blocking session fixation attacks.
- Passwords are stored with `password_hash` + bcrypt salted hashing — even a database leak cannot reveal plaintext credentials.
- Failed logins return a uniform "incorrect account or password" message, never disclosing whether an account exists, which closes off account enumeration.

**Project Access Layer**

- Each user's files live in an isolated space directory; cross-user access is impossible.
- Access links pass path-traversal validation (realpath comparison), so `../` tricks cannot escape the user directory to read system files.
- PHP stream wrappers such as `php://filter` are blocked, preventing protocol-based source-code disclosure.
- File downloads carry `X-Content-Type-Options: nosniff` and correct MIME mapping, stopping browsers from executing text as scripts.
- CDN-hosted jQuery and FontAwesome include SRI integrity checks; if a resource is hijacked or tampered with, the browser refuses to load it.

**Admin Protection Layer**

- The admin backend uses separate authentication — only the administrator (ID=1) can enter — plus an additional CSP content security policy header.
- An IP blacklist is supported: once a suspicious IP is banned, that address can no longer reach any platform endpoint.

## Modern Interface — Effortless to Use

The console adopts a Glassmorphism design language, with look and feel benchmarked against today's mainstream SaaS products:

- **One-click dark / light theme toggle**, with the choice persisted locally and restored on the next visit.
- **Responsive layout** that works on phones, tablets, and desktops, so projects can go live from a mobile device too.
- **Card hover effects, Toast notifications, and modal dialogs** provide complete interaction feedback — every action gets a clear response, never leaving you wondering whether a click registered.
- Theme color is customizable at install time and in the backend (non-blue/purple only), letting integrators align it with their brand.

## Companion Admin Backend — Worry-Free Operations

The platform is built not only for individual users but also for the scenario of running a public hosting service. The admin backend provides three capabilities:

- **System Settings**: Adjust the storage cap (in MB) for ordinary users and the global theme color — no code changes required.
- **User Management**: View all registered users, with one-click ban / unban. The super admin cannot be banned, keeping the backend always reachable.
- **IP Blacklist Management**: Manually block IPs engaged in malicious access or API abuse, with immediate effect.

The admin account itself has no storage limit, making it suitable for hosting official demo projects or large presentation pages.

## Low Installation Barrier — Near-Zero Dependencies

The system keeps runtime requirements minimal — a standard shared host or lightweight cloud server is enough to run it:

- PHP version ≥ 7.4, with only the `pdo_mysql` and `zip` extensions enabled.
- A single MySQL database — no manual table creation needed, since the installer auto-creates the user, short-link, IP-blacklist, and config tables.
- A three-step visual installer: environment check → database configuration → admin creation, all done through web forms in a few minutes.
- The installer auto-detects PHP version, extensions, and directory write permissions, so any missing prerequisite is visible upfront rather than surfacing as a runtime error later.

Database tables support a custom prefix (default `deploy_`), allowing the platform to share a database with other systems without conflicts.

## Who It's For

- **Frontend developers**: Finish an H5 locally and want to send a preview link to a client without wrestling with deployment.
- **Operations / Marketing**: Need a campaign landing page or H5 poster up temporarily, and prefer self-service over filing a ticket.
- **Education / Training**: Students upload assignments while instructors review them centrally, with storage caps and banning as guardrails.
- **Small-team internal hosting**: Stand up a private static-asset distribution endpoint with access control and auditability.

In summary, the platform reduces what used to be an ops-heavy task — copying files to a server — to a simple web form, while investing far more in security than comparable lightweight tools. For users, the saving is deployment time; for operators, the saving is the remediation cost of later attacks and abuse.
