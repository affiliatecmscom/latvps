# Hai biến thể demo chạy song song (iflmmo.affiliatecms.com và iflmmo.lat.vn)

> **TRẠNG THÁI 2026-09-20: GIAI ĐOẠN 1 ĐÃ XONG.** Demo chạy ở https://iflmmo.lat.vn
> (thư mục `/opt/demo-lat`), 152 roundup cộng 12 bài Blog, giữ nguyên toàn bộ coupon, CSDL 17 MB,
> ảnh 5,9 MB, license đã kích hoạt thật. Kết quả đo được để ở cuối mục 5. Giai đoạn 3 trở đi
> chưa làm. Xem thêm `docs/TIEP-TUC.md` mục 0-I bên repo BestProducts.

> **Đây là PLAN, chưa phải mô tả hệ thống đang chạy.** Phần "Hiện trạng" ở §2 là đo thật trên
> VPS `<VPS-dashboard>` ngày 2026-09-20, mỗi dòng đều ghi lệnh kiểm chứng. Phần từ §4 trở đi là
> đề xuất, **chưa code dòng nào**.
>
> Liên quan: [`ARCHITECTURE.md`](ARCHITECTURE.md) §9 vòng đời site,
> [`QUY-TRINH-PHAT-HANH.md`](QUY-TRINH-PHAT-HANH.md) §4 phát hành bundle demo (bản một bundle),
> [`VIEC-BEN-APP-LAT-VN.md`](VIEC-BEN-APP-LAT-VN.md) ba việc phía server đang treo.
> [`QUY-TRINH-TICH-HOP-LAT-REVIEW.md`](QUY-TRINH-TICH-HOP-LAT-REVIEW.md) chặng 2, tích hợp
> phiên bản LAT Review vào LATVPS, độc lập hoàn toàn với bản cũ.

---

## 1. Muốn gì

Hôm nay học viên chạy `lat add <domain> --type affiliatecms` thì luôn nhận **đúng một** bản demo,
lấy từ `iflmmo.affiliatecms.com` (25 bài, 313 sản phẩm, thiên về coupon và deal).

Muốn có **biến thể thứ hai**: `iflmmo.lat.vn`, dựng theo khuôn BestProducts, tức site review sản
phẩm kiểu Wirecutter (roundup "Best X", bảng so sánh, điểm số, tác giả, taxonomy hai cấp). Hai
biến thể **cùng tồn tại**, học viên chọn lúc tạo site.

Vì sao cần hai chứ không thay hẳn: hai biến thể phục vụ hai kiểu kiếm tiền khác nhau. Bản iflmmo
hiện tại nặng về coupon và mã giảm giá. Bản BestProducts nặng về nội dung review và affiliate
Amazon. Học viên chọn ngành nào thì lấy khuôn đó, đỡ phải xoá đi làm lại.

---

## 2. Hiện trạng: đường ống đã có sẵn, chỉ thiếu chỗ rẽ nhánh

Đây là điểm quan trọng nhất của tài liệu. **Không phải xây mới từ đầu.** Cơ chế "demo tự nạp
vào LATVPS" đã chạy thật, đủ ba mắt xích:

| Mắt xích | File | Việc |
|---|---|---|
| Đóng gói | `/opt/demo-iflmmo/tools/build-demo-bundle.sh` | Dump DB demo, bỏ data `wp_users`/`usermeta`/bảng runtime, lọc option bí mật và transient, đóng gói kèm uploads, ghi `bundle.info` có `demo_host=` |
| Phục vụ | `/opt/dashboard/.../Acms/DemoContentController.php` | `GET /update/demo/download?license_key=` , chặn theo license phải tồn tại, `active`, chưa hết hạn |
| Nạp | `lib/common.sh` `fetch_demo_bundle()` + `acms_import_demo_content()` | Tải, nạp đè DB, copy uploads, tạo lại admin, search-replace `demo_host` sang domain mới, ép `blog_public=0` |

Hai lưới an toàn trong bước đóng gói đáng được nhắc riêng vì sau này phải giữ: script quét regex
mẫu key thật, rồi **đối chiếu từng giá trị key đang nằm trong `wp_options` xem có lọt vào dump
không**, thấy là `exit 1`. Lưới thứ hai mới là lưới chặt, vì nó bắt cả những chỗ key bị lưu dưới
tên option mà danh sách chưa liệt kê.

