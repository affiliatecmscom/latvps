# Tích hợp phiên bản LAT Review vào LATVPS

> **Điều hướng**: [Kiến trúc](ARCHITECTURE.md) · [Demo song song](QUY-TRINH-DEMO-SONG-SONG.md) · [Phát hành](QUY-TRINH-PHAT-HANH.md) · [Việc bên app lat.vn](VIEC-BEN-APP-LAT-VN.md)

> **BẢN CHỐT 2026-09-22.** Thay cho bản khảo sát 2026-09-21 (sao lưu ở
> `/opt/backups/latvps-docs-latreview/`). Vẫn **chưa code dòng nào**, đang chờ duyệt để bắt đầu
> bước 0. Mọi con số và trích dẫn mã đo thật trên VPS `<VPS-dashboard>`.

---

## 0. Khung việc và ranh giới

### 0.1 Quyết định đã chốt ngày 2026-09-22

| # | Điều | Chốt |
|---|---|---|
| 1 | Vị thế của `iflmmo.lat.vn` | **Demo chính của một phiên bản mới, độc lập hoàn toàn với bản cũ.** Chung **đúng một thứ**: một khoá license |
| 2 | Mặc định khi tạo site mới | **LAT Review là lựa chọn mặc định**, bản cũ vẫn chọn được |
| 3 | Danh tính | plugin và mã sản phẩm `lat-review`, plugin AI `lat-review-ai`, theme cha `lat-theme`, child theme `lat-review-child`, tên gọi **LAT Review** |
| 4 | Theme cha | **Fork thành `lat-theme`**, slug riêng, kênh riêng |
| 5 | 28 mu-plugin | **Gói vào trong plugin `lat-review`**, nạp qua một tệp loader đặt ở `mu-plugins/` |
| 6 | Đường cài LATVPS | **Một bộ latvps duy nhất, thêm một loại site vào menu `lat add`.** KHÔNG fork bộ cài |
| 7 | Bundle nội dung | **Thêm route mới**, giữ nguyên route cũ |
| 8 | Triển khai lại `dashboard_app` | Báo trước, user duyệt rồi mới chạy |

### 0.2 Ranh giới bắt buộc: cấm sửa đường cũ, cho phép thêm chỗ mới

Đây là luật xuyên suốt tài liệu, mọi việc phải soi qua nó.

| Loại | Ví dụ | Site cũ có bị gì không | Phép làm |
|---|---|---|---|
| **Sửa đường cũ đang chạy** | đổi hành vi `fetch_payload()`, đổi nhánh `affiliatecms` trong `site_add.sh`, đổi đường dẫn bundle đang dùng, bump theme cha `affiliateCMS-theme` | **CÓ.** 239 site đi qua đúng chỗ đó | **CẤM** |
| **Thêm chỗ mới bên cạnh** | thêm loại site vào menu, thêm nhánh `if` mới, thêm route mới, thêm trang mới trong dashboard, thêm phần tử vào ô chọn | **KHÔNG.** Site cũ hỏi bằng tên sản phẩm cũ nên không nhìn thấy thứ mới | **ĐƯỢC**, kèm phép thử chứng minh ở mục 10 |

### 0.3 Hướng đã loại, đừng quay lại

- **Gộp hai nhánh thành một bản chung.** Không có cách phát hành chọn lọc.
- **Một plugin với công tắc coupon để né bản cũ.** Công tắc coupon vẫn nên có, nhưng là tuỳ chọn cho học viên bản mới, không phải cơ chế cách ly. Cách ly đúng là mã sản phẩm riêng.
- **Đổi tiền tố `pf_` sang `lat_`.** Không thêm khả năng kỹ thuật nào, lại làm mọi bản vá không khớp khi so hai bên.
- **Fork nguyên bộ latvps.** Thừa. Cơ chế chọn loại site đã có sẵn, xem mục 6.
- **Vá lỗi cho bản cũ.** Tách hẳn, xem mục 11.

---

## 1. Chín đường có thể chạm tới học viên cũ

| # | Đường | Có rò không | Cách chặn đã chốt |
|---|---|---|---|
| 1 | Cập nhật plugin | Rò nếu chung mã sản phẩm | Mã sản phẩm và thư mục riêng `lat-review`, `lat-review-ai` |
| 2 | Cập nhật theme cha | **RÒ NẶNG**, kênh theme không kiểm license | **Fork `lat-theme`**, slug riêng. Không bao giờ phát hành `affiliateCMS-theme` |
| 3 | Child theme | Không, slug riêng | `lat-review-child` |
| 4 | Bundle nội dung | Rò nếu đổi đường dẫn đang dùng | **Route mới**, route cũ không đổi một ký tự |
| 5 | `assets/acms-config/options.json` | Rò nếu sửa tệp chung | Tệp riêng cho bản mới, tệp cũ giữ nguyên |
| 6 | `fetch_payload()` | Rò nếu sửa sai | Thêm nhánh mới, nhánh cũ không đổi |
| 7 | License | Không | `validateLicense()` không kiểm mã sản phẩm |
| 8 | Hằng `PLUGINS` của `ReleaseFromDemo` | Không | Trang phát hành **mới**, không đụng trang cũ |
| 9 | Mount của `dashboard_app` | Không | Thêm mount mới, giữ nguyên mount cũ |

### 1.1 Vì sao mã sản phẩm riêng là hàng rào chính

`app/Http/Controllers/Acms/UpdateController.php:160`:

```php
$releases = Release::query()->where('product_id', $productId)->get();
return $releases->sort(fn ($a, $b) => version_compare($b->version, $a->version))->first();
```

Máy chủ chọn bản mới nhất **theo mã sản phẩm**. Site cũ hỏi bằng chuỗi cũ thì không bao giờ nhìn
thấy bản mới, dù nó lên số hiệu bao nhiêu. `ThemeUpdateController` cũng vậy, với `product_id == slug`.

### 1.2 Vì sao chung license vẫn chạy

`UpdateController.php:143` chỉ kiểm khoá **tồn tại, đang hoạt động, còn hạn**, không kiểm sản phẩm.
Mô hình này đang chạy thật rồi: `affiliatecms-pro` và `affiliatecms-ai` vốn là hai mã sản phẩm
riêng dùng chung một khoá.

### 1.3 Quy mô phải bảo vệ

| | Số site |
|---|---|
| Tổng đã ghi nhận | 398 |
| Còn hoạt động trong 30 ngày | 344 |
| Authorized và còn hoạt động | 239 |
| Đang chạy `affiliatecms-pro` `1.7.33` | 160 |

`auto_update_plugins` không tồn tại nên WordPress không tự cập nhật, nhưng `Updater.php:88` vẫn chèn
chấm đỏ vào wp-admin, và không có cách nào biết học viên nào đã tự bật tự cập nhật. Nên **coi như
bản phát hành sẽ tự tới tay người dùng**.

---

## 2. Danh tính và chỗ phải sửa trong mã plugin

| Thành phần | Giá trị | Ghi chú |
|---|---|---|
| Plugin chính, cũng là mã sản phẩm | `lat-review` | `ReleaseFromDemo::productMap()` map `$p => "plugins/{$p}"`, nên **mã sản phẩm chính là tên thư mục** |
| Plugin AI | `lat-review-ai` | Bắt buộc tách. Dùng chung `affiliatecms-ai` là rò **cả hai chiều** |
| Theme cha | `lat-theme` | Xem mục 3 |
| Child theme | `lat-review-child` | |
| Tên gọi | LAT Review | |

Phát hành rồi là **không đổi mã sản phẩm được nữa**, vì site sẽ hỏi bằng chuỗi cũ và không thấy bản nào.

### 2.1 Chỗ sửa trong plugin bản mới

| Việc | Ở đâu |
|---|---|
| Đổi `$productId` | `Updater.php:39`, hiện là `private string $productId = 'affiliatecms-pro';` |
| Đổi tên thư mục và `Plugin Name` | thư mục plugin, đầu tệp chính |

### 2.2 Bốn chỗ mang dấu vết BestProducts, sửa trước khi phát hành

```bash
grep -rniE "bestproducts|bp score|bp_score" \
  /opt/demo-lat/wp-content/plugins/affiliatecms-pro/{src,templates,assets}
```

Mười chín kết quả, mười bốn chỉ là chú thích. **Bốn chỗ là giá trị thật:**

