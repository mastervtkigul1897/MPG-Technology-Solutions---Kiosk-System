# Security TODO (Server / Deployment)

Mga item na ito ay outside PHP app code at dapat i-apply sa server/infra.

## 1) Database least-privilege users

- Gumamit ng hiwalay na DB accounts:
  - `app_runtime_user` (for normal web requests)
  - `app_migration_user` (for schema changes/migrations only)
- Sa production, **tanggalin** sa `app_runtime_user` ang destructive DDL privileges.

Example MySQL grants:

```sql
-- Runtime user (NO DROP / TRUNCATE / ALTER / CREATE / INDEX)
GRANT SELECT, INSERT, UPDATE, DELETE ON laundry_shop_db.* TO 'app_runtime_user'@'%';

-- Migration user (used only during controlled deploy window)
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP ON laundry_shop_db.* TO 'app_migration_user'@'%';

FLUSH PRIVILEGES;
```

## 2) Enforce HTTPS + secure cookies end-to-end

- Sa reverse proxy (Nginx/Apache), force HTTPS redirect.
- Ensure `X-Forwarded-Proto` is passed correctly kung behind load balancer.
- Enable HSTS only when HTTPS is fully stable in all subdomains.

Nginx example:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$host$request_uri;
}
```

## 3) Web server upload safety

- I-block execution ng scripts under upload directories (`public/uploads/*`).
- Allow only static file serving there.

Nginx example:

```nginx
location ^~ /uploads/ {
    autoindex off;
    types { }
    default_type application/octet-stream;
    try_files $uri =404;
}

location ~* ^/uploads/.*\.(php|phtml|phar|pl|py|sh|cgi)$ {
    deny all;
}
```

## 4) Production PHP settings

- `display_errors = Off`
- `log_errors = On`
- `expose_php = Off`
- `allow_url_fopen = Off` (if not required)
- `allow_url_include = Off`
- `session.cookie_secure = 1` (when HTTPS is always on)

## 5) Edge rate limiting / abuse protection

- Add IP-based rate limit at reverse proxy/WAF for:
  - `/login`
  - `/forgot-password`
  - `/email/verification-notification`
  - sensitive admin endpoints (`/super-admin/*`)
- Add fail2ban or equivalent to block repeated auth failures.

## 6) Backup and restore safety

- Keep automated daily DB backups offsite (encrypted at rest).
- Test restore monthly.
- Restrict who can run restore operations (super-admin only, audited).
- Protect backup storage credentials via environment variables/secret manager.

## 7) Secret management

- Move DB/mail/API secrets to environment variables (never hardcode in repo).
- Rotate secrets regularly and after incident.
- Restrict filesystem read permissions for config and env files.

## 8) Monitoring and alerting

- Monitor and alert on:
  - failed login spikes
  - repeated 403/419/429 responses
  - super-admin destructive actions
  - backup failures
- Keep logs centralized and retained according to policy.