Một chi tiết khéo trong `acms_import_demo_content()`, đừng vô tình phá khi sửa: nó chạy
`ALTER TABLE wp_users AUTO_INCREMENT=1` **trước khi** tạo admin, để admin mới nhận ID 1, trùng
với `post_author` của mọi bài trong dump. Bỏ dòng đó thì admin thành ID 4 và toàn bộ bài demo
mất tác giả.

### Chỗ thắt duy nhất

`config/acms.php` khai **một đường dẫn cứng**:

```php
'demo_bundle' => env('ACMS_DEMO_BUNDLE', '/demo-wp-content/acms-demo-bundle/demo-bundle.tar.gz'),
```

`/demo-wp-content` là mount read only của `/opt/demo-iflmmo/wp-content`. Endpoint không nhận tham
số nào ngoài `license_key`, client cũng không gửi gì khác. Nên **hệ thống chỉ phục vụ được một
bundle**. Đó là toàn bộ việc phải làm.

### Số liệu nền, đo ngày 2026-09-20

| Mục | Giá trị | Lệnh kiểm chứng |
|---|---|---|
| Bundle hiện tại | 2,0 MB, `database.sql` 5,9 MB | `tar -tzvf wp-content/acms-demo-bundle/demo-bundle.tar.gz` |
| `bundle.info` | `demo_host=iflmmo.affiliatecms.com` | `tar -xzOf <bundle> ./bundle.info` |
| Demo iflmmo | 25 bài publish, 6 trang, 313 sản phẩm, uploads 1,2 MB | truy vấn `wp_posts` và `wp_acms_products` |
| BestProducts | 6.341 bài, 49.538 sản phẩm, uploads 91 MB, DB khoảng 600 MB | `wp db query` trên `information_schema` |
| Cổng host đã dùng | 8000, 8083, 8090, 8091, 8092, 8109, 8110, 8111, 8120, 8141 | `ss -tlnp` |
| Cert `*.lat.vn` | `app-origin.pem` có SAN `*.lat.vn, lat.vn` | `openssl x509 -noout -text -in /opt/dashboard/app-origin.pem` |
| DNS `iflmmo.lat.vn` | **chưa phân giải** | `dig +short iflmmo.lat.vn` |
| Mount app dashboard | `/opt/dashboard/app -> /app (rw)` | `docker inspect dashboard_app` |
| Bảng `releases` | có `product_id`, `version`, `zip_file`, `zip_size`, unique `(product_id, version)` | `\d releases` trên `dashboard_db` |

---

## 3. Ba ràng buộc không được vi phạm

**1. Không được làm gãy `lat` bản cũ.** Mọi VPS học viên đang chạy đều gọi
`/update/demo/download?license_key=` không kèm tham số nào khác. Endpoint mới **bắt buộc** phải
mặc định về biến thể iflmmo khi không thấy tham số. Cùng loại ràng buộc đã ghi ở
[`VIEC-BEN-APP-LAT-VN.md`](VIEC-BEN-APP-LAT-VN.md) §1: đừng flip thẳng, phải nhận cả hai một thời gian.

**2. Không được recreate `couponapi_caddy` và `dashboard_app` nếu tránh được.**
`couponapi_caddy` giữ cổng 80/443 cho `app.lat.vn` (license server của mọi khách),
`api.lat.vn`, `tracking.retailcoupons.com`, `pincoup.com`, `ylg.org` và `bestproducts.org`.
`dashboard_app` chính là license server. Recreate là cắt license toàn hệ thống vài giây tới vài
phút. Mọi thay đổi phải đi đường `reload`, không đi đường `up -d --force-recreate`.

**3. Bundle phải tải được trên đường truyền học viên.** `fetch_demo_bundle()` đặt
`curl --max-time 300`. Bundle 100 MB trên đường 3 MB/s là 33 giây, nhưng trên đường 500 KB/s là
hơn 3 phút, và mạng Việt Nam đi quốc tế lúc nghẽn còn chậm hơn. Cộng thêm thời gian `mariadb`
nạp dump. **Biến thể BestProducts phải là bản rút gọn**, không phải cả 6.341 bài.

---

## 4. Hai phương án kiến trúc

### Phương án A: thêm mount thứ hai (làm nhanh, nợ kỹ thuật)

