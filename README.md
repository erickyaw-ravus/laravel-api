# Ravus

Laravel application using [Laravel Sail](https://laravel.com/docs/sail) for local development with Docker.

## Prerequisites

- Docker Desktop (or Docker Engine + Docker Compose)

## First-time setup

1. **Clone and install dependencies**

    ```bash
    composer install
    ```

2. **Copy environment file and generate key**

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

3. **Start Sail** (builds images and starts all services, including Mailpit):

    ```bash
    ./vendor/bin/sail up -d
    ```

4. **Run migrations**

    ```bash
    ./vendor/bin/sail artisan migrate
    ```

The app is available at `http://localhost` (or the port set by `APP_PORT` in `.env`).

---

## Sail with Mailpit

Sail is configured with **Mailpit** so all outgoing mail is captured locally instead of being sent to real addresses.

- **SMTP (for the app):** `mailpit:1025` (inside Docker)  
  In `.env`, Mailpit is already set when using Sail:
    - `MAIL_HOST=mailpit`
    - `MAIL_PORT=1025`

- **Web UI:** open **http://localhost:8025** (or the port from `FORWARD_MAILPIT_DASHBOARD_PORT` in `.env`) to view and inspect sent emails.

Useful Sail commands:

```bash
# Start all services (app, MySQL, Mailpit)
./vendor/bin/sail up -d

# Stop services
./vendor/bin/sail down

# View logs
./vendor/bin/sail logs -f
```

Optional `.env` overrides for Mailpit ports (if the defaults clash with other tools):

- `FORWARD_MAILPIT_PORT=1025` — SMTP port on the host (default 1025)
- `FORWARD_MAILPIT_DASHBOARD_PORT=8025` — Web UI port (default 8025)

---

## Testing

Tests run inside the Sail container so they use the same MySQL and environment as development, with test-specific overrides from `phpunit.xml` (e.g. `APP_ENV=testing`, `DB_DATABASE=testing`, `MAIL_MAILER=array`).

**Run all tests**

```bash
./vendor/bin/sail test
```

**Run a specific test suite**

```bash
./vendor/bin/sail test --testsuite=Unit
./vendor/bin/sail test --testsuite=Feature
```

**Run a single test file**

```bash
./vendor/bin/sail test tests/Feature/Auth/LoginTest.php
```

**Run with coverage** (optional)

```bash
./vendor/bin/sail test --coverage
```

In tests, mail is sent to the `array` driver (`MAIL_MAILER=array` in `phpunit.xml`), so nothing hits Mailpit during tests. Use Laravel’s `Mail` facade and assertions like `Mail::assertSent()` to verify that mails would be sent. Mailpit is for manual checking in the browser during development.