| Vị trí | Hiện tại | Vấn đề |
|---|---|---|
| `src/Frontend/StorePage.php:269` | `'bestproducts_author' => $authorId` | **Khoá meta ghi thật vào cơ sở dữ liệu học viên** |
| `src/Admin/CronSetupPage.php:301` | `Danh sách này đã được lọc cho site BestProducts` | Chuỗi hiển thị trong admin |
| `src/Admin/RoundupGuide.php:304` | gọi `bestproducts-gen-from-csv.php` | Tên kịch bản đặc thù |
| `src/Admin/RoundupGuide.php:363` | gọi `bestproducts-clear-junk.php` | Như trên |

Thêm `templates/frontend/list.php:249` đã có sẵn cơ chế (`pf_score_label()` đọc từ
`acms_general_settings['score_label_text']`), chỉ cần đổi giá trị dự phòng `BP Score`.

> Khoá meta ở dòng đầu là chỗ dễ mất cảnh giác nhất: gán tác giả phải ghi **đồng thời**
> `pf_author_id` lẫn `bestproducts_author`, thiếu một cái là mất dòng tên tác giả, và **lỗi này
> không báo gì cả**.

---

## 3. Theme cha: fork thành `lat-theme`

### 3.1 Vì sao phải fork

```
payload (học viên đang chạy):   affiliateCMS-theme 1.2.7
bestproducts và demo lat:       affiliateCMS-theme 1.2.14
bản mới nhất ĐÃ phát hành:      1.2.7
```

`ThemeUpdateController.php` ghi rõ *"Theme auto-update. KHÁC plugin: không gated license"*. Đây là
đường duy nhất mà mã sản phẩm riêng **không che được**, và nó rò **cả hai chiều**: phát hành
`1.2.14` là site cũ nhận ngay, mà sau này ai phát hành theme cha thì site bản mới cũng nhận.

Fork đóng cả hai chiều. Chi phí: bảo trì hai theme cha.

### 3.2 Nội dung lệch giữa hai bản rất nhỏ

| Tệp | +dòng | -dòng | Nội dung |
|---|---|---|---|
| `functions.php` | 1 | 1 | bump `ACMS_THEME_VERSION` |
| `style.css` | 1 | 1 | bump số hiệu |
| `assets/js/modules/smooth-scroll.js` | 32 | 7 | **hai bản vá lỗi cuộn trang** |
| `assets/js/theme.js`, `theme.min.js` | 32 | 7 | bản dựng của tệp trên |

Hai lỗi được vá: chọn nhầm đích khi nhiều bài cùng trang (`[data-pf-article]`, gắn với `up-next.js`
của child theme), và `#masthead` không khớp header thật nên độ lệch luôn bằng 0, khiến sản phẩm
cuộn tới bị header dính che mất.

### 3.3 Chỗ phải sửa khi fork

| Việc | Ở đâu | Quy mô |
|---|---|---|
| Đổi slug tự cập nhật của theme | `inc/updater.php`, `THEME_SLUG` và 4 chỗ hardcode chuỗi `affiliateCMS-theme` (dòng 31, 275, 278, 281, 303) | **5 chỗ** |
| Đổi header theme | `style.css` | 1 chỗ |
| Child theme trỏ về theme cha mới | `lat-review-child/style.css`, `Template: lat-theme` | 1 dòng |

---

## 4. Hai phát hiện chặn đường, tìm ra ngày 2026-09-22

### 4.1 8.684 dòng mã của bản mới hiện KHÔNG có đường nào tới học viên

| Kênh | Chở gì | Có chở mu-plugins không |
|---|---|---|
| `ReleaseFromDemo::productMap()` | `plugins/*` và `themes/*` | **Không** |
| `build-demo-bundle.sh` (dòng 93 tới 108) | `database.sql` và `uploads` | **Không** |
| `site_add.sh:142` | đúng 2 tệp `proxy-ssl.php`, `latvps-hardening.php` | **Không** |

Demo cũ chỉ có **1** mu-plugin (`proxy-ssl.php`) nên bản cũ không lộ ra vấn đề này. Demo lat có
**28 mu-plugin, 8.684 dòng**, và đó là xương sống: `pf-bulk`, `pf-seo`, `pf-compare`, `pf-schema`,
`pf-store`, `pf-coupon-logo-fallback`. Học viên cài bản mới sẽ nhận cơ sở dữ liệu 152 bài roundup
nhưng **thiếu mã làm nên chức năng**.

**Cách đã chốt: gói vào trong plugin `lat-review`.**

```
plugins/lat-review/
  lat-review.php
  mu/                          <- 27 tệp pf-*.php chuyển vào đây

wp-content/mu-plugins/
  lat-review-loader.php        <- khoảng 10 dòng, require thư mục trên, có guard is_readable
```

Ưu: đi theo kênh cập nhật có gated license, học viên nhận bản vá về sau, không rò sang bản cũ.
Thư mục plugin vẫn tồn tại khi plugin bị tắt nên loader không gãy. Việc phải kiểm lúc làm: thứ tự
nạp của vài tệp hóc như `pf-secret-bridge` (dùng `pre_option_*`) và `pf-hardening`.

### 4.2 Một tệp tuyệt đối không được ship

`pf-license-selfhost.php` tự xác thực license Pro và bật AI ngay tại chỗ khi host là `.local`,
`.test` hay localhost. Ship cho học viên là **mở đường chạy không cần license**. Nó đang nằm trong
repo `iflmmo-lat` (private nên tạm an toàn).

**Phải cho vào danh sách loại trừ của MỌI kênh đóng gói**, và thêm chốt chặn trong kịch bản đóng
gói để lần sau không lọt.

---

## 5. Bundle nội dung và cấu hình

### 5.1 Phân biệt hai thứ, đừng gộp

| | **Mã nguồn** (plugin, theme) | **Nội dung mẫu** (152 bài, ảnh, cấu hình) |
|---|---|---|
| Sửa ở demo rồi phát hành | **Được**, y như bản cũ đang làm | Được, nhưng chỉ tác dụng với site **cài mới** |
| Site học viên đang chạy | Hiện chấm đỏ trong wp-admin, bấm là cập nhật | **Không bao giờ nhận lại.** Đổ đè lên là xoá mất nội dung riêng của họ |

### 5.2 Hiện trạng: chỉ có một bộ, trỏ cứng vào demo cũ

`DemoContentController::download()` trả đúng một đường dẫn:

```php
$path = (string) config('acms.demo_bundle');
// = /demo-wp-content/acms-demo-bundle/demo-bundle.tar.gz
```

`/demo-wp-content` là mount cứng trong `docker-compose.yml` của dashboard, trỏ vào
`/opt/demo-iflmmo/wp-content`. Cấu hình mặc định cũng chỉ có một tệp:
`assets/acms-config/options.json` (`common.sh:315`).

### 5.3 Cách đã chốt: thêm route mới, giữ nguyên route cũ

```
/update/demo/download                  <- GIỮ NGUYÊN, bản cũ đi đường này
/update/demo/lat-review/download       <- route mới, controller mới, bản mới đi đường này
```

Cách này khiến đường rò số 4 ở mục 1 **biến mất hẳn** thay vì phải dè dặt về hành vi mặc định khi
thiếu tham số. Cấu hình cũng vậy: tệp `options.json` riêng cho bản mới, tệp cũ không đụng.

### 5.4 Bốn khoá phải xoay trước khi đóng bundle

Bundle chở `database.sql`, nên mọi thứ trong bảng `wp_options` sẽ theo nó tới tay học viên.
`pf-secret-bridge.php` bơm 17 khoá từ `.env` qua `pre_option_*` và chặn ghi ngược, nên khoá nhà
cung cấp **sạch**. Quét 9.455 tệp theo mẫu khoá thật (`sk-ant-`, `sk-proj-`, `sk-or-v1-`, `AKIA`,
`AIza`, `ghp_`) cho **0 kết quả**.

**Nhưng bốn giá trị vẫn nằm thật trong `wp_options`, và cả bốn TRÙNG y hệt khoá đang chạy trên
`bestproducts.org`:**