Mount `/opt/demo-bestproducts/wp-content` vào `dashboard_app` thành `/demo2-wp-content`, thêm
`ACMS_DEMO_BUNDLE_2`, rồi `if` trong controller.

- Ưu: ít code.
- Nhược: **phải recreate `dashboard_app`** để thêm mount, vi phạm ràng buộc 2. Và mỗi biến thể
  thứ ba lại thêm một mount, một biến môi trường, một nhánh `if`. Không có lịch sử phiên bản,
  không rollback được về bundle cũ khi bản mới hỏng.

### Phương án B: mô hình hoá bundle demo như một release (KHUYẾN NGHỊ)

Dashboard **đã có sẵn** bảng `releases` (`product_id`, `version`, `zip_file`, `zip_size`,
`released_at`, unique `(product_id, version)`) và thư mục `storage/app/acms-releases/`, đang phục
vụ plugin và theme qua `UpdateController::download()` bằng
`storage_path('app/acms-releases/' . $release->zip_file)`.

Vì `/opt/dashboard/app` là bind mount `rw`, **đặt file bundle vào `storage/app/acms-releases/`
không cần mount mới, không cần recreate container.** Ràng buộc 2 được tôn trọng trọn vẹn.

Cách làm: coi mỗi biến thể là một `product_id`, ví dụ `demo-iflmmo` và `demo-bestproducts`.

- Ưu: dùng đúng khuôn có sẵn thay vì đẻ cơ chế song song (rule E2 của BestProducts: kiểm tính
  năng sẵn có trước khi code). Có lịch sử phiên bản, **hạ bản được** khi bundle mới hỏng, đúng
  tinh thần `update/versions` đã làm cho plugin. Thêm biến thể thứ ba chỉ là thêm một dòng DB.
- Nhược: nhiều code hơn phương án A khoảng một buổi.

**Đề xuất chọn B.** Lý do quyết định không phải là đẹp về kiến trúc mà là ràng buộc 2: A bắt
phải recreate license server, B thì không.

---

## 5. Các bước triển khai

Chia bảy giai đoạn, mỗi giai đoạn có chốt nghiệm thu riêng. **Không sang giai đoạn sau khi chốt
của giai đoạn trước chưa đạt.**

### GĐ 0. Chuẩn bị và đường lùi

- [ ] Sao lưu bundle đang phục vụ:
      `cp /opt/demo-iflmmo/wp-content/acms-demo-bundle/demo-bundle.tar.gz /opt/backups/demo-bundle.$(date +%Y%m%d).BACKUP.tar.gz`
- [ ] Sao lưu `/opt/couponapi/Caddyfile` và `/opt/dashboard/app/config/acms.php`.
- [ ] Ghi lại md5 của bundle hiện tại để sau đối chiếu.
- [ ] Xác nhận `docker exec couponapi_caddy caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile` trả `Valid configuration` **trước** khi đụng vào, để biết điểm xuất phát sạch.

### GĐ 1. Dựng site `iflmmo.lat.vn`  [XONG 2026-09-20]

- [ ] Thêm bản ghi A `iflmmo` trong zone `lat.vn` trên Cloudflare, **bật proxy (cam)**, trỏ về
      `<VPS-dashboard>`. Zone đang dùng NS `jason`/`marlowe.ns.cloudflare.com`.
- [ ] Đặt SSL/TLS của zone ở **Full (strict)**. Cert origin `*.lat.vn` đã phủ subdomain này nên
      **không cần xin cert mới**.
- [ ] Tạo `/opt/demo-lat/` theo khuôn `/opt/demo-iflmmo/`: `docker-compose.yml`, `.env`,
      `wp-content/`. Cổng host đề xuất **8112** (8109 tới 8111 và 8120 đã bận).
- [ ] Bind `127.0.0.1:8112` , **tuyệt đối không** `0.0.0.0`.
- [ ] Nối container vào network `dashboard_default` để Caddy với tới được, giống `iflmmo_wp`.
- [ ] Thêm block vào `/opt/couponapi/Caddyfile`, copy y khuôn `app.lat.vn`:

```
iflmmo.lat.vn {
	tls /etc/caddy/app-origin.pem /etc/caddy/app-origin.key
	encode gzip
	reverse_proxy demo-lat-wp:80
}
```

