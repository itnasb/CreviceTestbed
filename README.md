# Crevice Testbed

Intentionally vulnerable PHP web application labs for hands-on security testing, exploit development practice, and secure code review training.

Crevice Testbed is tested on and installation is recommended on an Ubuntu Server virtual machine.

⚠️ **WARNING:**  
This application is intentionally vulnerable and plaintext hardcoded secrets are in use.  
Run on a local virtual machine only. Do not expose to the public internet.

If installed on your local machine rather than a VM, a port will be opened to your local network. This means don't run the app while on the local Coffee shop wifi or your public Uni wifi.

---

## Overview

Crevice Testbed is a collection of small, focused web application demos designed to emulate real-world vulnerability patterns in controlled scenarios.

Each lab demonstrates at least one class of vulnerability, such as:

- Input validation flaws
- Output encoding mistakes
- DOM-based injection
- File upload abuse
- Unsafe deserialization
- Broken Access Control
- Remote Code Execution issues
- And more

When installed via Docker, users do not need to install runtime dependencies directly on the host.

---

## Requirements

- Docker Engine
- Docker Compose plugin

You can verify your installation with:

```bash
docker version
docker compose version
```

---

## Quick Start (Recommended)

### 1) Clone the Repository

```bash
git clone https://github.com/itnasb/CreviceTestbed.git
cd CreviceTestbed
```

### 2) Pull the Latest Published Images

```bash
docker compose pull
```

### 3) Start the Application

```bash
docker compose up -d
```

### 4) Open in Browser

```text
http://localhost:8080
```

If you are running Crevice Testbed on a remote Ubuntu VM or server, replace `localhost` with that system's IP address or hostname.

---

## Updating to the Latest Version

From the project directory:

```bash
docker compose pull
docker compose up -d
```

If you want a completely fresh database state while updating:

```bash
docker compose down -v --remove-orphans
docker compose pull
docker compose up -d
```

---

## Lab Resets

If needed, restart with a clean database state:

```bash
docker compose down -v --remove-orphans
docker compose up -d
```

---

## Running From Source (Development Mode)

If you want to build locally instead of pulling the published images:

```bash
git clone https://github.com/itnasb/CreviceTestbed.git
cd CreviceTestbed
docker compose -f docker-compose.yml -f docker-compose.dev.yml up --build
```

Local dev without Docker:

```bash
php -S 127.0.0.1:8000 router.php
```

---

## Published Images

Crevice Testbed publishes separate web and database images through GitHub Container Registry:

- `ghcr.io/itnasb/crevice-testbed-web:latest`
- `ghcr.io/itnasb/crevice-testbed-db:latest`

Advanced users can pull them directly:

```bash
docker pull ghcr.io/itnasb/crevice-testbed-web:latest
docker pull ghcr.io/itnasb/crevice-testbed-db:latest
```

For normal use, Docker Compose is the recommended path.

---

## Versioning Strategy

Published images may use tags such as:

- `latest` -> most recent build from the default branch
- commit-derived tags -> exact build provenance

If release tags are added later, version tags such as `vX.Y.Z` can also be published.

This allows:

- Reproducible training environments
- Stable classroom distributions
- Controlled lab progression

---

## Security Notice

These applications intentionally contain vulnerable code patterns.

They are provided strictly for:

- Educational use
- Security research
- Defensive training
- Secure coding practice

Do not deploy to:

- Production environments
- Internet-facing servers
- Shared public infrastructure

---

## License

MIT License

---

## Maintainer

Maintained by: Bernard  
GitHub: https://github.com/itnasb