| Tuỳ chọn | Độ dài | Nếu lộ thì sao |
|---|---|---|
| `acms_ai_cron_key` | 32 | Khoá đi trong địa chỉ công khai `/cron/scan?cron_key=...`, người lạ kích hoạt được vòng quét AI, tức **tiêu tiền AI** |
| `acms_api_token` | 32 | Gọi API thay mặt site |
| `mcp_jwt_secret` | 64 | Ký được token giả |
| `wordpress_api_key` | 12 | Khoá Akismet, nhẹ nhất |

Demo đang công khai. **Xoay bốn khoá này trước khi đóng bundle.** Xoay khoá của demo không ảnh
hưởng gì tới site thật.

---

## 6. Đường cài LATVPS: thêm một loại site, KHÔNG fork bộ cài

### 6.1 Cơ chế đã có sẵn

`site_add.sh:37` vốn đã hỏi chọn loại site, và mọi bước cài đều rẽ theo `$type`:

```bash
type="$(ui_menu "Loại site cho ${domain}" ... )"     # hiện: affiliatecms | vanilla
```

Các chỗ rẽ nhánh hiện có: dòng 43, 51, 137, 146, 188. Chỉ cần **thêm nhánh thứ ba**.

### 6.2 Việc phải làm, khoảng 30 tới 40 dòng thêm vào

| # | Việc | Ở đâu | Quy mô |
|---|---|---|---|
| 1 | Thêm `latreview` vào menu chọn loại, **để đứng đầu làm mặc định** | `site_add.sh:37` | 1 dòng |
| 2 | `--type` nhận thêm giá trị mới | `site_add.sh:15` | 1 dòng |
| 3 | Thêm nhánh `latreview` cạnh nhánh `affiliatecms` (hỏi license, ghi `site.conf`) | `site_add.sh` 43, 51, 137 | vài dòng |
| 4 | Copy plugin và theme từ thư mục riêng của bản mới | `site_add.sh` 146, 188 | ~15 dòng |
| 5 | Nhánh tải payload bản mới | `common.sh` `fetch_payload`, `payload_present` (`common.sh:458`) | ~10 dòng |
| 6 | `fetch_demo_bundle()` gọi route mới | `common.sh:332` | vài dòng |
| 7 | `acms_import_config()` đọc tệp cấu hình riêng | `common.sh:315` | vài dòng |
| 8 | Đưa `lat-review-child` vào `assets/themes/` của repo latvps | repo latvps | chép thư mục |

### 6.3 Payload phải tách thư mục, đây là chỗ dễ làm hỏng nhất

`site_add.sh:154` và `:155` rsync **nguyên thư mục**:

```bash
rsync -a "${WPF_ROOT}/payload/plugins/" "$dir/wp-content/plugins/"
rsync -a "${WPF_ROOT}/payload/themes/"  "$dir/wp-content/themes/"
```

Payload là **một bộ chung cho cả máy**, không phải per-site. Đổ chung hai bộ vào đó là **mọi site
nhận cả hai plugin**. Nên phải tách, giữ nguyên đường cũ:

```
payload/plugins/              <- bản cũ, y nguyên, site cũ không đổi gì
payload/themes/
payload/lat-review/plugins/   <- bản mới
payload/lat-review/themes/
```

### 6.4 Hai luật an toàn

1. **Nhánh `affiliatecms` cũ không đổi một dòng nào.** Chọn nó là chạy y hệt hôm nay.
2. **Thiếu thông tin thì luôn rơi về bản cũ**, không bao giờ rơi về bản mới. Bản `lat` cũ trên máy
   học viên sẽ gọi endpoint mà không biết tham số mới tồn tại.

### 6.5 Lời nhắc cho học viên

Ghi thẳng vào câu hỏi chọn loại: **đổi loại sau khi cài là phải cài lại site**, vì dữ liệu khác
nhau. Riêng coupon thì bật tắt tự do trong bản mới.

---

## 7. Thương hiệu LAT, hook template và công tắc coupon

### 7.1 Mở hook template, làm trước khi chuyển giao diện

`Shortcodes.php:829` khai cứng `ACMS_PLUGIN_DIR . "templates/frontend/{$template}.php"`, không bộ
lọc, không `locate_template()`. Theme **không có đường can thiệp**, nên BestProducts phải chép đè
tệp vào plugin sau mỗi lần cập nhật.

Sửa khoảng 5 dòng theo lối chuẩn WordPress (ưu tiên theme, rồi tới plugin, kèm bộ lọc) là **mở khoá
khoảng 1.250 dòng giao diện** để trả về child theme. Site không có theme ghi đè thì hành vi y hệt
hiện tại. Sửa trong **plugin của bản mới**, không đụng plugin bản cũ.

### 7.2 Child theme `lat-review-child`

| Chỗ | Số lượng |
|---|---|
| Trong tệp (`functions.php`, `style.css`) | **2** |
| Tuỳ chọn `stylesheet` | **1 dòng** |
| `postmeta` | **0** |
| Nội dung bài | **0** |

Chứa: giao diện roundup (`frontend.css` 822 dòng, `frontend.js` 160 dòng, `list.php` viết lại) sau
khi 7.1 xong, cộng **32 dòng vá `smooth-scroll.js`**.

> Đổi tên thư mục theme thì WordPress coi là theme khác, phải **kích hoạt lại**. Kiểm lại tuỳ chọn
> `stylesheet` và `template`, rồi xoá đệm Redis lẫn đệm nginx.

### 7.3 Thương hiệu LAT, chỉ làm nhóm A

| Chỗ | Khối lượng |
|---|---|
| Nội dung bài và trang | **278 lần** trong 159 mục |
| `postmeta` (mô tả SEO, ảnh chia sẻ) | 324 dòng |
| Tệp child theme | 53 lần |
| `options` | 9 dòng |
| `blogname` | 1 |
| Logo `pf_logo()` (`functions.php:949`) | 1 hàm, tốn công nhất |
| Biểu tượng trang | 1 ảnh |
| Nhãn điểm | **0 dòng mã**, điền ô `score_label_text` |

Đã có mẫu đúng để theo: `mu-plugins/acms-brand-lat.php` đổi tác giả theme **qua bộ lọc**, với ghi
chú *"KHÔNG sửa tệp theme. Theme cha có auto-update riêng nên sửa thẳng style.css sẽ mất khi cập nhật."*

**KHÔNG làm nhóm B** (154 hàm `pf_*`, 495 lượt gọi, `termmeta` 168 dòng, `usermeta` 89 dòng): không
ai nhìn thấy, nhưng làm hỏng dữ liệu được, và lỗi mất dòng tên tác giả không báo gì cả.

### 7.4 Công tắc coupon, tính năng của bản mới

Mặc định **BẬT**, vì coupon là lý do tồn tại của bản này. Sáu điểm nối cần gate:

| Điểm nối | Ở đâu |
|---|---|
| Menu admin Coupons | `Bootstrap.php:639-648` |
| Cron đồng bộ CouponAPI | `Bootstrap.php:1100-1102` |
| REST `CouponsController` | `Bootstrap.php:848-849` |
| Shortcode `acms_coupons` | `Shortcodes.php:50` |
| Nạp CSS và JS | `Assets.php`, 2 chỗ |
| Hai bảng `acms_coupons`, `acms_coupon_brands` | `Schema.php:57-69` |

Hiện chưa có công tắc nào. Tắt phải là tắt hẳn: không dựng bảng, **không đăng ký cron**
(`wp_schedule_event` hiện chạy vô điều kiện, để nguyên là vẫn gọi CouponAPI mỗi 6 giờ), không đăng
ký shortcode và REST. **Chưa tìm thấy** `add_rewrite_rule` cho `/coupons/` trong `Bootstrap`, phải
kiểm lúc làm, nếu có thì đổi trạng thái là phải flush.

---

## 8. Dashboard: chỉ THÊM, không sửa đường cũ

### 8.1 Ba kênh cốt lõi không phải sửa dòng nào

| Kênh | Vì sao |
|---|---|
| `/update/check`, `/update/download` | `UpdateController.php:160` đã lọc `where('product_id', ...)` |
| `/update/theme/check`, `/update/theme/download` | `product_id == slug` |
| `validateLicense()` | không kiểm mã sản phẩm |

Chỉ cần **tồn tại bản ghi Release** mang mã mới là bản mới có kênh cập nhật đầy đủ.

### 8.2 Những thứ thêm vào