- [ ] **Sửa Caddyfile tại chỗ** bằng trình soạn thảo, đừng `rsync` hay `cp` đè. File là bind
      mount một file đơn, thay file là đổi inode và container vẫn đọc bản cũ, trong khi
      `caddy validate` báo hợp lệ nên rất dễ tưởng đã xong (đã trả giá một lần, xem memory
      `docker-mount-mot-file-theo-inode`).
- [ ] `docker exec couponapi_caddy caddy validate ...` rồi mới
      `docker exec couponapi_caddy caddy reload --config /etc/caddy/Caddyfile`. **Reload, không recreate.**

**Chốt GĐ 1**: `curl -sI https://iflmmo.lat.vn/` trả 200, và `app.lat.vn` vẫn 200 (kiểm cả hai,
vì reload hỏng là chết cả cụm).

### GĐ 2. Nội dung cho biến thể BestProducts  [XONG 2026-09-20]

- [ ] Nhân bản nội dung từ `bestproducts.org` sang `/opt/demo-lat/` (dump + uploads), hoặc dựng
      từ `acms-starter`. Quyết định này còn treo, xem §8.
- [ ] **Tỉa còn khoảng 100 tới 200 roundup** cùng sản phẩm tương ứng. Mục tiêu kích thước:
      `database.sql` dưới 60 MB trước khi nén, bundle dưới 20 MB.
- [ ] Giữ nguyên taxonomy, tác giả, trang tĩnh, logo, token màu, vì đó mới là thứ đáng làm khuôn.
- [ ] Xoá sạch dữ liệu coupon nếu biến thể này không bán coupon, xem §6.

**Chốt GĐ 2**: `iflmmo.lat.vn` render đủ trang chủ, một trang danh mục, một roundup có bảng so
sánh và nút mua, không rò `%year%`, không lộ placeholder.

### GĐ 3. Tham số hoá bộ đóng gói  [CHƯA LÀM, bắt đầu từ đây]

`build-demo-bundle.sh` hiện khai cứng `DB_SVC="iflmmo-db"` và `ROOT` suy từ vị trí script.

- [ ] Chuyển thành script dùng chung nhận tham số, ví dụ
      `build-demo-bundle.sh --root /opt/demo-lat --db-svc demo-lat-db --product-id demo-bestproducts`.
- [ ] **Giữ nguyên hai lưới an toàn**, không được nới.
- [ ] Bổ sung danh sách option bí mật cho dữ liệu kiểu BestProducts, xem §6.
- [ ] Giữ `.htaccess` chặn web trong thư mục bundle.

**Chốt GĐ 3**: build được cả hai biến thể bằng cùng một script, và chạy lại trên demo iflmmo cho
ra bundle **có cùng danh sách bảng và cùng số file uploads** như bản đang phục vụ. Đây là phép
thử không hồi quy, đừng bỏ.

### GĐ 4. Server phục vụ hai bundle

- [ ] Thêm hai dòng vào bảng `releases`: `product_id` là `demo-iflmmo` và `demo-bestproducts`,
      `zip_file` trỏ tới file trong `storage/app/acms-releases/`.
- [ ] `DemoContentController::download()` nhận thêm tham số tuỳ chọn, ví dụ `?variant=`.
      **Không có tham số thì trả `demo-iflmmo`**, đúng ràng buộc 1.
- [ ] Biến thể không tồn tại thì trả 404 có thông điệp rõ, đừng im lặng rơi về bản mặc định,
      vì im lặng thì học viên nhận nhầm khuôn mà không ai biết.
- [ ] Thêm endpoint liệt kê biến thể, ví dụ `GET /update/demo/variants`, để client hiện danh sách
      thay vì hardcode. Khuôn có sẵn ở `update/versions`.
- [ ] Giữ nguyên toàn bộ phép kiểm license: tồn tại, `active`, chưa hết hạn.

**Chốt GĐ 4**: ba lệnh `curl` phải đúng, chạy từ máy ngoài:
`?license_key=<hợp lệ>` không kèm variant trả **đúng bundle iflmmo cũ, md5 khớp bản backup GĐ 0**;
`?variant=demo-bestproducts` trả bundle mới;
`?license_key=ACMS-0000-0000-0000-0000` trả **403**.

### GĐ 5. Client `lat` cho chọn biến thể

- [ ] `lat add` thêm cờ `--demo <variant>`, và khi chạy tương tác thì hiện menu chọn, lấy danh
      sách từ endpoint ở GĐ 4.
