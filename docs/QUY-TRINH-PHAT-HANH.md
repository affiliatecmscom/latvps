# Quy trình phát hành (plugin / theme / nội dung demo / lệnh lat)

> Đọc file này TRƯỚC khi phát hành bất cứ thứ gì cho học viên.
> Viết ngày 2026-07-15 sau một lần phát hành thật (AI plugin 1.3.22 + rebuild bundle demo).

## 1. Nguyên tắc phải nhớ: push GitHub KHÔNG PHẢI là phát hành

Đây là chỗ dễ nhầm nhất. Có **hai đường hoàn toàn tách biệt**:

| Thứ | Học viên nhận từ đâu | Phát hành bằng cách nào |
|---|---|---|
| Plugin `affiliatecms-pro`, `affiliatecms-ai` | `app.lat.vn` (Dashboard) | `acms:release` hoặc nút Release from Demo |
| Theme `affiliateCMS-theme` | `app.lat.vn` | như trên |
| Nội dung demo (DB + uploads) | `app.lat.vn` | chạy `tools/build-demo-bundle.sh` ở demo |
| Lệnh `lat` (LATVPS) | GitHub `main` | `git push` |

Chỉ có **LATVPS** là phát hành bằng git push. Plugin và nội dung demo thì push bao nhiêu cũng
không tới tay học viên. `lat add` tải plugin từ `app.lat.vn`, **không** từ GitHub.

## 2. Thứ tự đúng khi phát hành

Làm sai thứ tự sẽ ra site lai: LATVPS mới + plugin cũ.

```
1. Sửa code plugin ở /opt/demo-iflmmo/wp-content/plugins/<tên>/
2. Bump Version: trong file plugin chính + hằng số VERSION + viết CHANGELOG.md
3. Commit + push repo demo-iflmmo          <- lưu code, CHƯA phát hành
4. Phát hành plugin lên app.lat.vn         <- BƯỚC NÀY MỚI TỚI TAY HỌC VIÊN
5. (nếu nội dung demo đổi) build lại bundle
6. Trên VPS: lat update  ->  lat payload-sync  ->  lat add
```

## 3. Phát hành plugin / theme

Có 2 cách, kết quả như nhau (cùng gọi `ReleasePackager`):

**Cách A - dòng lệnh (nhanh, dùng được từ SSH):**
```bash
docker exec dashboard_app php /app/artisan acms:release \
  affiliatecms-ai /demo-wp-content/plugins/affiliatecms-ai \
  --changelog="mô tả ngắn cho học viên đọc"
```
- Tham số: `{product} {source} [--changelog=] [--ver=]`
- `source` là đường dẫn **trong container dashboard**: `/demo-wp-content/...`
  (Dashboard mount `/opt/demo-iflmmo/wp-content` read-only vào đó).
- Version tự đọc từ header `Version:` của plugin, có kiểm tra tính nhất quán. Không cần `--ver`.
- Chạy xong là **LIVE NGAY**, mọi site học viên sẽ tự update.

**Cách B - giao diện:** Dashboard `http://localhost:8083` -> AffiliateCMS -> Release from Demo.

### Bắt buộc kiểm chứng sau khi phát hành
Đừng tin dòng `Released ...`. Hỏi thẳng đúng endpoint mà plugin dùng:
```bash
LIC=$(cat /opt/latvps/.license | tr -d '[:space:]')
curl -fsS -G "https://app.lat.vn/wp-json/acms-license/v1/update/check" \
  --data-urlencode "plugin=affiliatecms-ai" \
  --data-urlencode "version=<phiên bản CŨ>" \
  --data-urlencode "license_key=$LIC" \
  --data-urlencode "domain=iflmmo.affiliatecms.com"
# mong đợi: {"update_available":true,"version":"<phiên bản MỚI>",...}
```
Muốn chắc hơn nữa thì tải zip về và kiểm tra header Version bên trong.

## 4. Phát hành nội dung demo (DB + uploads)