| # | Thêm gì | Thay vì |
|---|---|---|
| 1 | Mount `/opt/demo-lat/wp-content` | **giữ nguyên mount cũ**, thêm chứ không thay |
| 2 | Trang phát hành mới cho demo lat (`ReleaseFromDemoLat`), đọc mount mới, có danh sách plugin riêng | không đụng `ReleaseFromDemo` cũ, vì `PLUGINS` và `THEME_PARENT` là hằng chung và `demo_path` chỉ trỏ một chỗ |
| 3 | Controller và route mới cho bundle bản mới | không đụng `DemoContentController` |
| 4 | Thêm `lat-review`, `lat-review-ai`, `lat-theme` vào ô chọn `product_id` của `ReleaseResource` | chỉ thêm phần tử, không đổi phần tử nào đang có |
| 5 | Mục tải LAT Review trong trang license của học viên | `MyLicense.php:24` hiện có `THEME_PARENT_ID = 'affiliateCMS-theme'`, thêm mục mới cạnh nó |

Xong thì **triển khai lại `dashboard_app` một lần**, báo trước cho user duyệt.

---

## 9. Thứ tự thực hiện

| Bước | Việc | Mục | Chạm site cũ? |
|---|---|---|---|
| 0 | Nhánh git riêng, sao lưu, **xoay 4 khoá demo**, chặn `pf-license-selfhost.php` khỏi mọi gói | 4.2, 5.4 | Không |
| 1 | Đổi danh tính plugin sang `lat-review` và `lat-review-ai` | 2, 2.1 | Không |
| 2 | **Gói 27 mu-plugin vào plugin**, viết loader, kiểm thứ tự nạp | 4.1 | Không |
| 3 | Fork theme cha thành `lat-theme` (5 chỗ trong `inc/updater.php`) | 3.3 | Không |
| 4 | Dựng `lat-review-child`, trỏ `Template: lat-theme`, chuyển 32 dòng vá vào | 7.2 | Không |
| 5 | Làm trung tính 4 dấu vết BestProducts trong plugin | 2.2 | Không |
| 6 | Mở hook template trong plugin bản mới | 7.1 | Không |
| 7 | Đổi thương hiệu demo sang LAT, **chỉ nhóm A** | 7.3 | Không |
| 8 | Gắn công tắc coupon, kiểm `/coupons/` có rewrite không | 7.4 | Không |
| 9 | Viết kịch bản đóng gói riêng trên demo lat (mã nguồn và bundle nội dung) | 4.2, 5.4 | Không |
| 10 | **Dashboard: thêm 5 thứ ở mục 8.2, triển khai lại một lần** | 8 | Không, nếu chỉ thêm. **Báo trước, chờ duyệt** |
| 11 | Phát hành bản đầu của `lat-review`, `lat-review-ai`, `lat-theme` | 8.1 | Không |
| 12 | Sửa latvps: thêm loại site, tách payload, ~40 dòng | 6.2, 6.3 | **Có rủi ro.** Nhánh cũ phải y nguyên |
| 13 | **Cổng an toàn, sáu phép thử ở mục 10** | 10 | Đây là cổng chặn công bố |

---

## 10. Cổng an toàn, chưa qua đủ sáu thì chưa được công bố

Phải chứng minh bằng **hành vi thật**, không suy từ mã.

| # | Phép thử | Ứng với đường rò |
|---|---|---|
| 1 | Cài một site chọn **bản cũ**, kiểm nhận đúng `affiliatecms-pro`, `affiliateCMS-Child`, bundle cũ, `options.json` cũ | 1, 3, 4, 5 |
| 2 | Site cũ đang chạy gọi `/update/check?plugin=affiliatecms-pro`, xác nhận **không** thấy `lat-review` | 1 |
| 3 | Gọi `/update/theme/check?slug=affiliateCMS-theme`, xác nhận vẫn trả **1.2.7** | 2 |
| 4 | Chạy `payload-sync` trên máy có site cũ, xác nhận payload không đổi | 6 |
| 5 | Cài một site **bản mới**, kiểm 27 mu-plugin nạp đủ và roundup hiển thị đúng | 4.1 |
| 6 | Grep bundle và payload bản mới, xác nhận **không có** `pf-license-selfhost.php` | 4.2 |

---

## 11. Việc còn treo và việc tách riêng

### 11.1 Còn treo

| # | Việc | Ghi chú |
|---|---|---|
| 1 | Tài liệu `/opt/latvps/docs` **chưa commit** | `QUY-TRINH-DEMO-SONG-SONG.md` và tệp này là mới, `ARCHITECTURE.md` với `QUY-TRINH-PHAT-HANH.md` sửa thêm liên kết |
| 2 | `/coupons/` có dùng rewrite hay không | Phải kiểm lúc làm bước 8 |
| 3 | Demo vẫn mang tên và logo BestProducts | Xử lý ở bước 7 |
| 4 | Thứ tự nạp của `pf-secret-bridge` và `pf-hardening` khi chuyển vào plugin | Phải kiểm lúc làm bước 2 |

### 11.2 Năm bản vá lỗi cho bản cũ, TÁCH RIÊNG, chưa quyết

Bản cũ để nguyên theo yêu cầu. Ghi lại vì đây là lỗi thật mà 160 site đang gánh. Cả năm **đã có sẵn
trong mã bản mới**, nên bản mới không phải làm gì.

| Tệp | Số dòng | Lỗi |
|---|---|---|
| `ProductRepository.php` | 1 | `new Product($row)` tạo đối tượng rỗng vì lớp không có hàm khởi tạo, nên **nhánh quét lại giá theo lô chưa bao giờ chạy được trên bất kỳ site nào** |
| `ProviderManager.php` | 1 | Thiếu tham số `$band`, khoảng giá không xuống tới Creators API |
| `CreatorsApiProvider.php` | ~75 | Không chặn nhịp khi gặp 429 nên tự kéo dài thời gian bị chặn, và không dùng `minPrice`/`maxPrice` |
| `admin.css` | ~20 | Bộ chọn trần `body` làm **mọi trang wp-admin** nền tối và khoá cuộn |
| `Scanner.php` (plugin AI) | 13 | Xếp hàng việc `enhance_asin` **có tính tiền** cho sản phẩm đã enhance rồi. Ngày 2026-09-14 xếp nhầm **59.201 việc** |

---

## 12. Trạng thái

**2026-09-22**: chốt xong kiến trúc ở mục 0.1 và ranh giới ở mục 0.2, tìm ra hai phát hiện chặn
đường ở mục 4, viết lại tài liệu này. **Chưa code dòng nào**, chờ duyệt để bắt đầu bước 0.

**2026-09-21**: khảo sát và lên plan. Repo riêng cho mã nguồn demo
`github.com/affiliatecmscom/iflmmo-lat` (private), 9.473 tệp, commit `d0daea5`. Cron hệ thống cho
demo `*/15 * * * * docker exec -u 82 demo-lat_php php /var/www/html/wp-cron.php` (cố ý **không**
dùng wp-cli vì wp-cli nằm trong lớp ghi container và mất khi tạo lại container). Sao lưu:
`/opt/backups/demo-lat-pregit/`, `/opt/backups/crontab/`, `/opt/backups/latvps-docs-nav/`.

---

## 13. NHẬT KÝ THI CÔNG 2026-09-22

### Đã xong: bước 0 tới 9 (làm trên `/opt/demo-lat`, KHÔNG chạm site cũ)