- [ ] `fetch_demo_bundle()` truyền tham số xuống. Không truyền thì hành vi y như cũ.
- [ ] `acms_import_demo_content()` **không cần sửa**: nó đã đọc `demo_host` từ `bundle.info` và
      chỉ rơi về mặc định `iflmmo.affiliatecms.com` khi thiếu. Nhưng **phải kiểm** rằng bundle mới
      có `bundle.info` ghi đúng `demo_host=iflmmo.lat.vn`, nếu không search-replace sẽ trượt và
      site học viên mang đầy URL `iflmmo.lat.vn`.
- [ ] Bump `VERSION`, chạy shellcheck, `bash -n`, bats theo đúng
      [`QUY-TRINH-PHAT-HANH.md`](QUY-TRINH-PHAT-HANH.md) §5.

**Chốt GĐ 5**: CI xanh, và `lat add` bản cũ (chưa có cờ) vẫn tải được bundle mặc định.

### GĐ 6. Nghiệm thu trên VPS trắng

**Không nghiệm thu được trên `<VPS-dashboard>`**: cổng 80 và 443 đã thuộc `couponapi_caddy`, mà
LATVPS cần `nginx-proxy` của riêng nó (hiện máy này không có container đó, không có network
`latvps_proxy`, và `lat ls` trả rỗng). Dựng thử ở đây là đụng cổng với license server.

- [ ] Thuê một VPS trắng, chạy `latvps.sh`.
- [ ] `lat add <domain-test-1> --type affiliatecms` không kèm cờ, xác nhận nhận bundle iflmmo.
- [ ] `lat add <domain-test-2> --type affiliatecms --demo demo-bestproducts`, xác nhận nhận bundle mới.
- [ ] Trên cả hai site kiểm: đếm nút `/go/` trên một bài (license đã kích hoạt chưa, xem memory
      `license-acms-gan-theo-domain`), không còn URL của demo nguồn, `blog_public=0`,
      tác giả bài không rỗng, và **không có link tiếp thị nào của mình còn sót**.

**Chốt GĐ 6**: cả hai site chạy được, và phép tìm chuỗi affiliate tag trong DB site test trả 0 dòng.

### GĐ 7. Phát hành và tài liệu

- [ ] Cập nhật [`QUY-TRINH-PHAT-HANH.md`](QUY-TRINH-PHAT-HANH.md) §4 cho khớp: giờ có hai biến thể.
- [ ] Cập nhật [`ARCHITECTURE.md`](ARCHITECTURE.md) §13 backlog.
- [ ] Cập nhật `README.md` phần Payload.
- [ ] Tag bản phát hành đúng số trong `VERSION`.

### Kết quả thật của GĐ 1 và GĐ 2, đo ngày 2026-09-20

| Hạng mục | Trước khi cắt | Sau khi cắt |
|---|---|---|
| Bài viết | 6.355 | **164** (152 roundup cộng 12 blog) |
| Danh mục | 234 | 168 (xoá 66 con rỗng) |
| Tag | 262 | 43 (xoá 219 rỗng) |
| Ảnh | 6.787 | 188 |
| Sản phẩm | 57.126 | 1.774 |
| Tác giả | 95 | 90 (giữ admin dù rỗng) |
| Coupon | 721 mã, 28 brand | **giữ nguyên** |
| CSDL | 733 MB | **17 MB** |
| Ảnh trên đĩa | 91 MB | **5,9 MB** |

**Chạy lọt trần tài nguyên của học viên**, đây là phép thử quan trọng nhất: php 65/512 MB,
db 117/512 MB, web 18/128 MB, redis 9/96 MB, mọi trang vẫn trả 200.

**Ba chỗ lệch so với khuôn LATVPS gốc**, đều ghi lý do ngay trong `docker-compose.yml` của demo:
network ngoài là `dashboard_default` với alias `demo-lat-web`, publish `127.0.0.1:8112` để kiểm
từ host, và hằng số `PF_ACMS_LICENSE_KEY`. Riêng khoá thì **cố ý chỉ cấp license**, không cấp
khoá AI, Amazon, Crawlbase hay CouponAPI: demo không sinh nội dung và không cào dữ liệu.

**Bốn cạm bẫy đã trả giá, sẽ gặp lại nếu dựng biến thể thứ ba:**

