# Kiến trúc LATVPS

> Tài liệu mô tả **hệ thống đang chạy thật**, đối chiếu trực tiếp với code trong repo.
> Mỗi mục đều ghi rõ file nguồn để kiểm chứng. Không phải bản nháp đề xuất.
>
> **Cập nhật:** v3.18.0 — viết lại toàn bộ. Bản trước mô tả stack **Caddy** và các script rời
> (`install.sh`, `new-site.sh`, ...) vốn đã bị thay từ lâu bởi **nginx-proxy + lệnh `lat`**.
> Sai lệch này được báo trong [issue #1](https://github.com/affiliatecmscom/latvps/issues/1).
> Phần "Việc còn lại" ở §13 là backlog thật, đã phân loại.

---

## 1. Mục tiêu & nguyên tắc

1. **1 lệnh là chạy** — học viên không cần kiến thức DevOps.
2. **Cô lập là mặc định** — mỗi site là 1 hộp kín; thủng 1 site không lan ra site khác.
3. **Bề mặt tấn công tối thiểu** — chỉ 3 cổng public (22/80/443), không lộ DB/Redis ra ngoài.
4. **Nhân bản dễ** — cùng 1 bộ code chạy trên mọi VPS trắng, không cấu hình thủ công.
5. **Stateless tool, stateful data** — code (`/opt/latvps`) tách khỏi dữ liệu site
   (`/opt/sites`); xoá/cài lại tool không mất site.
6. **ID bất biến** — site định danh bằng `s-<6hex>`, domain chỉ là thuộc tính. Đổi domain
   không tạo lại container.

---

## 2. Sơ đồ tổng thể

```
                            Internet
                               │
                     ┌─────────┴─────────┐
                     │   UFW firewall    │  chỉ mở 22 / 80 / 443
                     └─────────┬─────────┘
                               │ 80, 443
              ┌────────────────▼────────────────┐
              │  latvps_proxy   (nginx-proxy)   │  route theo VIRTUAL_HOST
              │  latvps_acme    (acme-companion)│  tự xin Let's Encrypt
              └────────────────┬────────────────┘
                               │  network: latvps_proxy (CHUNG, external)
       ┌───────────────────────┼───────────────────────┐
       │                       │                       │
 ┌─────▼─────┐           ┌─────▼─────┐           ┌─────▼─────┐
 │ s-aaa_web │           │ s-bbb_web │           │ s-ccc_web │   nginx:alpine
 └─────┬─────┘           └─────┬─────┘           └─────┬─────┘   (CHỈ _web ra proxy)
       │ net: site-s-aaa_internal   (RIÊNG từng site, không thông nhau)
 ┌─────▼──────────────────────────────┐
 │  s-aaa_php    wordpress:fpm-alpine │
 │  s-aaa_db     mariadb:11           │   ← KHÔNG container nào trong nhóm này
 │  s-aaa_redis  redis:7-alpine       │      chạm được network chung
 └────────────────────────────────────┘
 vol: site-s-aaa_db / _redis / _wphtml
 dir: /opt/sites/s-aaa/wp-content
```

**Đường đi request:** Internet → UFW → nginx-proxy (TLS) → `latvps_proxy` → `<id>_web`
(nginx) → fastcgi → `<id>_php` → `<id>_db` / `<id>_redis` qua network nội bộ riêng.
Không có mũi tên nào nối ngang giữa các site.

**Chốt quan trọng:** `php`, `db`, `redis` **không** join `latvps_proxy`
(`templates/wordpress/compose.yml.tmpl`). Chỉ `web` — một nginx:alpine chỉ mount `:ro` —
có mặt trên network chung. PHP, tức vector bị chiếm quyền phổ biến nhất, nằm ngoài mặt chung.

---

## 3. Layout thư mục trên VPS

```
/opt/
├── latvps/                      # TOOL (git, nhân bản sang VPS khác)
│   ├── latvps.sh                #   bootstrap VPS trắng (hỗ trợ LATVPS_REF để ghim bản)
│   ├── bin/lat                  #   CLI DUY NHẤT (menu + subcommand)
│   ├── bin/wp-cli.phar          #   wp-cli (tải lúc setup, gitignore)
│   ├── lib/common.sh            #   helper chung: registry, license, payload, proxy, perms
│   ├── lib/ui.sh                #   hỏi-đáp từng dòng (không whiptail)
│   ├── lib/menu.sh              #   các menu TUI
│   ├── lib/actions/*.sh         #   1 file = 1 lệnh (setup, site_add, backup, cron, ...)
│   ├── templates/wordpress/     #   compose.yml.tmpl + nginx.conf
│   ├── proxy/docker-compose.yml #   stack proxy chung
│   ├── assets/                  #   mu-plugins, child theme, config demo, profile.d
│   ├── payload/                 #   plugin/theme AffiliateCMS (gitignore, tải gated license)
│   ├── tests/                   #   bats - hàm thuần, chạy trên CI
│   ├── .license                 #   license key (chmod 600, gitignore)
│   └── docs/                    #   tài liệu này + QUY-TRINH-PHAT-HANH.md
│
├── sites/                       # DATA (KHÔNG track git)
│   ├── s-a1b2c3/                #   thư mục THẬT, tên = ID bất biến
│   │   ├── site.conf            #     ID/DOMAIN/TYPE/SSL/CANONICAL/LICENSE_KEY (chmod 600)
│   │   ├── docker-compose.yml   #     render từ template
│   │   ├── nginx.conf
│   │   ├── .env                 #     DB_PASSWORD, REDIS_PASSWORD, VIRTUAL_HOST (chmod 600)
│   │   └── wp-content/          #     plugin/theme/uploads/mu-plugins của site
│   └── my-deals.com -> s-a1b2c3 #   SYMLINK tiện tay; thư mục thật vẫn là ID
│
├── proxy/certs/                 # cert: Let's Encrypt + Origin + tự ký (chmod 700)
└── backups/<id>/<stamp>.tar.gz  # backup (chmod 700, chỉ root đọc)
```

**Nguyên tắc:** `latvps` = code (xoá được, cài lại từ git). `sites` + `backups` = dữ liệu
(phải giữ + backup).

> **Bẫy `resolve_site`:** vì `/opt/sites/<domain>` là symlink, `site.conf` của domain cũng
> "tồn tại" — nhánh tắt từng trả về domain thay vì ID, gây site zombie + domain bị chiếm
> vĩnh viễn (fix ở `04aefca`). `resolve_site()` giờ chỉ nhận nhánh tắt khi arg là thư mục
> **thật**. Có test hồi quy: `tests/common.bats`.

---

## 4. Mạng Docker

| Network | Loại | Ai join | Mục đích |
|---|---|---|---|
| `latvps_proxy` | external, tạo 1 lần ở `lat setup` | nginx-proxy, acme-companion, **`<id>_web` của mọi site** | proxy → nginx site. Mặt chung DUY NHẤT. |
| `site-<id>_internal` | compose tự sinh theo project | **web + php + db + redis** của RIÊNG site đó | WP ↔ DB ↔ Redis nội bộ. |

- `db`/`redis`/`php` **không bao giờ** join `latvps_proxy` → từ network chung không có route
  tới bất kỳ database nào.
- Mỗi `internal` do compose sinh theo project name `site-<id>` → các site không thấy nhau.

### Vì sao KHÔNG bật `enable_icc=false` trên `latvps_proxy`

Bản tài liệu cũ đề xuất việc này với lý do "chỉ proxy gọi vào được". **Đó là hiểu sai.**
`com.docker.network.bridge.enable_icc=false` chặn **mọi** giao tiếp container-container trên
bridge đó — bao gồm `latvps_proxy → <id>_web:80`, tức chính đường đi request duy nhất. Bật
lên là toàn bộ site trả 502. (Cơ chế ngoại lệ ngày xưa là `--link` legacy, đã bỏ.)

Lợi ích cũng gần bằng 0: thứ duy nhất trên network chung là các container `nginx:alpine`
mount `:ro`, không chạy PHP. Rủi ro "1 site bị chiếm rồi quét ngang site khác" đã được chặn
ở tầng kiến trúc — bằng cách để php/db/redis ra khỏi network chung — chứ không cần ICC.

---

## 5. Cổng & firewall

| Cổng | Trạng thái | Ghi chú |
|---|---|---|
| 22/tcp | mở (UFW) | SSH. `lat setup` hỏi tắt password auth nếu đã có SSH key. |
| 80/tcp | mở (UFW) | HTTP → nginx-proxy (redirect 443 + ACME challenge). |
| 443/tcp | mở (UFW) | HTTPS → nginx-proxy. |
| 3306 / 6379 / 9000 | **không publish** | db / redis / php-fpm chỉ ở network internal. |

UFW: `default deny incoming` + allow 22/80/443 (`lib/actions/setup.sh`). Không container nào
dùng `ports:` ra host trừ nginx-proxy.

**Kiểm tra runtime:** `assert_ports_private()` (`lib/common.sh`) chạy sau mỗi `lat add`, gọi
`docker port` trên `<id>_db|redis|php` và cảnh báo nếu phát hiện cổng bị publish. Đây là lưới
phụ phòng trường hợp `docker-compose.yml` của site bị sửa tay thêm `ports:`.

---

## 6. Ranh giới cô lập & threat model

**Tình huống:** site A bị chiếm (plugin lỗ hổng → RCE trong `<id>_php` của A).

| Tài nguyên | Làm được gì từ trong site A? | Vì sao bị chặn |
|---|---|---|
| DB/Redis của A | Có (đúng phạm vi A) | Chấp nhận — chỉ mất dữ liệu A |
| DB/Redis của B, C | **Không** | Khác network, không resolve/không route |
| File site B, C | **Không** | Bind-mount riêng, container A không thấy |
| Quét HTTP site khác | **Không** | `_php` không ở trên `latvps_proxy` |
| Root của host | Rất khó | `no-new-privileges`, không privileged, không mount docker.sock |
| Làm sập site khác | Hạn chế | `mem_limit` mỗi container (CPU: xem §13) |

**Điểm tập trung rủi ro còn lại:** `proxy/docker-compose.yml` mount `/var/run/docker.sock`
vào nginx-proxy và acme-companion. Cờ `:ro` **không** làm Docker API thành read-only — nó chỉ
chặn ghi vào *file* socket; ai gọi được API đó vẫn tạo được container privileged → root host.
Nghĩa là RCE trong tầng proxy ≈ mất cả VPS. Đây là đánh đổi có chủ đích (nginx-proxy cần
theo dõi container để route), và là hạng mục ưu tiên ở §13.

---

## 7. Container inventory

| Thành phần | Image | Số lượng | Cổng host | Network | mem_limit |
|---|---|---|---|---|---|
| nginx-proxy | `nginxproxy/nginx-proxy:1.11` | 1 | 80, 443 | latvps_proxy | — |
| acme-companion | `nginxproxy/acme-companion:2.7` | 1 | không | latvps_proxy | — |
| `<id>_web` | `nginx:alpine` | N | không | internal + proxy | 128m |
| `<id>_php` | `wordpress:fpm-alpine` | N | không | internal | 512m |
| `<id>_db` | `mariadb:11` | N | không | internal | 512m |
| `<id>_redis` | `redis:7-alpine` | N | không | internal | 96m |

Tổng container = `2 + 4N`. Image proxy được **pin theo minor** (1.11 / 2.7), không dùng `latest`.

Mọi container site đặt `security_opt: ["no-new-privileges:true"]`.

---

## 8. Dữ liệu, volume & backup

**Trạng thái cần giữ:**
- DB mỗi site → named volume `site-<id>_db`
- Redis → `site-<id>_redis` (cache, mất không sao)
- WP core → `site-<id>_wphtml` (cài lại được)
- `wp-content` → bind-mount `/opt/sites/<id>/wp-content` ← **dữ liệu thật**
- Cert → `/opt/proxy/certs` + volume `acme`

**`lat backup [id|domain|all]`** (`lib/actions/backup.sh`):
1. `mariadb-dump` trong container (mật khẩu đọc từ env **bên trong** container, không lên
   dòng lệnh host → không lộ qua `ps`) → `db.sql`
2. `tar -czf` toàn bộ `/opt/sites/<id>` (wp-content + compose + .env + db.sql)
3. Ghi `/opt/backups/<id>/<YYYYmmdd-HHMMSS>.tar.gz`, `chmod 600`, xoay vòng giữ
   `BACKUP_KEEP` bản (mặc định 14)

Exit code của `tar` **được kiểm**: bỏ qua sẽ báo "đã backup" cho file rỗng/hỏng (vd hết đĩa),
rồi file rác đó vẫn chiếm 1 slot xoay vòng → vài ngày sau xoá sạch bản backup còn tốt.

**`lat restore <id|domain> <file.tar.gz>`:** đọc `tar_id` từ tên thư mục gốc trong archive và
**từ chối** nếu không khớp site đang restore. Không kiểm sẽ khiến `lat restore siteB backupA.tar.gz`
hồi sinh site A (kể cả A đã xoá) mà vẫn báo "phục hồi xong B".

---

## 9. Vòng đời site

```
latvps.sh ──▶ lat setup ──▶ host sẵn sàng (Docker, UFW, swap, fail2ban, proxy, wp-cli, lat)
                                │
   lat add ──────────────────────┼──▶ sinh ID + dir + compose + nginx.conf + .env (pass random)
                                │    cert theo chế độ SSL đã chọn
                                │    copy payload (plugin/theme) + mu-plugins
                                │    up -d → wait_for_db → assert_ports_private
                                │    wp core install → clone demo (tuỳ chọn) → activate license
                                │    cài cron AffiliateCMS → bật Redis object cache → fix_perms
                                ▼
                           site LIVE (https://domain)
                                │
   lat backup / lat domain / lat logs / lat upgrade
                                │
   lat rm ──────────────────────┴──▶ deactivate license + xoá cron + down -v + xoá cert + xoá dir
```

**Rollback:** `_add_rollback()` trong `lib/actions/site_add.sh` chạy ở **mọi** nhánh lỗi:
`down -v` + xoá dir + xoá symlink + xoá cert. Không có nó thì mỗi lần tạo site thất bại sẽ để
lại site zombie và **chiếm domain** khiến học viên không add lại được.

Vì vậy các helper như `wait_for_db()` / `render_template()` **trả về 1 chứ không `die`** —
`die` là `exit`, caller sẽ không bao giờ chạy tới `|| { _add_rollback; }`.

---

## 10. Cập nhật & phát hành

| Lệnh | Cập nhật cái gì |
|---|---|
| `lat update` | **code lat** (`git pull --ff-only` từ nhánh đang bám) |
| `lat update-check` | chỉ kiểm tra có bản mới không (cũng chạy nền lúc đăng nhập SSH) |
| `lat upgrade` | **image** (WP/MariaDB/nginx/redis) từng site + proxy + `apt upgrade` |
| `lat payload-sync` | plugin/theme AffiliateCMS cho site tạo **sau** |
| wp-admin | plugin/theme của site đang chạy (qua license server) |

**Ghim bản (v3.18.0+):**

```bash
# cài/quay về một bản đã tag
LATVPS_REF=v3.17.2 curl -fsSL https://raw.githubusercontent.com/affiliatecmscom/latvps/main/latvps.sh | sudo bash

# gỡ ghim, theo lại bản mới nhất
git -C /opt/latvps checkout main && lat update
```

Tag → checkout detached → `lat update` **cố ý** không kéo đi, và báo rõ cách gỡ ghim.

**Lưới an toàn khi cập nhật:** `act_self_update()` ghi lại commit hiện tại trước khi pull, chạy
`bash -n` trên toàn cây sau khi pull, và **tự `git reset --hard` về commit cũ** nếu bản mới lỗi
cú pháp. CI (`.github/workflows/ci.yml`) chặn từ đầu nguồn: shellcheck + `bash -n` + bats, cộng
`release-guard` bắt buộc tag `vX.Y.Z` khớp file `VERSION`.

---

## 11. Định cỡ tài nguyên

`mem_limit` mỗi site cộng lại tối đa `512 + 512 + 96 + 128 = 1248m`, thực tế nhàn rỗi
≈ **0.5–0.7 GB/site**.

| RAM VPS | Số site khuyến nghị |
|---|---|
| 2 GB | 2–3 |
| 4 GB | 5–7 |
| 8 GB | 12–15 |

`lat setup` **luôn** tạo swapfile 2GB nếu máy chưa có swap (kể cả RAM lớn — nhiều site × 4
container vẫn OOM được lúc tải cao) và đặt `vm.swappiness=10`.

---

## 12. Hardening — đã làm

**Mức host** (`lib/actions/setup.sh`):
- [x] `apt upgrade` toàn bộ ngay lúc setup (đóng lỗ hổng đã biết, không để backlog)
- [x] UFW `default deny incoming`, chỉ 22/80/443
- [x] `fail2ban` jail sshd (5 lần / 10 phút → ban 1 giờ)
- [x] `unattended-upgrades` + tự reboot 3h30 khi bản vá yêu cầu
- [x] SSH key-only (hỏi, và **chỉ hỏi khi đã có** `authorized_keys` → không tự khoá mình ra)
- [x] swapfile 2GB + `vm.swappiness=10`
- [x] Secret `chmod 600`: `.license`, `.env` mỗi site, `site.conf`, backup; `certs/` `chmod 700`

**Mức container** (`templates/wordpress/compose.yml.tmpl`):
- [x] không publish cổng nào ra host (+ `assert_ports_private` kiểm lúc chạy)
- [x] `no-new-privileges:true` mọi container
- [x] `mem_limit` mọi container
- [x] `MARIADB_RANDOM_ROOT_PASSWORD`, mật khẩu DB/Redis random riêng từng site
- [x] Redis bắt buộc `--requirepass` + `maxmemory` + `allkeys-lru`

**Mức ứng dụng** (`templates/wordpress/nginx.conf`, `assets/mu-plugins/`):
- [x] cấm thực thi PHP trong `wp-content/uploads`, `wp-content/cache`, `wp-includes` (webshell)
- [x] chặn `xmlrpc.php` ở cả nginx lẫn WP; bỏ header `X-Pingback`
- [x] rate-limit `wp-login.php` ~10 req/phút/IP
- [x] chặn tải file `.sql .bak .old .log .sh .ini .env .conf` và `/.git`
- [x] security headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`) —
      lặp lại trong block `location ~ \.php$` vì nginx **không** kế thừa `add_header` xuống
      location đã có `add_header` riêng
- [x] FastCGI cache cho khách vãng lai, bypass đúng khi POST / có cookie đăng nhập / admin / REST
- [x] `blog_public=0` mặc định (chống trùng lặp nội dung giữa các site học viên)
- [x] `fix_perms`: dir 755 / file 644 + owner www-data — **bắt buộc**, vì php-fpm chạy uid 82
      còn nginx chạy uid 101; thư mục 0700 sẽ khiến nginx 404 toàn bộ css/js mà php vẫn đọc
      được, nên lỗi rất khó thấy ("vỡ giao diện")

---

## 13. Việc còn lại (backlog đã phân loại)

Nguồn: rà soát nội bộ + [issue #1](https://github.com/affiliatecmscom/latvps/issues/1).

### Đang làm — v3.18.0 "phát hành an toàn"
- [x] Tag mọi bản phát hành + `LATVPS_REF` để ghim/hạ bản
- [x] `latvps.sh` chạy lại **thật sự** cập nhật (trước đây thấy `bin/lat` là bỏ qua, nên lệnh
      curl trong README không bao giờ sửa được máy đang hỏng)
- [x] `lat update` tự rollback khi bản mới lỗi cú pháp
- [x] CI: shellcheck + `bash -n` + bats + release-guard
- [x] Viết lại tài liệu này cho khớp code

### Đã làm — v3.19.0 "siết bề mặt tấn công" (phần không cần VPS/server)
- [x] `cpus:` cạnh mỗi `mem_limit` — db 1.0, php 1.0, redis 0.5, web 0.5
- [x] `healthcheck:` cho db + redis, và `lat status` cảnh báo khi service không khoẻ
- [x] Verify **SHA512** cho `wp-cli.phar` (wp-cli publish sẵn file `.sha512` cạnh phar)
- [x] `unzip_replace_dir()` — tải payload về, **kiểm tra rồi mới tráo**. Bản cũ `rm -rf` thư
      mục đang dùng *trước* khi `unzip`: zip hỏng / server trả HTML / hết đĩa = mất luôn plugin
      đang chạy được, và máy mất mạng thì không tải lại được

### Còn lại — cần VPS thật hoặc cần sửa app.lat.vn

> Ba việc phụ thuộc server (POST body, hash, gate theme) có file riêng:
> **`docs/VIEC-BEN-APP-LAT-VN.md`** — kèm bằng chứng probe và lệnh kiểm chứng lại.
- [ ] `tecnativa/docker-socket-proxy` chắn trước docker.sock (§6). **Cần VPS test**: đụng vào
      đường cấp cert, sai là site mất HTTPS. Lưu ý acme-companion cần `POST`+`EXEC` để reload
      nginx-proxy nên phải tách 2 tầng quyền, không phải đổi 3 dòng
- [ ] SHA256 cho payload plugin/theme + bundle demo. **Chặn ở server**: app.lat.vn phải trả
      hash kèm file thì client mới có gì để đối chiếu
- [ ] Chuyển `license_key` từ query string sang POST body. **Chặn ở server**: endpoint
      `update/download` hiện trả `405 Method Not Allowed` cho POST (route đăng ký GET-only).
      Cùng loại lỗi đã sửa cho `cron_key` ở `585fd3f` — key nằm nguyên văn trong access log
- [ ] Nối `depends_on: condition: service_healthy` sau khi healthcheck được xác nhận trên VPS
- [ ] `healthcheck:` cho php/web — chưa làm vì `wordpress:fpm-alpine` không có sẵn lệnh kiểm
      fpm đáng tin (busybox `nc -z` không chắc có), thà không có còn hơn báo hỏng nhầm

### Sau đó — v3.20.0 "chống mất dữ liệu"
- [ ] Cron `lat backup all` hằng ngày, cài sẵn trong `lat setup`
- [ ] Đích backup offsite tuỳ chọn (rclone → S3/Backblaze) — mất nguyên VPS là mất cả backup
- [ ] Kiểm dung lượng trống trước khi tar (14 bản full × uploads có thể làm đầy đĩa)
- [ ] `lat restore --dry-run` để kiểm backup còn dùng được

### Đã cân nhắc và KHÔNG làm
- **`enable_icc=false` trên `latvps_proxy`** — sẽ làm chết routing, lợi ích ~0. Lý do đầy đủ ở §4.
- **`read_only: true` rootfs + `cap_drop: [ALL]`** — đúng về lý thuyết, nhưng mariadb/nginx cần
  khá nhiều capability và đường ghi tạm; giá trị biên thấp so với rủi ro làm hỏng site học viên.
  Để ngỏ, chưa lên lịch.