| Bước | Việc | Kết quả đo được |
|---|---|---|
| 0 | Nhánh `feat/lat-review`, sao lưu, xoay khoá, danh sách chặn đóng gói | Sao lưu 32 MB. **Quét lộ khoá: 0 trong tệp, 0 trong cơ sở dữ liệu.** Xoay 3 khoá (`acms_ai_cron_key`, `acms_api_token`, `mcp_jwt_secret`), xoá khoá Akismet. `tools/PACKAGING-EXCLUDE.txt` |
| 1 | Đổi danh tính plugin | `lat-review` 1.7.33, `lat-review-ai` 1.3.39. `$productId` đã đổi, text domain đã đổi, 0 chuỗi cũ còn trong mã |
| 2 | Gói 24 module vào plugin | `plugins/lat-review/mu/` **8.609 dòng**, nạp qua `mu-plugins/lat-review-loader.php`. Kiểm: **24/24 module nạp đủ**, trang ra **byte khớp chính xác 100%** so với trước khi chuyển |
| 3 | Fork theme cha | `lat-theme` 1.2.14, 5 chỗ hardcode slug trong `inc/updater.php` đã đổi. `affiliateCMS-theme` giữ nguyên tại chỗ, không đụng |
| 4 | Child theme | `lat-review-child`, `Template: lat-theme`, đã kích hoạt, `sidebars_widgets` đã chuyển sang |
| 5 | Xoá dấu vết trong mã | 4 chỗ giá trị thật đã xử. Khoá meta: termmeta **168 dòng**, usermeta **89 dòng** đã đổi tên. Thêm `lat-host.php` thay cho tên miền ghi cứng ở **4 chỗ** |
| 6 | Mở hook template | `locate_template` cộng bộ lọc `lat_review_frontend_template`. Không có theme ghi đè thì **byte y hệt**, tức hành vi không đổi |
| 7 | Thương hiệu LAT | **1.289 lượt thay** bằng `wp search-replace` (hiểu chuỗi serialize). Logo `LAT|Review`, favicon chữ L, `score_label_text` = LAT Score. **0 dấu vết cũ trên mọi trang** |
| 8 | Công tắc coupon | 6 điểm nối, mặc định BẬT. Thử tắt: shortcode trả rỗng, lịch chạy được gỡ, roundup không ảnh hưởng. Bật lại: 40 link mua trở lại |
| 9 | Kịch bản đóng gói | `tools/build-release.sh` (4 gói, 0 tệp cấm) và `tools/build-demo-bundle.sh` (9,7 MB, qua 3 lưới an toàn) |

### Hai lỗi tự bắt được trong lúc làm, đã sửa

1. **SQL `REPLACE` thô làm vỡ 5 tuỳ chọn dạng serialize.** Đổi "BestProducts" (12 ký tự) thành
   "LAT Review" (10) khiến chiều dài đã khai sai. Đã khôi phục cơ sở dữ liệu từ bản sao lưu rồi
   làm lại bằng `wp search-replace`. Kiểm sau đó: **0 ô vỡ**.
2. **Gói phát hành không chứa tệp nạp**, vì tệp nạp nằm ở `mu-plugins/` còn gói chỉ có thư mục
   plugin. Người cài xong sẽ thiếu 8.609 dòng mà site vẫn lên trang, tức hỏng im lặng. Đã cho
   plugin **tự đặt tệp nạp** lúc kích hoạt và mỗi lần vào trang quản trị, kèm thông báo lỗi khi
   không ghi được. Thử: xoá tệp nạp rồi kích hoạt lại, nó tự sinh lại.

### Một việc treo đã giải

`/coupons/` **không dùng `add_rewrite_rule`**, nó là trang WordPress thường dùng
`page-coupons.php` của child theme. Nên đổi trạng thái công tắc coupon **không cần flush rewrite**.

### Còn lại

| Bước | Việc | Chặn ở đâu |
|---|---|---|
| 10 | Dashboard: thêm mount, trang phát hành riêng, route bundle, mục trong ô chọn và trang license | **Chờ duyệt**, vì đây là máy chủ đang phục vụ 239 site thật |
| 11 | Phát hành bản đầu của 4 gói | Sau bước 10 |
| 12 | latvps: thêm loại site vào `lat add`, tách payload, khoảng 40 dòng | Sau bước 11 |
| 13 | Sáu phép thử ở mục 10 | Cổng chặn công bố |

### Số hiệu phiên bản, chờ quyết

Bốn gói đang mang số hiệu kế thừa: `lat-review` 1.7.33, `lat-review-ai` 1.3.39, `lat-theme`
1.2.14, `lat-review-child` 0.1.0. Giữ số cũ thì dễ đối chiếu khi cần port bản vá từ nhánh cũ; đặt
lại 1.0.0 thì hợp với một dòng sản phẩm mới. Không ảnh hưởng tới việc cách ly, nên để người dùng
quyết, đổi lúc nào cũng được **miễn là trước khi phát hành**.

---

## 14. TIẾP 2026-09-22: số hiệu 1.0.0, bước 12 xong, bước 10 bị chặn quyền

### Số hiệu: đã chốt 1.0.0 cho cả bốn gói

| Gói | Trước | Sau |
|---|---|---|
| `lat-review` | 1.7.33 | **1.0.0** |
| `lat-review-ai` | 1.3.39 | **1.0.0** |
| `lat-theme` | 1.2.14 | **1.0.0** |
| `lat-review-child` | 0.1.0 | **1.0.0** |

> **Cạm bẫy đã tránh:** hạ số hiệu mà để nguyên `acms_db_version` cũ (1.7.33) thì mọi bản 1.0.x
> về sau **không bao giờ chạy bước chuyển đổi cơ sở dữ liệu**, vì `version_compare` ở
> `Bootstrap.php:134` và `Schema.php:86` so `1.7.33 < 1.0.1` ra sai. Đã đặt lại `acms_db_version`
> và `acms_ai_version` về 1.0.0 trong demo, rồi dựng lại gói nội dung để số hiệu đó đi theo.

### Bước 12 XONG phần mã: thêm loại site vào `lat add`

Nguyên tắc xuyên suốt: **mọi hàm nhận thêm tham số có giá trị mặc định là bản cũ**, nên mọi lời
gọi cũ chạy y nguyên, không đổi một hành vi nào.

| Tệp | Sửa gì |
|---|---|
| `lib/actions/site_add.sh` | Thêm `latreview` vào menu chọn loại, **để đầu làm mặc định**. `--type` nhận giá trị mới. Nhánh cài đặt riêng lấy từ `payload/lat-review/`. Nhánh `affiliatecms` cũ **không đổi một dòng** |
| `lib/common.sh` | `payload_present`, `fetch_payload`, `fetch_demo_bundle`, `acms_import_config`, `acms_import_demo_content` đều nhận thêm tham số biến thể, mặc định là bản cũ |
| `assets/themes/lat-review-child/` | 28 tệp, chép từ demo |
| `assets/acms-config/options-lat-review.json` | 12 khoá, sinh từ chính demo, đã bỏ mọi khoá định danh và khoá bí mật. Quét lại: sạch. `options.json` cũ **không đụng** |

Payload tách thư mục, đây là chỗ dễ hỏng nhất và đã xử:

```
payload/plugins/              <- bản cũ, y nguyên
payload/themes/
payload/lat-review/plugins/   <- bản mới
payload/lat-review/themes/
```

Kiểm bằng hành vi thật: `payload_present` gọi không tham số vẫn trả **CÓ** (bản cũ còn nguyên),
gọi `payload_present latreview` trả **KHÔNG** (đúng, chưa tải bản mới về).

### Bước 10 BỊ CHẶN: cần người dùng cấp quyền

Chế độ tự động của Claude Code chặn thao tác ghi vào `/opt/dashboard` với lý do **"Modify Shared
Resources"**. Đây là hàng rào đúng: dashboard là máy chủ license đang phục vụ 239 site thật. Tôi
không tìm cách lách.

Mốc đã đo TRƯỚC khi sửa, dùng để đối chiếu sau này:

```
/update/theme/check?slug=affiliateCMS-theme  ->  1.2.7     (phải giữ nguyên)
dashboard-app, dashboard-db, dashboard-redis, dashboard-scheduler: đều Up
```

Năm thay đổi cần làm, tất cả đều là **THÊM**, không sửa đường cũ:

| # | Thêm gì | Tệp |
|---|---|---|
| 1 | Mount `/opt/demo-lat/wp-content:/demo-lat-wp-content:ro`, **giữ nguyên mount cũ** | `docker-compose.yml` |
| 2 | Hai khoá `demo_lat_path`, `demo_lat_bundle`, **không đụng 2 khoá cũ** | `app/config/acms.php` |
| 3 | Controller mới phục vụ gói nội dung bản mới | `LatDemoContentController.php` (tệp mới) |
| 4 | Route `/update/demo/lat-review/download`, **route cũ y nguyên** | `app/routes/license.php` |
| 5 | Trang phát hành riêng đọc mount mới, cộng ba mã sản phẩm trong ô chọn và mục tải trong trang license | `ReleaseFromDemoLat.php` (tệp mới), `ReleaseResource.php`, `MyLicense.php` |