1. Chủ sở hữu file: nguồn uid 33 (Debian), khuôn LATVPS cần uid 82 (Alpine). Phải chạy `fix_perms`
   sau mỗi lần rsync, nếu không PHP không ghi được mà im lặng tới lần ghi đầu tiên.
2. Cache Redis giữ giá trị cũ sau `search-replace`: `wp option get home` vẫn trả domain cũ trong
   khi CSDL đã đúng. Phải `wp cache flush` cộng xoá cache nginx ở container `_web`.
3. wp-cli nằm trong lớp ghi container, tạo lại container php là mất. Đã để sẵn
   `/opt/demo-lat/wpcli-install.sh`, chạy lại sau mỗi lần `docker compose up -d php`.
4. Tên biến CSDL hai bên khác nhau: bestproducts dùng `MYSQL_*` và user `pf_user`, khuôn LATVPS
   dùng `MARIADB_*` và user `wordpress`. Mất 3 lần thử mới dump được vì chuyện này.

**Về noindex**: demo đã gỡ 8 công tắc ép tay trong Rank Math (`pt_*_custom_robots`,
`tax_*_custom_robots`, `author_custom_robots`), nay **chỉ `blog_public=0` điều khiển tất cả**,
đã chứng minh bằng phép thử bật tắt hai chiều (bật ra `index, follow`, tắt ra `noindex, nofollow`).
Cấu hình Rank Math cũ lưu ở option `pf_backup_rankmath_titles`. `pf-compare.php` vẫn giữ noindex
riêng cho `/compare/`, đó là chủ ý, đừng gỡ.

**Việc còn treo của demo**: chưa đổi tên và logo (vẫn mang thương hiệu BestProducts), chưa cài
cron nên `acms_validate_license` không chạy.

---

---

## 6. Rò rỉ dữ liệu: phần nguy hiểm nhất của việc này

Bộ lọc hiện tại viết cho dữ liệu của demo iflmmo. Dữ liệu BestProducts có thêm mấy chỗ **chưa
được phủ**. Đây không phải lo xa, đây là đo thật trên `bestproducts.org` ngày 2026-09-20.

| Chỗ hở | Bằng chứng | Hậu quả nếu bỏ qua |
|---|---|---|
| `pf_indexnow_key` | có trong `wp_options` của BestProducts, **không** nằm trong `SECRET_OPTS` của script | Mọi site học viên dùng chung khoá IndexNow của mình |
| `wp_acms_coupons` (721 dòng) và `wp_acms_coupon_brands` (28 dòng) | bị dump nguyên vẹn, script không loại | Xem dòng dưới |
| `wipe-demo-content.sh` không dọn `acms_coupons`, `acms_coupon_brands`, `pf_blog_jobs` | đọc thẳng script, danh sách bảng dừng ở `rank_math_internal_meta` | Cùng hậu quả |

**Về coupon, nói cho rõ mức độ.** Hôm nay 28 brand trong `wp_acms_coupon_brands` còn trỏ link
trang chủ (`https://aosom.com`, `https://aventon.com`...), nên **chưa rò gì**. Nhưng việc "gắn
link tiếp thị thật cho 28 store" đang nằm trong hàng đợi của BestProducts. **Ngay khi việc đó
xong, mọi site học viên nhân từ bundle này sẽ mang link tiếp thị của mình**, tức học viên bán
hàng thì tiền về ví mình, và tài khoản network nhìn thấy hàng trăm site lạ dùng chung link.
Cùng loại lỗi với memory `amazon-tag-tach-biet-theo-site`.

Nên **thứ tự làm quan trọng**: vá bộ lọc **trước** khi gắn link tiếp thị thật, hoặc chốt rằng
biến thể BestProducts không mang coupon và xoá sạch hai bảng đó lúc đóng gói.

Việc phải làm, gộp lại:

- [ ] Bổ sung `pf_indexnow_key` và rà lại toàn bộ `wp_options` của nguồn mới tìm option kiểu khoá.
- [ ] Quyết: biến thể BestProducts có mang coupon không. Không thì loại hai bảng khỏi dump.
      Có thì phải thay bằng **dữ liệu giả tự dựng**, không dùng dữ liệu CouponAPI thật.
