# FluxMode Labs

Intentionally vulnerable PHP web application labs for hands-on security testing, and secure code review practice.

⚠️ WARNING:
These applications are intentionally vulnerable.
Run locally only. Do not expose to the public internet.

---

## Overview

FluxMode Labs is a collection of small, focused web application demos designed to emulate real-world vulnerability patterns in controlled scenarios.

Each lab demonstrates a specific class of vulnerability, such as:

- DOM-based injection
- File upload abuse
- Command execution issues
- Input validation flaws
- Output encoding mistakes
- Unsafe deserialization
- And more

The platform is distributed as a Docker container so users do not need to install:

- PHP
- Apache
- Composer
- Extensions
- Any runtime dependencies

All dependencies (including mbstring) are built into the container.

---

## Quick Start (Recommended)

### 1) Install Docker

Install Docker Desktop or Docker Engine:
https://www.docker.com/products/docker-desktop/

### 2) Pull the Latest Image

```bash
docker pull YOUR_DOCKERHUB_USERNAME/fluxmode-labs:latest
```

### 3) Run the Container

```bash
docker run --name fluxmode-labs -p 8080:80 YOUR_DOCKERHUB_USERNAME/fluxmode-labs:latest
```

### 4) Open in Browser

http://localhost:8080


---

## Using Docker Compose (Alternative)

```bash
docker compose up -d
```
Then visit:

http://localhost:8080

---

## Updating to the Latest Version

```bash
docker pull YOUR_DOCKERHUB_USERNAME/fluxmode-labs:latest
docker rm -f fluxmode-labs
docker run --name fluxmode-labs -p 8080:80 YOUR_DOCKERHUB_USERNAME/fluxmode-labs:latest
```

For deterministic environments, you may pull a specific version:

```bash
docker pull YOUR_DOCKERHUB_USERNAME/fluxmode-labs:v1.2.0
```

---

## Lab Resets


If needed, restart container for a clean state:

```bash
docker rm -f fluxmode-labs
docker run --name fluxmode-labs -p 8080:80 YOUR_DOCKERHUB_USERNAME/fluxmode-labs:latest
```

---

## Running From Source (Development Mode)

If you want to build locally instead of pulling a prebuilt image:

```bash
git clone https://github.com/YOUR_GITHUB_USERNAME/fluxmode-labs.git
cd fluxmode-labs
docker compose up --build

or 

Local dev:
  php -S 127.0.0.1:8000 router.php
```

---

## Versioning Strategy

Images are published with:

- latest -> most recent build from main branch
- sha-<commit> -> exact commit build
- vX.Y.Z -> tagged releases

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

Specify your license here (MIT recommended for training content).

---

## Maintainer

Maintained by: YOUR_NAME
GitHub: https://github.com/YOUR_GITHUB_USERNAME