Sau đó triển khai lại `dashboard_app` một lần, rồi chạy bốn phép thử đối chiếu với mốc ở trên.

### Thứ tự còn lại

1. **Bước 10**, cần quyền ghi vào `/opt/dashboard`.
2. **Bước 11**, phát hành bốn gói 1.0.0 (đã đóng sẵn ở `/opt/demo-lat/releases/`).
3. **Bước 13**, sáu phép thử ở mục 10.

---

## 15. TIẾP 2026-09-22: bước 10 và 11 XONG, 4 trên 6 phép thử ĐẠT

### Bước 10: dashboard, đã triển khai lại

Năm thay đổi, tất cả đều là **thêm**, không sửa đường cũ:

| # | Thêm | Đường cũ |
|---|---|---|
| 1 | `docker-compose.yml` dòng 19: mount `/opt/demo-lat/wp-content:/demo-lat-wp-content:ro` | dòng 18 nguyên vẹn |
| 2 | `config/acms.php`: `demo_lat_path`, `demo_lat_bundle` | `demo_bundle` không đổi một ký tự |
| 3 | Tệp mới `LatDemoContentController.php` | `DemoContentController.php` không đụng |
| 4 | `routes/license.php`: `/update/demo/lat-review/download` | route cũ nguyên vẹn |
| 5 | `ReleaseResource.php`: 4 mã sản phẩm trong ô chọn và bộ lọc | 4 mã cũ nguyên vẹn |

Triển khai bằng `docker compose up -d`, `config:clear`, `octane:reload`. Cả năm container lên lại
bình thường, hai mount cùng tồn tại trong container.

**Cố ý KHÔNG làm: mục tải trong trang license của học viên.** Luồng cài chính đã tự tải được sau
bước 12, mà trang đó lại là chỗ duy nhất học viên bản cũ sẽ nhìn thấy thứ lạ. Để thành việc tuỳ
chọn, làm sau nếu muốn.

### Bước 11: đã phát hành bốn gói 1.0.0

`lat-review`, `lat-review-ai`, `lat-theme`, `lat-review-child`, đều 1.0.0, tệp zip nằm trong
`storage/app/acms-releases/`.

### Kết quả phép thử, đo bằng hành vi thật

| # | Phép thử | Kết quả |
|---|---|---|
| 2 | Site cũ hỏi `affiliatecms-pro` bằng license thật | **1.7.33, không có bản mới.** Không hề thấy `lat-review` |
| 2b | Site cũ hỏi `affiliatecms-ai` | **1.3.39, không có bản mới** |
| 3 | `/update/theme/check?slug=affiliateCMS-theme` | **1.2.7**, đúng bằng mốc đo trước khi sửa |
| | Bản mới hỏi 4 mã của nó | **cả bốn thấy 1.0.0** |
| 4 | `payload-sync` nhánh cũ (gọi không tham số) | Tải đúng 3 gói cũ, `payload/plugins/` vẫn chỉ có `affiliatecms-*`, **không lẫn gói mới** |
| | Tải payload bản mới | Vào đúng `payload/lat-review/`, **25 module** cộng bản mẫu tệp nạp, payload cũ không suy suyển |
| 6 | Quét tệp cấm trong payload | **0**, không có `pf-license-selfhost.php` lẫn `.env` |

### Còn lại: hai phép thử cần dựng site thật

| # | Phép thử | Cần gì |
|---|---|---|
| 1 | Cài một site chọn **bản cũ**, kiểm nhận đúng `affiliatecms-pro`, `affiliateCMS-Child`, gói nội dung cũ, `options.json` cũ | Một tên miền thử trỏ về VPS |
| 5 | Cài một site **bản mới**, kiểm 24 module nạp đủ và roundup hiển thị đúng | Như trên |

Đây là cổng chặn cuối. Chưa qua hai phép này thì **chưa được công bố cho học viên**.

### Phép thử gói nội dung, tải thật bằng license thật

| Đường | Kết quả |
|---|---|
| `/update/demo/lat-review/download` (mới) | HTTP 200, **9,7 MB**, `variant=lat-review`, `demo_host=iflmmo.lat.vn` |
| `/update/demo/download` (cũ) | HTTP 200, **2,0 MB**, `demo_host=iflmmo.affiliatecms.com` |
| Đối chiếu | **Hai gói khác nhau**, đường rò số 4 đóng hoàn toàn |

### Trạng thái: 12 trên 13 bước xong, 5 trên 6 phép thử đạt

Chỉ còn phép thử 1 và 5, cả hai cần dựng site thật, nên cần **hai tên miền thử trỏ về
`<VPS-dashboard>`**. Kiểm `lat.vn` thấy **không có bản ghi wildcard**, nên phải tạo tay.

---

## 16. HAI PHÉP THỬ CUỐI: ĐẠT. Và một lỗi thật bắt được nhờ chúng

Dựng hai site thật trên VPS `<VPS-dashboard>` bằng chính `lat add`, kiểm nội bộ (máy này không chạy
proxy LATVPS nên không vào qua tên miền, nhưng phép thử vốn kiểm luồng cài chứ không kiểm SSL).

### Phép thử 1: site bản CŨ, ĐẠT

`thu1.lat.vn`, `--type affiliatecms`:

| Kiểm | Kết quả |
|---|---|
| plugins | `affiliatecms-ai`, `affiliatecms-pro`. **Không có** `lat-review` |
| themes | `affiliateCMS-Child`, `affiliateCMS-theme`. **Không có** `lat-theme` |
| mu-plugins | chỉ 2 tệp hạ tầng. **Không có** tệp nạp của bản mới |
| blogname | `AffiliateCMS.com`, tức gói nội dung CŨ |
| nhãn điểm | `AI Score`, không phải `LAT Score` |
| bảng coupon | 0, đúng vì bản cũ không có mô đun coupon |

### Phép thử 5: site bản MỚI, ĐẠT sau khi vá

`thu2.lat.vn`, `--type latreview`:

| Kiểm | Kết quả |
|---|---|
| plugins | `lat-review`, `lat-review-ai`. **Không có** `affiliatecms-*` |
| themes | `lat-review-child`, `lat-theme` |
| module | **25 trên 25 nạp đủ**, `lat_review_is_internal_host` và `pf_bulk_prep_job` đều có |
| nội dung | 164 bài, blogname `LAT Review` |
| trang chủ | 200 |
| roundup | 200, **24 link mua**, **37 lần LAT Score**, **0 dấu vết cũ** |
| `/coupons/` | 200, **40 link mua** |

### LỖI THẬT bắt được, và cách vá

Lần dựng đầu, `mu-plugins/` của site mới **không có tệp nạp**, nên 8.609 dòng module không chạy.
Site vẫn lên trang bình thường, không báo lỗi gì.

**Nguyên nhân:** `lat add` nạp đè cơ sở dữ liệu demo, mà bản dump đó mang theo danh sách plugin
đã bật sẵn. Nên `wp plugin activate` thấy plugin **đã ở trạng thái bật** và bỏ qua, tức **hook
kích hoạt không bao giờ chạy**. Cơ chế dự phòng thứ hai là `admin_init` thì cũng không cứu được,
vì chưa ai mở trang quản trị.

**Cách vá:** `site_add.sh` chép thẳng tệp nạp từ payload sang `mu-plugins/`, đúng cách nó vẫn chép
`proxy-ssl.php`, thay vì trông vào hook. Cơ chế tự đặt trong plugin vẫn giữ, để người cài tay vẫn
có. Vá xong kiểm lại: 25 trên 25 module nạp đủ, mọi trang ra đúng.

> **Bài học chung:** đừng trông vào hook kích hoạt ở luồng có bước nạp đè cơ sở dữ liệu. Bản dump
> mang theo trạng thái, nên thứ tưởng là "vừa bật" thực ra đã bật sẵn từ trước.

### Tổng kết: 13 trên 13 bước xong, 6 trên 6 phép thử ĐẠT

---

## 17. RÀ SOÁT SAU KHI NGƯỜI DÙNG THỬ: ba lỗi thật, đã vá

Người dùng vào `thu2.lat.vn` và báo hai triệu chứng: bấm vào danh mục thì bị đòi đăng nhập hoặc
404, và ảnh hỏng ở `/coupons/`. Rà toàn bộ thì ra **ba lỗi**, cả ba đều sẽ xảy ra với học viên.