- [ ] Vá `wipe-demo-content.sh` thêm `acms_coupons`, `acms_coupon_brands`, `pf_blog_jobs`.
- [ ] Thêm vào lưới an toàn một phép kiểm: grep giá trị `affiliate_tag` và mọi `affiliate_url`
      của brand trong `database.sql`, thấy là `exit 1`. Lưới hiện tại chỉ soi option, **không
      soi bảng dữ liệu**, mà link tiếp thị nằm ở bảng.

---

## 7. Cạm bẫy đã biết

1. **Bind mount một file thì đừng thay file.** `Caddyfile` mount theo inode. `rsync`/`cp` đè là
   container đọc bản cũ trong khi `caddy validate` vẫn báo hợp lệ. Sửa tại chỗ.
2. **`lat` dùng `SITES_ROOT="/opt/sites"`**, mà `bestproducts.org` đang sống ở
   `/opt/sites/bestproducts` và **không** do `lat` quản. Hiện `lat ls` trả rỗng nên chưa va chạm,
   nhưng nếu có ngày chạy `lat setup` trên máy này thì phải kiểm `lat backup all` và `lat ls` có
   quét nhầm thư mục đó không.
3. **Đừng tin `lat add` chạy xong là xong.** Kiểm số nút `/go/` trên một bài thật. License gắn
   theo domain, chưa kích hoạt là mất sạch nút mua mà trang vẫn trả HTTP 200
   (memory `license-acms-gan-theo-domain`).
4. **`auto-draft` không phải nội dung.** Khi so "demo có đổi không" trước lúc build lại bundle,
   WordPress tự đẻ `auto-draft` rỗng, đừng vì thấy nó mà tưởng nội dung đã đổi.
5. **Bundle 100 MB gần như chắc chắn timeout.** `--max-time 300`. Rút gọn nội dung là bắt buộc,
   không phải tuỳ chọn.

---

## 8. Quyết định còn treo

| # | Câu hỏi | Vì sao chặn |
|---|---|---|
| 1 | Nội dung biến thể hai lấy từ đâu: nhân bản `bestproducts.org` rồi tỉa, hay dựng mới từ `acms-starter` | Đổi hẳn khối lượng GĐ 2. Nhân bản thì nhanh nhưng mang theo rủi ro rò dữ liệu ở §6; dựng mới thì sạch nhưng tốn AI và thời gian |
| 2 | Biến thể BestProducts có mang module coupon không | `acms-starter` **chưa có** module Coupon (đã tìm, không có file nào tên `*Coupon*`). Nếu có thì phải port trước |
| 3 | Rút gọn còn bao nhiêu bài, và tỉa theo tiêu chí nào | Ảnh hưởng kích thước bundle và cảm nhận "site có vẻ đầy đặn" của học viên |
| 4 | Tên `product_id` cho hai biến thể | Đặt rồi thì khó đổi vì client cũ sẽ gửi chuỗi đó mãi |

---

## 9. Lệnh kiểm chứng nhanh

```bash
# Bundle đang phục vụ: kích thước, danh sách file, demo_host
tar -tzvf /opt/demo-iflmmo/wp-content/acms-demo-bundle/demo-bundle.tar.gz | head
tar -xzOf /opt/demo-iflmmo/wp-content/acms-demo-bundle/demo-bundle.tar.gz ./bundle.info

# Đường phục vụ thật (license hợp lệ phải 200, license rác phải 403)
LIC=$(cat /opt/latvps/.license | tr -d '[:space:]')
curl -s -o /dev/null -w '%{http_code}\n' "https://app.lat.vn/wp-json/acms-license/v1/update/demo/download?license_key=$LIC"
curl -s -o /dev/null -w '%{http_code}\n' "https://app.lat.vn/wp-json/acms-license/v1/update/demo/download?license_key=ACMS-0000-0000-0000-0000"

# Bundle KHÔNG được lộ qua web của chính demo
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8110/wp-content/acms-demo-bundle/demo-bundle.tar.gz   # phải 403

# Caddy: luôn validate trước khi reload, và KHÔNG recreate
docker exec couponapi_caddy caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
docker exec couponapi_caddy caddy reload   --config /etc/caddy/Caddyfile

# Cert *.lat.vn có phủ subdomain mới không
openssl x509 -noout -text -in /opt/dashboard/app-origin.pem | grep -A2 "Subject Alternative Name"

# Cổng host nào còn trống
ss -tlnp | grep 127.0.0.1 | grep -oE ':8[0-9]{3}' | sort -u
```
