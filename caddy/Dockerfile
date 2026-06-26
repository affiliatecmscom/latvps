# Caddy + plugin Cloudflare DNS (để xin cert qua ACME DNS-01, chạy được kể cả khi bật CF proxy).
# Build trên VPS lúc setup. Hành xử y hệt caddy:2 khi không bật CF mode.
FROM caddy:2-builder AS builder
RUN xcaddy build --with github.com/caddy-dns/cloudflare

FROM caddy:2
COPY --from=builder /usr/bin/caddy /usr/bin/caddy