**Chỉ cần làm khi NỘI DUNG demo đổi** (bài, trang, sản phẩm, logo, sidebar, menu).
Sửa code plugin **không** liên quan tới bundle: bundle chỉ chứa DB + uploads, plugin đi đường payload.

```bash
cd /opt/demo-iflmmo
cp wp-content/acms-demo-bundle/demo-bundle.tar.gz /tmp/bundle.BACKUP.tar.gz   # đường lùi
bash tools/build-demo-bundle.sh
```

Kiểm tra "nội dung có thật sự đổi không" trước khi build (tránh thay artifact đã test bằng bản chưa test):
```bash
PW=$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2-)
docker compose exec -T iflmmo-db sh -c "mariadb -uwordpress -p'$PW' wordpress -e \
  \"SELECT post_type, post_status, COUNT(*) FROM wp_posts \
    WHERE post_modified > '<ngày build bundle cũ>' GROUP BY post_type, post_status;\""
```
Lưu ý: `auto-draft` là bản nháp rỗng WordPress tự tạo khi bấm "Add New Post" rồi bỏ đi.
**Nó không phải nội dung thật** - đừng vì thấy nó mà tưởng demo đã đổi.

Script tự sanitize (bỏ data `wp_users`/`usermeta`, bảng runtime, option secret, transient, widget_block
AdSense) và có 2 lưới an toàn: quét pattern secret + grep giá trị key thật, thấy là ABORT.

### Kiểm chứng bundle mới
```bash
mkdir /tmp/bcheck && tar -xzf wp-content/acms-demo-bundle/demo-bundle.tar.gz -C /tmp/bcheck
grep -c "INSERT INTO \`wp_users\`" /tmp/bcheck/database.sql        # phải = 0
grep -q "$(cat /opt/latvps/.license | tr -d '[:space:]')" /tmp/bcheck/database.sql && echo "LỘ KEY"
grep -q widget_block /tmp/bcheck/database.sql && echo "CÒN ADSENSE"
# so với bản backup: kích thước dump, số file uploads, số bảng có data phải khớp
```
Rồi kiểm tra đường phục vụ thật:
```bash
LIC=$(cat /opt/latvps/.license | tr -d '[:space:]')
curl -fsS -o /tmp/live.tar.gz "https://app.lat.vn/wp-json/acms-license/v1/update/demo/download?license_key=$LIC"
md5sum /tmp/live.tar.gz wp-content/acms-demo-bundle/demo-bundle.tar.gz   # phải khớp nhau
curl -s -o /dev/null -w '%{http_code}\n' "https://app.lat.vn/wp-json/acms-license/v1/update/demo/download?license_key=ACMS-0000-0000-0000-0000"  # phải 403
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8110/wp-content/acms-demo-bundle/demo-bundle.tar.gz  # phải 403
```

## 5. Phát hành lệnh lat (LATVPS)

```bash
cd /opt/latvps
# sửa code -> bump VERSION -> commit
git push          # <- đây LÀ phát hành: VPS nào chạy `lat update` là nhận ngay
```
`lat update` = `git pull --ff-only` từ `main`.

**Quirk phải nhớ:** `lat update` chạy code **CŨ** rồi mới `git pull`, nên bước nào mới thêm vào
`self_update` chỉ có hiệu lực từ lần update **kế tiếp**. Nếu thấy thiếu: chạy `lat update` lần 2,
hoặc `lat setup` (idempotent).

## 6. Cập nhật trên VPS học viên / VPS test

```bash
lat update          # lấy code lat mới nhất từ GitHub
lat payload-sync    # BẮT BUỘC sau khi phát hành plugin: payload/ đang cache bản CŨ
lat add <domain>    # site mới sẽ có plugin mới + nội dung demo mới
```
Bỏ `lat payload-sync` là `lat add` dùng lại plugin cũ trong cache, dù đã phát hành bản mới.

Site đã chạy sẵn thì không cần làm gì: plugin tự hiện thông báo update trong wp-admin
(qua `update/check`), học viên bấm update như plugin WordPress bình thường.

