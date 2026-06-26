# WP Factory — `iflmmo` CLI

Quản lý nhiều WordPress site (AffiliateCMS hoặc thường) trên một VPS Ubuntu, mỗi site **cô lập**
để một site bị hack không lây sang site khác. Một lệnh duy nhất: **`iflmmo`**.

## Cài đặt (VPS Ubuntu trắng) — 1 lần

```bash
curl -fsSL https://cdn.lat.vn/iflmmo.sh | sudo bash
```

Lệnh này: cài Docker + UFW (chỉ 22/80/443) + Caddy + wp-cli, tạo network, **cài lệnh `iflmmo`**,
hỏi license (bỏ qua được — site vanilla không cần), rồi hỏi tạo site đầu tiên.

> Nguồn code lấy từ repo `IFLMMO_REPO` (mặc định GitHub). Đặt CDN/repo thật trước khi phát hành
> cho học viên. Nếu đã có sẵn `/opt/wp-factory`, chạy thẳng: `sudo /opt/wp-factory/bin/iflmmo setup`.

## Dùng hằng ngày: gõ `iflmmo`

```bash
iflmmo            # mở menu TUI (mũi tên + Enter; tự fallback menu số nếu thiếu whiptail)
```

Menu chính:
1. **Thêm site mới** (việc chính)
2. Quản lý site (chọn site → đổi domain / đổi www-nonwww / backup / bật-tắt / logs / xoá)
3. Backup tất cả
4. License
5. Trạng thái hệ thống
6. Bảo trì / nâng cao (cập nhật hệ thống, self-update, payload, setup lại)

## Subcommand (power user / script / cron)

```bash
iflmmo add my-deals.com --type affiliatecms --canonical non-www --email you@email.com
iflmmo add blog.com --type vanilla            # WordPress thường, không AffiliateCMS
iflmmo ls
iflmmo domain <id|domain> new-domain.com      # đổi domain, giữ nguyên DB/container
iflmmo canonical <id|domain> www|non-www|none
iflmmo backup all
iflmmo restore <id|domain> /opt/backups/<id>/<date>.tar.gz
iflmmo update         # nâng image WP/MariaDB/Caddy + vá OS
iflmmo self-update    # cập nhật chính lệnh iflmmo
iflmmo status
iflmmo rm <id|domain>
```

## Hai loại site

- **affiliatecms**: cài sẵn plugin `affiliatecms-pro` + `affiliatecms-ai` + theme, tự activate
  license cho domain (lazy: hỏi license khi cần nếu chưa có).
- **vanilla**: WordPress sạch, không liên quan AffiliateCMS, không dùng license.

## Cô lập bảo mật

Mỗi site = 1 docker-compose project riêng (`site-<id>`):
- DB nằm network nội bộ riêng, **không** ra `wpfactory_proxy` → site khác không chạm tới được.
- `wp-content` + volume DB riêng từng site.
- `no-new-privileges` + `mem_limit`; không publish cổng (chỉ Caddy ra 80/443).

Chi tiết kiến trúc: `docs/ARCHITECTURE.md`.

## Định danh site (vì sao đổi domain an toàn)

Mỗi site có **ID bất biến** (vd `s-a1b2c3`) dùng cho tên container/network/volume. Domain chỉ là
thuộc tính trong `/opt/sites/<id>/site.conf`. Đổi domain = search-replace DB + đổi license domain +
đổi Caddy block, **không** đụng container/volume → không mất dữ liệu.

## Cấu trúc

```
/opt/wp-factory/        # tool (track git)
  bin/iflmmo            # dispatcher + symlink /usr/local/bin/iflmmo
  iflmmo.sh             # bootstrap 1 lệnh
  lib/common.sh ui.sh menu.sh  actions/*.sh
  templates/  caddy/  payload/  VERSION
/opt/sites/<id>/        # data mỗi site: site.conf + compose + .env + wp-content
/opt/backups/<id>/      # backup
```

## Lưu ý nhân bản
- Bộ này standalone (tự mang Caddy, bind 80/443) → để deploy sang VPS mới. Trên VPS đã chạy stack
  khác chiếm 80/443 sẽ xung đột cổng (đổi tạm port Caddy để test).
- `payload/` (plugin/theme) build từ demo qua `iflmmo payload-sync`, không track git.