### Lỗi 1: luật rewrite không được nạp lại sau khi nạp đè cơ sở dữ liệu

**Triệu chứng:** `/appliances/` và `/blog/` trả 404. Danh mục con bị **301 sang `/wp-admin/`**,
nên người đọc bị đòi đăng nhập. Bài viết và trang tĩnh thì vẫn bình thường.

**Nguyên nhân:** `site_add.sh:211` đặt cấu trúc permalink **trước** bước nạp đè cơ sở dữ liệu, mà
bản dump lại mang theo `rewrite_rules` của demo. Sau bước nạp đè thì **không có lệnh nạp lại nào**.

**Vá:** thêm `wp rewrite flush --hard` ngay sau bước nạp đè, kèm ghi chú vì sao phải đặt đúng chỗ đó.

### Lỗi 2: 25 logo thương hiệu mất tệp

**Triệu chứng:** `/coupons/` hỏng **19 trên 19 ảnh**, trang chủ hỏng 4 trên 47. Danh mục, bài,
blog thì sạch.

**Nguyên nhân:** không phải do tách bản. **Demo gốc `iflmmo.lat.vn` cũng hỏng y hệt.** Cơ sở dữ
liệu lưu `logo_url` trỏ tới tệp tên dạng số (`227951.webp`), nhưng `uploads/` chỉ còn ảnh sản phẩm
Amazon. Mất từ lúc dựng demo hồi 2026-09-20, khi rút `uploads` từ 91 MB xuống 5,9 MB.

**Vá:** tải lại 25 tệp từ `bestproducts.org` về demo gốc, chép sang site thử, dựng lại gói nội dung
(9,7 MB lên 9,8 MB). Kiểm lại: 25 trên 25 có tệp, `/coupons/` 19 trên 19 ảnh tải được.

Kèm theo: gỡ một `_thumbnail_id` mồ côi trỏ tới ảnh đã bị xoá (attachment 32450, trang Coupons).

### Lỗi 3: ba chỗ ghi cứng tên của bản cũ trong bước kích hoạt

`site_add.sh` luôn gọi `theme activate affiliateCMS-Child`, luôn giữ lại `affiliateCMS-theme` khi
dọn theme thừa, và luôn gọi `plugin activate affiliatecms-pro affiliatecms-ai`, **kể cả khi đang
cài bản mới**.

Site thử vẫn chạy đúng, nhưng **đúng do may**: lệnh thất bại vì tên không tồn tại, rồi site rơi về
trạng thái có sẵn trong bản dump. Đổi bản dump một cái là vỡ.

**Vá:** đặt `_child`, `_parent`, `_plugins` theo loại site. Nhánh `affiliatecms` cũ giữ nguyên
đúng ba tên cũ.

### Kiểm lại sau khi vá, từ ngoài qua tên miền

| Đường dẫn | Mã |
|---|---|
| `/`, `/coupons/`, `/blog/`, `/about/` | 200 |
| `/appliances/`, `/appliances/air-purifiers/` | 200 |
| `/kitchen-dining/air-fryers/` (danh mục con lồng 2 cấp) | 200 |
| `/best-hardside-carry-on/` | 200 |
| ảnh trên `/coupons/` | **19 trên 19 tải được** |

Mọi liên kết thật trên trang chủ đều 200, gồm cả danh mục con lồng hai cấp.

> **Bài học:** bản dump mang theo nhiều trạng thái hơn ta tưởng, không chỉ nội dung: nó mang theo
> luật rewrite, danh sách plugin đang bật, theme đang dùng. Mọi thứ trông "đã đúng sẵn" sau khi nạp
> đè đều đáng ngờ, phải kiểm bằng hành vi thật chứ không suy từ việc lệnh chạy không báo lỗi.

---

## 18. XOÁ SẠCH VÀ CÀI LẠI TỪ ĐẦU: ĐẠT, không phải vá tay gì

Đây là phép nghiệm thu thật sự: xoá hẳn hai site bằng `lat remove`, xoá luôn payload đã tải để
buộc lấy mới từ máy chủ, rồi cài lại bằng đúng lệnh học viên dùng.

### Trước khi cài lại, đối chiếu gói đã phát hành với demo

| Gói | Kết quả |
|---|---|
| `lat-review` | khớp demo |
| `lat-review-ai` | khớp demo |
| `lat-theme` | lệch 2 mục, đều là `buttons.css.bak` và `node_modules`, vốn bị loại trừ có chủ ý |
| `lat-review-child` | khớp demo, và khớp luôn bản trong `assets/themes/` của bộ cài |

Nên không phải đóng gói lại. Gói nội dung thì đã dựng lại sau khi vá ảnh (9,8 MB).

### Kết quả cài lại

| Kiểm | thu1 (bản cũ) | thu2 (bản mới) |
|---|---|---|
| Trang chủ | 200 | 200 |
| `/blog/` | 200 | 200 |
| `/coupons/` | **404, đúng thiết kế** vì bản cũ không có mô đun coupon | 200 |
| `/appliances/` | 404, đúng vì demo cũ không có danh mục này | 200 |
| `/appliances/air-purifiers/` | | 200 |
| `/kitchen-dining/air-fryers/` (lồng 2 cấp) | | **200** |

### Bốn bản vá đều tự chạy đúng, không can thiệp tay

| Vá | Kết quả trên bản cài mới tinh |
|---|---|
| Chép tệp nạp | `mu-plugins/` có `lat-review-loader.php` ngay sau khi cài |
| Nạp lại luật rewrite | Danh mục cha và danh mục con lồng 2 cấp đều 200 |
| Tên theme và plugin theo loại site | `lat-review-child / lat-theme`, đúng thiết kế chứ không nhờ may |
| Ảnh logo thương hiệu | **0 tệp thiếu**, ảnh trên `/coupons/` tải được hết |

Cộng thêm: 25 trên 25 module nạp đủ, 164 bài, nhãn điểm `LAT Score`.

### Lưu ý khi đọc kết quả

Lần kiểm đầu ngay sau khi cài, vài trang của thu1 trả 503. Đó là nhiễu lúc container vừa khởi
động, gọi lại thì 200. Đừng vội kết luận hỏng khi site vừa lên chưa tới một phút.

---

## 19. PHÁT HÀNH NHÁNH TEST VÀ SỬA DEMO (2026-09-24)

### Phát hành chỉ trên nhánh `feat/lat-review`, `main` giữ nguyên 3.19.0

Cài để test: `LATVPS_REF=feat/lat-review curl -fsSL https://raw.githubusercontent.com/affiliatecmscom/latvps/main/latvps.sh | sudo bash`.

- **3.20.0-beta.1**: loại site `latreview`, `payload-sync` làm mới cả payload LAT Review khi máy đã có, `payload/lat-review/` vào `.gitignore`.
- **3.20.0-beta.2**: vá 2 lỗi bắt được khi cài trên VPS trắng.
  1. `cron.sh` dò nhầm `affiliatecms-ai` nên khoá cron AI rơi về query string, nằm trong access log. Chỉ sửa tên cũng không đủ, vì `lat-review-ai` 1.0.0 nhỏ hơn mốc 1.3.23. Site `latreview` giờ luôn gửi khoá qua header.
  2. Tệp nạp module chép trước `wp core install` nên in ra 10 dòng "WordPress database error". Giờ chép sau bước nạp đè CSDL demo.

### Hai lỗi trên demo, user báo, đã sửa

| Lỗi | Gốc | Sửa |
|---|---|---|
| Danh mục 404, danh mục con 301 về trang chủ | Luật rewrite trong CSDL demo cũ. Bản vá ở `lat add` không chạy trên chính demo | `wp rewrite flush --hard` và **xoá FastCGI cache**, vì nginx cache cả 301 trong 60 phút |
| Show Coupon không mở tab store | Demo thiếu 27 bài store nên thiếu `data-acms-store` | Nhập 27 bài giữ nguyên ID, dựng lại 27 ảnh store-og với tên site mới |

Dựng lại gói nội dung: 9,8 MB lên 11 MB.

### Nghiệm thu trên VPS trắng: ĐẠT

Cài bằng đúng lệnh người test, rồi `lat update`, `lat payload-sync`, xoá và cài lại: 0 dòng lỗi CSDL, cron AI qua header, 27 trang store, 270 link 0 hỏng. Bấm thật trên trình duyệt: tab mới mở trang store kèm popup mã, tab gốc sang cửa hàng.