## 6.1. Site "vỡ giao diện" / nghi bị cache -> ĐỪNG tin ngay là cache

Bug `umask` (đã vá ở lat 3.17.1) có triệu chứng **giống hệt cache hỏng**: trang vẫn load,
HTML render, wp-admin vào được, nhưng **CSS/JS trả 404** nên giao diện vỡ và admin plugin
không boot. Nguyên nhân: thư mục `0700` do php-fpm (uid 82) tạo, còn nginx chạy uid 101 nên
không traverse được. Xoá cache KHÔNG bao giờ sửa được, file vẫn nằm đúng chỗ trên đĩa.

Phân định trong 5 giây:
```bash
lat version
curl -sI https://<domain>/wp-content/themes/affiliateCMS-theme/style.css | head -1
ls -ld /opt/sites/<domain>/wp-content/themes/affiliateCMS-theme
```
- style.css **404** hoặc thư mục **700** -> **không phải cache**, là bug quyền.
- `lat version` = **3.17.0** -> chưa có bản vá (cài lại trước khi `lat update` là dính).
- style.css **200** + thư mục **755** -> lúc đó mới thật sự là cache.

Chữa tại chỗ, không cần cài lại site (`<id>` lấy từ `lat ls`):
```bash
lat update && lat update
lat payload-sync
docker exec <id>_php find /var/www/html/wp-content -type d -exec chmod 755 {} +
docker exec <id>_php find /var/www/html/wp-content -type f -exec chmod 644 {} +
```

## 6.2. ĐỪNG xoá Telemetry.php

`src/Core/Telemetry.php` (có ở **cả** `affiliatecms-pro` lẫn `affiliatecms-ai`) trông như
telemetry nhưng **là kênh license + công tắc tắt từ xa**: cùng một request vừa gửi beacon
`/hs` vừa **nhận cờ điều khiển về** (`force_disable`, `blacklisted`/`suspended`/`revoked`
-> tắt AI / `acms_force_disable`), kèm `detectClone()`.

Xoá nó = **mất vĩnh viễn khả năng tắt AI trên site bị crack, hết hạn hoặc bị thu hồi license**.
Bản telemetry chết trong `Bootstrap::performSystemCheck` đã được gỡ ở PRO 1.7.18; đó là thứ
khác, một chiều, và không liên quan tới `Telemetry.php`.

## 7. Checklist rút gọn

- [ ] Bump `Version:` + hằng số VERSION + CHANGELOG
- [ ] `php -l` các file đã sửa (dùng `docker exec iflmmo_wp php -l ...`)
- [ ] Test hành vi thật trên demo, dọn sạch dữ liệu test, xác nhận DB demo về nguyên trạng
- [ ] Commit + push repo demo-iflmmo
- [ ] `acms:release` -> **verify bằng `update/check`**, không tin thông báo
- [ ] Nội dung demo có đổi thật không? Nếu có: backup bundle cũ -> build -> verify -> so với backup
- [ ] LATVPS: bump VERSION -> commit -> push
- [ ] VPS: `lat update` -> `lat payload-sync` -> `lat add`

## 8. Ranh giới giữa các dự án

| Dự án | Vai trò | Được làm gì ở đây |
|---|---|---|
| `/opt/demo-iflmmo` | Nguồn plugin/theme + nội dung demo | Sửa, commit, push, build bundle |
| `/opt/latvps` | Script cài đặt cho học viên | Sửa, commit, push |
| `/opt/dashboard` | app.lat.vn, phát hành + license | **Chỉ dùng tính năng** (`acms:release`). Không sửa code, có repo và luồng chat riêng |
| `/opt/couponapi` | Caddy proxy domain demo | Không đụng. Có luồng chat riêng |

Dashboard chạy Octane/FrankenPHP: nếu có sửa code bên đó thì cần
`php artisan route:cache && php artisan config:cache` + `docker restart dashboard_app`.