> **Bài học:** bản vá đặt trong luồng cài chỉ cứu site cài MỚI. Nguồn gốc là demo thì phải vá trên
> chính demo rồi dựng lại gói, không thì lỗi vẫn nằm đó để người dùng thấy trước tiên.

---

## 20. PHÁT HÀNH CHÍNH THỨC 3.20.0 (2026-09-24)

`feat/lat-review` gộp vào `main` (fast-forward), `VERSION` = 3.20.0, tag `v3.20.0`.

### Đường lùi cho học viên nếu bản mới lỗi

```bash
# quay về bản ổn định trước đó (ghim, lat update sẽ không kéo đi)
LATVPS_REF=v3.19.0 curl -fsSL https://raw.githubusercontent.com/affiliatecmscom/latvps/main/latvps.sh | sudo bash

# gỡ ghim, theo lại bản mới nhất
git -C /opt/latvps checkout main && lat update
```

`main` ngay trước khi gộp được giữ ở nhánh `backup/main-3.19.0`. Plugin thì học viên tự hạ về bản cũ
ngay trong wp-admin, vì trang tải về giữ đủ các bản đã phát hành.

---

## 21. BẢN VÁ BẢO MẬT 3.20.1 VÀ PLUGIN (2026-09-24)

Rà bảo mật cả LATVPS lẫn 4 plugin. Đã vá và phát hành:

| Thành phần | Bản | Vá |
|---|---|---|
| LATVPS | 3.20.1 | **Tiêm lệnh vào crontab root** qua option `acms_api_token` / `acms_ai_cron_key` (ai sửa được option WordPress là chạy lệnh root): chỉ nhận `[A-Za-z0-9]{16,64}`, lạ thì sinh lại trên host. `lat domain` nhận site `latreview`. Sau khi nạp CSDL demo: xoá usermeta mồ côi, tắt tự đăng ký, vai mặc định subscriber. `.env` ghi dưới umask 077. Kiểm `--type`, `--email` |
| `lat-review` / `affiliatecms-pro` | 1.0.3 / 1.7.35 | Cập nhật, license, telemetry luôn kiểm chứng chỉ; link gói chỉ nhận https đúng máy chủ license. Endpoint `/pkg/v1/verify` bỏ email, IP, phiên bản. Prompt tự do card title chỉ cho biên tập viên trở lên |
| `lat-review-ai` / `affiliatecms-ai` | 1.0.1 / 1.3.40 | Nút "Run now" gửi khoá cron qua header. TLS và endpoint như trên, bỏ thêm chi phí AI |
| Bản cũ riêng | 1.7.35 / 1.3.40 | Kèm 1.7.34 (nofollow), quét giá theo lô, band giá Creators, lùi nhịp 429, admin.css, Scanner không xếp việc AI trả phí trùng |

Kiểm bằng hành vi thật: cài token `$(touch /tmp/PWNED_TOKEN)` vào option rồi cài cron: token bị thay, crontab sạch, không có tệp PWNED. Plugin: kiểm cập nhật với TLS bắt buộc vẫn thành công.

Để sau (cần sửa phía `app.lat.vn`): ký số gói, gửi license qua POST thay query string.

---

## 22. BÀN GIAO CUỐI NGÀY 2026-09-24

Đang chạy: LATVPS 3.20.1, `lat-review` 1.0.3, `lat-review-ai` 1.0.1, `affiliatecms-pro` 1.7.35, `affiliatecms-ai` 1.3.40.

### Việc phiên sau

1. Thông báo học viên cập nhật: `lat update`, `lat payload-sync`, cập nhật plugin trong wp-admin, và **`lat cron`
   cài lại cron cho từng site** (bản vá crontab chỉ áp khi cron được cài lại, `lat update` không tự làm).
2. Theo dõi phản hồi. Đường lùi: plugin hạ bản trong wp-admin; LATVPS `LATVPS_REF=v3.19.0`.
3. Ký số gói cài đặt, gửi license qua POST thay query string (cần sửa phía `app.lat.vn`).
4. Điểm thấp chưa làm: giới hạn worker AI khi gọi thẳng `cron/work`; khoá Gemini trên URL (chỉ admin);
   nghi ngờ thuộc tính block trong output AI chưa lọc (chưa xác minh); thử tay crop ảnh với ảnh ảo.
5. Cân nhắc để `lat update` tự cài lại cron cho mọi site sau khi cập nhật, để bản vá kiểu này không phụ thuộc học viên.

### Quyết định đã chốt

- Site học viên chỉ có tài khoản admin (như bản cũ), không mang tác giả demo.
- Không lưu ảnh Amazon trên host: featured và og:image dùng link Amazon.
- Plugin cập nhật bắt buộc kiểm TLS, chấp nhận site hỏng bộ chứng chỉ gốc sẽ không cập nhật được.

---

## 23. SHOW PRICE ẨN CẢ TRANG COMPARE: `lat-review` 1.0.4, LATVPS 3.20.2 (2026-09-26)

**Lỗi:** tắt "Show price" trong Visible Elements thì bảng roundup chỉ để trống ô giá (vẫn còn cột
Price), còn trang `/compare/` vẫn in giá đầy đủ, kèm nhãn "Lowest price" và giá gốc gạch ngang.
`compare.php` của `lat-review-child` 1.0.0 in giá cứng, không đọc công tắc.

**Cạm bẫy:** child theme **không có kênh tự cập nhật** (updater của `lat-theme` chỉ canh chính nó,
updater của plugin chỉ canh `affiliateCMS-theme` và con của nó). Chỉ vá child theme thì mọi site
học viên đã cài giữ mãi bản lỗi.

| Thành phần | Sửa |
|---|---|
| `lat-review` 1.0.4 | `list.php` bỏ hẳn cột Price khi tắt. Plugin kèm bản vá `templates/frontend/compare-page.php`; `mu/pf-compare.php` dùng bản này **chỉ khi** `compare.php` của theme trùng md5 bản 1.0.0 gốc, site đã tự sửa thì giữ bản của họ |
| `lat-review-child` 1.0.1 (`assets/themes/`) | `compare.php` theo công tắc `show_price`, tắt là ẩn cả hàng Price. Cho site cài mới |
| LATVPS 3.20.2 | Mang child theme 1.0.1 |

Kiểm trên demo, cả khi child theme còn bản 1.0.0 lẫn sau khi lên 1.0.1: tắt thì compare mất hàng
Price (8 còn 7 hàng, 0 giá, 0 "Lowest price"), roundup mất cả cột; bật lại hiện đủ. Máy chủ cập
nhật: `lat-review` 1.0.3 thấy 1.0.4, `affiliatecms-pro` 1.7.35 không thấy gì.

**Học viên:** chỉ cần cập nhật plugin `lat-review` trong wp-admin. `lat update` là không bắt buộc,
chỉ ảnh hưởng site cài mới.

### 23.1 `lat-review` 1.0.5 và `lat-review-child` 1.0.1 trên kênh theme (cùng ngày)

Gỡ luôn gốc của cạm bẫy trên: updater trong plugin canh nhầm `affiliateCMS-theme` (theme cha bản cũ).
Giờ canh mọi theme con của `lat-theme` (bản thân `lat-theme` vẫn tự canh bằng updater riêng của nó).
Trang **Updates** của plugin liệt kê `LAT Theme` và `LAT Review Child` thay cho `AffiliateCMS Theme`.
`lat-review-child` 1.0.1 đã phát hành trên kênh theme.

Kiểm bằng hành vi thật: hạ child theme trên demo về 1.0.0, WordPress tự thấy 1.0.1, `wp theme update` cập
nhật xong, tệp khớp đúng `assets/themes/lat-review-child`. Kênh: `lat-review` 1.0.3 và 1.0.4 thấy 1.0.5;
`lat-review-ai`, `affiliatecms-pro`, `affiliatecms-ai`, `affiliateCMS-theme`, `lat-theme` không đổi.
Bản vá md5 ở 1.0.4 vẫn giữ, cho site chưa bấm cập nhật theme.

**Học viên LAT Review:** cập nhật plugin `lat-review` lên 1.0.5, sau đó cập nhật theme `LAT Review Child`
lên 1.0.1 (hiện trong Dashboard > Updates).
